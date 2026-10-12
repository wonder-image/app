<?php
/** php tests/Themes/RecaptchaComponentTest.php — il riquadro reCAPTCHA del form nei quattro modi. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Credentials;
use Wonder\Themes\Support\PageAssets;
use Wonder\Themes\Wonder\Form\Components\reCAPTCHA;

// In CLI session_start() dopo un output fallisce: tutto l'output va nel buffer (come in RecaptchaGuardTest).
$sessioni = sys_get_temp_dir() . '/wi-recaptcha-component-' . getmypid();
@mkdir($sessioni, 0777, true);
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
session_save_path($sessioni);
session_start();

echo "reCAPTCHA component\n";

$schema = ['recaptcha_action' => 'contact'];
$required = fn (string $html): bool => str_contains($html, 'name="g-recaptcha-token" required') && str_contains($html, 'name="g-recaptcha-action" required');

check('solo casella: come oggi, campi obbligatori, tipo checkbox', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, 'site-key', '', true, false);

    return $required($html) && !str_contains($html, 'data-wi-score-key') && str_contains($html, 'name="g-recaptcha-type" value="checkbox"')
        && str_contains($html, 'data-wi-site-key="site-key"') && str_contains($html, 'data-wi-action="contact"') && !str_contains($html, 'wi-recaptcha-terms');
});

check('solo v3: chiave v3, campi non obbligatori, tipo score', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, '', 'score-key', true, false);

    return !$required($html) && str_contains($html, 'data-wi-score-key="score-key"') && str_contains($html, 'name="g-recaptcha-type" value="score"');
});

check('tutte e due, senza sfida: tipo score, nessun data-wi-challenge', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, 'site-key', 'score-key', true, false);

    return !$required($html) && str_contains($html, 'value="score"') && !str_contains($html, 'data-wi-challenge');
});

check('tutte e due, sfida aperta: casella obbligatoria, tipo checkbox', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, 'site-key', 'score-key', true, true);

    return $required($html) && str_contains($html, 'data-wi-challenge="1"') && str_contains($html, 'value="checkbox"');
});

check('sfida senza chiave a casella o senza v3: ignorata', function () use ($schema) {
    return !str_contains(reCAPTCHA::markup($schema, '', 'score-key', true, true), 'data-wi-challenge')
        && !str_contains(reCAPTCHA::markup($schema, 'site-key', '', true, true), 'data-wi-challenge');
});

check('nessuna chiave: come oggi', function () use ($schema, $required) {
    $html = reCAPTCHA::markup($schema, '', '', true, false);

    return $required($html) && str_contains($html, 'data-wi-site-key=""') && str_contains($html, 'value="checkbox"');
});

check('data-wi-badge sempre "true" o "false"', function () use ($schema) {
    return str_contains(reCAPTCHA::markup($schema, 'site-key', '', true, false), 'data-wi-badge="true"')
        && str_contains(reCAPTCHA::markup($schema, 'site-key', 'score-key', false, false), 'data-wi-badge="false"');
});

check('attribuzione solo con badge nascosto e v3, con i due link di Google', function () use ($schema) {
    $html = reCAPTCHA::markup($schema, 'site-key', 'score-key', false, false);

    return str_contains($html, '<p class="wi-recaptcha-terms">')
        && str_contains($html, 'href="https://policies.google.com/privacy"')
        && str_contains($html, 'href="https://policies.google.com/terms"')
        && !str_contains($html, '{{privacy}}')
        && !str_contains(reCAPTCHA::markup($schema, 'site-key', '', false, false), 'wi-recaptcha-terms')
        && !str_contains(reCAPTCHA::markup($schema, 'site-key', 'score-key', true, false), 'wi-recaptcha-terms');
});

check('valori di base e escape degli attributi', function () {
    $html = reCAPTCHA::markup(['recaptcha_action' => 'a"b'], 'k"1', 's"2', true, false);
    $base = reCAPTCHA::markup([], 'site-key', '', true, false);

    return str_contains($html, 'data-wi-action="a&quot;b"') && str_contains($html, 'data-wi-site-key="k&quot;1"') && str_contains($html, 'data-wi-score-key="s&quot;2"')
        && str_contains($base, 'data-wi-theme="light"') && str_contains($base, 'data-wi-size="normal"') && str_contains($base, 'data-wi-action="submit"');
});

check('testi dell\'attribuzione nelle cinque lingue', function () {
    foreach (['it', 'en', 'de', 'es', 'fr'] as $lang) {
        $data = json_decode((string) file_get_contents(__DIR__ . "/../../resources/lang/{$lang}/components.json"), true);
        $texts = $data['forms']['recaptcha'] ?? [];

        if (!str_contains((string) ($texts['notice'] ?? ''), '{{privacy}}') || !str_contains((string) ($texts['notice'] ?? ''), '{{terms}}')
            || trim((string) ($texts['privacy'] ?? '')) === '' || trim((string) ($texts['terms'] ?? '')) === '') {
            echo "    {$lang}: forms.recaptcha incompleto\n";

            return false;
        }
    }

    return true;
});

// Credenziali: stub di Credentials::api() (cache statica), azzerato dopo ogni check.
$credenziali = new class extends Credentials {
    public static function imposta(object $api): void
    {
        static::$API = $api;
    }

    public static function azzera(): void
    {
        static::$API = null;
    }
};

$api = static fn (string $badge): object => (object) [
    'g_recaptcha_site_key' => 'site-key',
    'g_recaptcha_score_site_key' => 'score-key',
    'g_recaptcha_badge' => $badge,
];

check('renderInput: badge " FALSE " spegne il badge e aggiunge l\'attribuzione', function () use ($credenziali, $api) {
    $credenziali::imposta($api(' FALSE '));
    try {
        $html = (new reCAPTCHA())->render((object) ['schema' => ['recaptcha_action' => 'contact']]);
    } finally {
        $credenziali::azzera();
    }

    return str_contains($html, 'data-wi-badge="false"') && str_contains($html, 'wi-recaptcha-terms');
});

check('renderInput: sfida in sessione aperta una sola volta', function () use ($credenziali, $api) {
    $credenziali::imposta($api('true'));
    try {
        $_SESSION['wi_recaptcha_challenge']['contact'] = true;
        $primo = (new reCAPTCHA())->render((object) ['schema' => ['recaptcha_action' => 'contact']]);
        $secondo = (new reCAPTCHA())->render((object) ['schema' => ['recaptcha_action' => 'contact']]);
    } finally {
        $credenziali::azzera();
    }

    return str_contains($primo, 'data-wi-challenge="1"') && !str_contains($secondo, 'data-wi-challenge');
});

// In modalità v3 la cella resta vuota: la griglia del form le darebbe comunque il gap sopra e sotto.
$regola = '.wi-recaptcha-cell:has(.g-recaptcha:empty):not(:has(.wi-recaptcha-terms)){display:none}';

check('renderInput: lo stile della cella vuota esce una volta sola per pagina', function () use ($credenziali, $api, $regola) {
    $credenziali::imposta($api('true'));
    PageAssets::reset();
    try {
        $primo = (new reCAPTCHA())->render((object) ['schema' => ['recaptcha_action' => 'contact']]);
        $secondo = (new reCAPTCHA())->render((object) ['schema' => ['recaptcha_action' => 'contact']]);
    } finally {
        $credenziali::azzera();
        PageAssets::reset();
    }

    return substr_count($primo, 'data-wi-recaptcha-style') === 1 && str_contains($primo, $regola)
        && !str_contains($secondo, 'data-wi-recaptcha-style') && str_contains($secondo, 'class="g-recaptcha"');
});

check('markup() resta senza stile: lo aggiunge solo la resa della pagina', function () use ($schema) {
    return !str_contains(reCAPTCHA::markup($schema, 'site-key', 'score-key', true, false), '<style');
});

check('il form di accesso marca la cella del reCAPTCHA', function () {
    return str_contains((string) file_get_contents(__DIR__ . '/../../app/view/components/frontend/account/auth-form.php'), 'class="wi-recaptcha-cell ');
});

// Ultimo: dichiara __t che lancia, come un provider non inizializzato o una chiave mancante.
if (!function_exists('__t')) {
    function __t(string $key, array $replacements = []): string
    {
        throw new \RuntimeException('chiave mancante');
    }
}

check('testi di ripiego se __t lancia: attribuzione in italiano', function () {
    $html = reCAPTCHA::markup(['recaptcha_action' => 'contact'], '', 'score-key', false, false);

    return str_contains($html, 'Norme sulla privacy') && str_contains($html, 'Termini di servizio');
});

summary();
