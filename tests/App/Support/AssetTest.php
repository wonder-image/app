<?php
/** php tests/App/Support/AssetTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ASSETS_VERSION', '1.0.0');

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\Support\Asset;

$fail = 0;
function same(string $label, string $actual, string $expected): void {
    global $fail;
    if ($actual === $expected) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  expected: $expected\n  actual:   $actual\n"; }
}

// Sandbox con file reali per il filemtime
$root = sys_get_temp_dir().'/asset-test-'.uniqid();
mkdir($root.'/assets/'.ASSETS_VERSION.'/css/set-up', 0777, true);
mkdir($root.'/assets/lib/wonder-image/dist/frontend', 0777, true);

$rootCss = $root.'/assets/'.ASSETS_VERSION.'/css/set-up/root.css';
file_put_contents($rootCss, ':root {}');
touch($rootCss, 1721035200);

$libCss = $root.'/assets/lib/wonder-image/dist/frontend/lib.css';
file_put_contents($libCss, 'body {}');
touch($libCss, 1721040000);

// --- Asset::version() ---

same('URL con prefisso APP_URL',
    Asset::version(APP_URL.'/assets/1.0.0/css/set-up/root.css', $root),
    APP_URL.'/assets/1.0.0/css/set-up/root.css?v=1721035200');

same('path relativo alla radice',
    Asset::version('/assets/1.0.0/css/set-up/root.css', $root),
    '/assets/1.0.0/css/set-up/root.css?v=1721035200');

same('file della lib (Dependencies)',
    Asset::version(APP_URL.'/assets/lib/wonder-image/dist/frontend/lib.css', $root),
    APP_URL.'/assets/lib/wonder-image/dist/frontend/lib.css?v=1721040000');

same('file inesistente resta invariato',
    Asset::version(APP_URL.'/assets/1.0.0/css/missing.css', $root),
    APP_URL.'/assets/1.0.0/css/missing.css');

same('query string esistente resta invariata',
    Asset::version(APP_URL.'/assets/1.0.0/css/set-up/root.css?foo=bar', $root),
    APP_URL.'/assets/1.0.0/css/set-up/root.css?foo=bar');

same('URL esterno resta invariato',
    Asset::version('https://fonts.googleapis.com/css2?family=Montserrat', $root),
    'https://fonts.googleapis.com/css2?family=Montserrat');

same('URL esterno senza query resta invariato',
    Asset::version('https://cdn.example.com/lib.css', $root),
    'https://cdn.example.com/lib.css');

same('URL protocol-relative resta invariato',
    Asset::version('//cdn.example.com/lib.css', $root),
    '//cdn.example.com/lib.css');

same('fragment resta invariato',
    Asset::version(APP_URL.'/assets/1.0.0/css/set-up/root.css#top', $root),
    APP_URL.'/assets/1.0.0/css/set-up/root.css#top');

same('traversal resta invariato',
    Asset::version(APP_URL.'/assets/../.env', $root),
    APP_URL.'/assets/../.env');

same('stringa vuota resta invariata', Asset::version('', $root), '');

// --- Asset::url() ---

same('url() costruisce e versiona',
    Asset::url('css/set-up/root.css', $root),
    APP_URL.'/assets/1.0.0/css/set-up/root.css?v=1721035200');

same('url() tollera slash iniziale',
    Asset::url('/css/set-up/root.css', $root),
    APP_URL.'/assets/1.0.0/css/set-up/root.css?v=1721035200');

same('url() con file mancante: URL senza ?v',
    Asset::url('css/missing.css', $root),
    APP_URL.'/assets/1.0.0/css/missing.css');

same('url() con stringa vuota', Asset::url('', $root), '');

// Cleanup
unlink($rootCss);
unlink($libCss);

echo $fail === 0 ? "\nTutti i test passati\n" : "\n$fail test falliti\n";
exit($fail === 0 ? 0 : 1);
