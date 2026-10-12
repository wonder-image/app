<?php
/** php tests/Security/RecaptchaConfigTest.php — chiave v3 e badge nella configurazione. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Credentials;
use Wonder\App\Models\Config\Security;
use Wonder\App\Resources\Config\SecurityResource;
use Wonder\App\Theme;
use Wonder\Plugin\Google\Credentials as GoogleCredentials;

echo "RecaptchaConfig\n";

$root = dirname(__DIR__, 2);
$read = fn (string $path): string => (string) file_get_contents($root.'/'.$path);

// Il formSchema() legge i servizi mail: senza runtime basta questo elenco.
if (!function_exists('mailService')) {
    function mailService(): array
    {
        return ['phpmailer' => 'PHPMailer', 'brevo' => 'Brevo'];
    }
}

// Path e il layout leggono le costanti del bootstrap, che la CLI non definisce.
foreach (['ROOT' => $root, 'APP_URL' => 'https://app.example.test', 'ASSETS_VERSION' => '1', 'APP_VERSION' => '1'] as $nome => $valore) {
    if (!defined($nome)) {
        define($nome, $valore);
    }
}

// Il layout risolve i Tooltip nel tema attivo, e la CLI non ne imposta uno.
Theme::set('bootstrap');

const CHIAVI_RECAPTCHA = ['G_RECAPTCHA_SITE_KEY', 'G_RECAPTCHA_SECRET_KEY', 'G_RECAPTCHA_SCORE_SITE_KEY', 'G_RECAPTCHA_BADGE'];

// Le variabili d'ambiente della macchina non devono falsare le prove.
foreach (CHIAVI_RECAPTCHA as $chiave) {
    unset($_ENV[$chiave]);
}

// Permette di azzerare e impostare la cache statica di Credentials::api(), e di leggere le chiavi reCAPTCHA da una riga.
$credenziali = new class extends Credentials {
    public static function azzera(): void
    {
        static::$API = null;
    }

    public static function imposta(object $api): void
    {
        static::$API = $api;
    }

    public static function leggi(array $riga): object
    {
        $api = static::apiDefaults();
        static::recaptcha($api, $riga);

        return $api;
    }
};

check('Credentials dell\'app: chiave v3 vuota e badge \'true\' di default, poi dall\'ambiente nei loro campi', function () use ($credenziali) {
    $default = Credentials::apiDefaults();

    if ($default->g_recaptcha_score_site_key !== '' || $default->g_recaptcha_badge !== 'true') {
        return false;
    }

    // api() legge comunque la riga di security dal DB, ma l'ambiente vince su di essa: il risultato non dipende dal DB.
    // Il ramo «riga senza ambiente» è coperto dal check su recaptcha(), qui sotto.
    $_ENV['G_RECAPTCHA_SITE_KEY'] = 'chiave-v2-env';
    $_ENV['G_RECAPTCHA_SCORE_SITE_KEY'] = 'chiave-v3-env';
    $_ENV['G_RECAPTCHA_BADGE'] = 'false';

    try {
        $credenziali::azzera();
        $api = Credentials::api();

        return $api->g_recaptcha_site_key === 'chiave-v2-env'
            && $api->g_recaptcha_score_site_key === 'chiave-v3-env'
            && $api->g_recaptcha_badge === 'false';
    } finally {
        $credenziali::azzera();

        foreach (CHIAVI_RECAPTCHA as $chiave) {
            unset($_ENV[$chiave]);
        }
    }
});

check('Credentials::recaptcha(): la riga di security batte il default, l\'ambiente batte la riga, riga vuota o assente lascia il default', function () use ($credenziali) {
    $riga = [
        'g_recaptcha_site_key' => 'chiave-v2-riga',
        'g_recaptcha_secret_key' => 'segreto-riga',
        'g_recaptcha_score_site_key' => 'chiave-v3-riga',
        'g_recaptcha_badge' => 'false',
    ];
    $rigaVuota = ['g_recaptcha_score_site_key' => '', 'g_recaptcha_badge' => ''];

    foreach (CHIAVI_RECAPTCHA as $chiave) {
        unset($_ENV[$chiave]);
    }

    try {
        $daRiga = $credenziali::leggi($riga);
        $daRigaVuota = $credenziali::leggi($rigaVuota);
        $senzaChiavi = $credenziali::leggi([]);

        $_ENV['G_RECAPTCHA_SCORE_SITE_KEY'] = 'chiave-v3-env';
        $_ENV['G_RECAPTCHA_BADGE'] = 'true';
        $daEnv = $credenziali::leggi($riga);

        return $daRiga->g_recaptcha_site_key === 'chiave-v2-riga'
            && $daRiga->g_recaptcha_secret_key === 'segreto-riga'
            && $daRiga->g_recaptcha_score_site_key === 'chiave-v3-riga'
            && $daRiga->g_recaptcha_badge === 'false'
            && $daRigaVuota->g_recaptcha_score_site_key === ''
            && $daRigaVuota->g_recaptcha_badge === 'true'
            && $senzaChiavi->g_recaptcha_score_site_key === ''
            && $senzaChiavi->g_recaptcha_badge === 'true'
            && $daEnv->g_recaptcha_score_site_key === 'chiave-v3-env'
            && $daEnv->g_recaptcha_badge === 'true';
    } finally {
        foreach (CHIAVI_RECAPTCHA as $chiave) {
            unset($_ENV[$chiave]);
        }
    }
});

check('Credentials di Google: punteggio v3 e badge dalla configurazione, la chiave v2 resta separata', function () use ($credenziali) {
    $api = Credentials::apiDefaults();
    $api->g_recaptcha_site_key = 'chiave-v2';
    $api->g_recaptcha_score_site_key = 'chiave-v3';
    $api->g_recaptcha_badge = 'false';

    try {
        $credenziali::imposta($api);
        $google = GoogleCredentials::get();

        return $google->recaptcha_site_key === 'chiave-v2'
            && $google->recaptcha_score_site_key === 'chiave-v3'
            && $google->recaptcha_badge === 'false';
    } finally {
        $credenziali::azzera();
    }
});

check('modello Security: colonna del badge con default \'true\', chiave v3 come colonna e come campo', function () {
    $colonne = [];

    foreach (Security::tableSchema() as $colonna) {
        $colonne[$colonna->name] = $colonna;
    }

    $campi = array_map(static fn ($campo) => $campo->key, Security::dataSchema());

    return isset($colonne['g_recaptcha_score_site_key'], $colonne['g_recaptcha_badge'])
        && ($colonne['g_recaptcha_badge']->schema['default'] ?? null) === 'true'
        && in_array('g_recaptcha_score_site_key', $campi, true)
        && in_array('g_recaptcha_badge', $campi, true);
});

check('backend: etichette, select del badge e le due righe reCAPTCHA v2 e v3', function () {
    $etichette = [
        'g_recaptcha_site_key' => 'Chiave Sito',
        'g_recaptcha_score_site_key' => 'Chiave Sito',
        'g_recaptcha_badge' => 'Badge',
    ];

    foreach ($etichette as $chiave => $etichetta) {
        if (!str_contains(SecurityResource::getInput($chiave)->render('bootstrap'), $etichetta)) {
            return false;
        }
    }

    $badge = SecurityResource::getInput('g_recaptcha_badge')->render('bootstrap');

    if (!str_contains($badge, '<option value="true" selected>Visibile</option><option value="false">Nascosto</option>')
        || !str_contains($badge, ' required')) {
        return false;
    }

    $layout = SecurityResource::formLayoutSchema()->render('bootstrap');

    if (str_contains($layout, 'name="g_recaptcha_secret_key"')) {
        return false;
    }

    // Riga v2 con la chiave a casella, riga v3 con chiave e badge, poi la sezione Maps.
    $posizioni = [];

    foreach (['Google reCAPTCHA v2*', 'name="g_recaptcha_site_key"', 'Google reCAPTCHA v3*', 'name="g_recaptcha_score_site_key"', 'name="g_recaptcha_badge"', 'Google Maps*'] as $pezzo) {
        $pos = strpos($layout, $pezzo);

        if ($pos === false) {
            return false;
        }

        $posizioni[] = $pos;
    }

    $ordinate = $posizioni;
    sort($ordinate);

    return $ordinate === $posizioni;
});

check('dev-shared: la riga della chiave v3', fn () => str_contains($read('docs/app/piattaforma/dev-shared.md'), '`G_RECAPTCHA_SCORE_SITE_KEY`'));

summary();
