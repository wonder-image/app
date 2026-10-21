<?php
/** php tests/Frontend/DataLayerConsentTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Frontend\Support\DataLayer;

$key = str_repeat('k', 32);

// Esegue lo script reale in node e restituisce il dataLayer risultante.
$runJs = static function (string $script): ?array {
    $js = 'const window = globalThis;'.$script.';process.stdout.write(JSON.stringify(window.dataLayer.map(e => (e && typeof e === "object" && "length" in e && !Array.isArray(e)) ? {args: Array.from(e)} : e)));';
    $file = tempnam(sys_get_temp_dir(), 'wi-dl').'.js';
    file_put_contents($file, $js);
    $out = shell_exec('node '.escapeshellarg($file).' 2>&1');
    @unlink($file);

    return json_decode((string) $out, true);
};

$node = trim((string) shell_exec('command -v node')) !== '';

check('dataLayer: primo evento consent default con i 4 segnali negati e wait_for_update 500', function () use ($runJs, $key, $node) {
    if (!$node) {
        return false;
    }
    $dl = $runJs(DataLayer::bootstrapScript(42, $key));

    return ($dl[0]['args'] ?? null) === ['consent', 'default', [
        'ad_storage' => 'denied', 'analytics_storage' => 'denied',
        'ad_user_data' => 'denied', 'ad_personalization' => 'denied',
        'wait_for_update' => 500,
    ]];
});

check('dataLayer: user.id è l\'hash, non l\'id in chiaro', function () use ($runJs, $key) {
    $dl = $runJs(DataLayer::bootstrapScript(42, $key));
    $id = $dl[1]['user']['id'] ?? null;

    return is_string($id) && $id === DataLayer::userId(42, $key) && preg_match('/^[0-9a-f]{32}$/', $id) === 1 && $id !== '42';
});

check('user.id: stabile per utente, diverso tra utenti e tra chiavi', fn () =>
    DataLayer::userId(42, $key) === DataLayer::userId('42', $key)
    && DataLayer::userId(42, $key) !== DataLayer::userId(43, $key)
    && DataLayer::userId(42, $key) !== DataLayer::userId(42, str_repeat('x', 32)));

check('user.id: null senza utente o con chiave vuota', fn () =>
    DataLayer::userId(null, $key) === null && DataLayer::userId(0, $key) === null
    && DataLayer::userId('', $key) === null && DataLayer::userId(5, '') === null);

check('dataLayer: senza utente user.id è null', function () use ($runJs, $key) {
    $user = $runJs(DataLayer::bootstrapScript(null, $key))[1]['user'] ?? [];

    return array_key_exists('id', $user) && $user['id'] === null;
});

check('script: nessun </script> iniettabile', fn () => !str_contains(DataLayer::bootstrapScript(1, $key), '</'));

// Testo del sorgente (head.php richiede l'intero bootstrap frontend): lo script
// di consenso viene prima del loader GTM e l'id in chiaro non è più emesso.
check('head.php: consenso prima di gtm.js, niente user_id in chiaro', function () {
    $src = (string) file_get_contents(__DIR__.'/../../app/view/components/frontend/layout/head.php');
    $consent = strpos($src, 'DataLayer::bootstrapScript(');
    $gtm = strpos($src, 'googletagmanager.com/gtm.js');

    return $consent !== false && $gtm !== false && $consent < $gtm
        && !str_contains($src, "'id' => (int) (\$_SESSION['user_id']");
});

summary();
