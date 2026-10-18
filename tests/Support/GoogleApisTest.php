<?php
/** php tests/Support/GoogleApisTest.php — registro delle API Google da abilitare. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Support\GoogleCloud\GoogleApi;
use Wonder\App\Support\GoogleCloud\GoogleApis;

echo "GoogleApis\n";

$root = dirname(__DIR__, 2);
$previousRoot = $GLOBALS['ROOT'] ?? null;
$sandbox = sys_get_temp_dir().'/google-apis-test-'.uniqid();
mkdir($sandbox.'/custom/config', 0777, true);
$GLOBALS['ROOT'] = $sandbox;

check('core: reCAPTCHA Enterprise obbligatoria, le altre facoltative, ognuna con nome e campi', function () {
    GoogleApis::reset();
    $all = GoogleApis::all();

    foreach (['recaptcha_enterprise', 'maps_javascript', 'places', 'places_sync', 'maps_embed', 'directions', 'google_oauth'] as $key) {
        if (!isset($all[$key]) || $all[$key]->getName() === $key || $all[$key]->getFields() === []) {
            return false;
        }
    }

    return array_keys(GoogleApis::required()) === ['recaptcha_enterprise']
        && $all['recaptcha_enterprise']->getService() === 'recaptchaenterprise.googleapis.com'
        && $all['google_oauth']->getService() === null;
});

check('consoleUrl(): libreria API con il progetto, client OAuth senza servizio', function () {
    GoogleApis::reset();

    return GoogleApis::get('places')->consoleUrl('mio progetto') === 'https://console.cloud.google.com/apis/library/places.googleapis.com?project=mio%20progetto'
        && GoogleApis::get('places')->consoleUrl() === 'https://console.cloud.google.com/apis/library/places.googleapis.com'
        && GoogleApis::get('google_oauth')->consoleUrl() === 'https://console.cloud.google.com/auth/clients';
});

check('merge(): obbligatoria se lo è per una sorgente, campi e utilizzatori sommati, il resto dalla prima', function () {
    $core = GoogleApi::make('maps_javascript')->name('Maps JavaScript API')->service('maps-backend.googleapis.com')->fields(['gcp_client_api_key'])->usedBy('app');
    $module = GoogleApi::make('maps_javascript')->name('Altro')->fields(['g_maps_map_id'])->required()->usedBy('ecommerce', 'app');
    $merged = $core->merge($module);

    return $merged->isRequired()
        && !$core->isRequired()
        && $merged->getName() === 'Maps JavaScript API'
        && $merged->getService() === 'maps-backend.googleapis.com'
        && $merged->getFields() === ['gcp_client_api_key', 'g_maps_map_id']
        && $merged->getUsedBy() === ['app', 'ecommerce'];
});

check('sito: custom/config/google-apis.php rende obbligatoria un\'API del core e ne aggiunge una nuova', function () use ($sandbox) {
    file_put_contents($sandbox.'/custom/config/google-apis.php', <<<'PHP'
    <?php
    use Wonder\App\Support\GoogleCloud\GoogleApi;

    return [
        GoogleApi::make('places')->required()->usedBy('sito'),
        GoogleApi::make('routes')->name('Routes API')->service('routes.googleapis.com')->fields(['gcp_api_key'])->keyType(GoogleApi::KEY_SERVER),
    ];
    PHP);

    try {
        GoogleApis::reset();
        $all = GoogleApis::all();

        return $all['places']->isRequired()
            && $all['places']->getName() === 'Places API (New)'
            && $all['places']->getUsedBy() === ['app', 'sito']
            && isset($all['routes'])
            && array_keys(GoogleApis::required()) === ['recaptcha_enterprise', 'places'];
    } finally {
        unlink($sandbox.'/custom/config/google-apis.php');
        GoogleApis::reset();
    }
});

check('missing(): solo le obbligatorie con un campo vuoto; la chiave server non vale come chiave client', function () use ($sandbox) {
    file_put_contents($sandbox.'/custom/config/google-apis.php', <<<'PHP'
    <?php
    return [Wonder\App\Support\GoogleCloud\GoogleApi::make('maps_javascript')->required()];
    PHP);

    try {
        GoogleApis::reset();
        $soloServer = ['gcp_project_id' => 'p', 'gcp_api_key' => 'server', 'g_recaptcha_site_key' => 'site', 'gcp_client_api_key' => ''];
        $completo = ['gcp_client_api_key' => 'client'] + $soloServer;

        return array_keys(GoogleApis::missing($soloServer)) === ['maps_javascript']
            && array_keys(GoogleApis::missing([])) === ['recaptcha_enterprise', 'maps_javascript']
            && GoogleApis::missing($completo) === [];
    } finally {
        unlink($sandbox.'/custom/config/google-apis.php');
        GoogleApis::reset();
    }
});

check('definizioni non valide: chiave, servizio, tipo di chiave, voce che non è una GoogleApi', function () use ($sandbox) {
    $throws = static function (callable $fn): bool {
        try {
            $fn();
        } catch (\InvalidArgumentException) {
            return true;
        }

        return false;
    };

    file_put_contents($sandbox.'/custom/config/google-apis.php', "<?php return ['places'];");

    try {
        return $throws(fn () => GoogleApi::make('Maps JS'))
            && $throws(fn () => GoogleApi::make('maps')->service('https://example.com'))
            && $throws(fn () => GoogleApi::make('maps')->keyType('public'))
            && $throws(function () { GoogleApis::reset(); GoogleApis::all(); });
    } finally {
        unlink($sandbox.'/custom/config/google-apis.php');
        GoogleApis::reset();
    }
});

check('lang: ogni API del core ha la descrizione in it e en, con i tipi di chiave', function () use ($root) {
    GoogleApis::reset();

    foreach (['it', 'en'] as $lang) {
        $json = json_decode((string) file_get_contents($root.'/resources/lang/'.$lang.'/google.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach (GoogleApis::all() as $api) {
            $path = explode('.', $api->getPurpose());
            if (array_shift($path) !== 'google') {
                return false;
            }
            $value = $json;
            foreach ($path as $part) {
                $value = $value[$part] ?? null;
            }
            if (!is_string($value) || $value === '' || !isset($json['credentials']['key_types'][$api->getKeyType()])) {
                return false;
            }
        }
    }

    return true;
});

rmdir($sandbox.'/custom/config');
rmdir($sandbox.'/custom');
rmdir($sandbox);
$GLOBALS['ROOT'] = $previousRoot;

summary();
