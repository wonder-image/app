<?php
/** php tests/App/DependenciesTest.php */
declare(strict_types=1);

// Sandbox come ROOT: Dependencies risolve i file sotto ROOT/assets/lib/...
$sandbox = sys_get_temp_dir().'/dependencies-test-'.uniqid();

define('APP_URL', 'https://example.test');
define('ROOT', $sandbox);
define('ASSETS_VERSION', '1.0.0');

require __DIR__ . '/../../vendor/autoload.php';

use Wonder\App\Dependencies;

$fail = 0;
function has(string $label, string $html, string $needle): void {
    global $fail;
    if (str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  missing: $needle\n"; }
}

mkdir($sandbox.'/assets/lib/wonder-image/dist/lib/jquery', 0777, true);

$jquery = $sandbox.'/assets/lib/wonder-image/dist/lib/jquery/jquery.js';
file_put_contents($jquery, '// jquery');
touch($jquery, 1721035200);

Dependencies::jquery();
Dependencies::moment(); // file NON creato nella sandbox

$head = Dependencies::Head();

has('file esistente versionato',
    $head, APP_URL.'/assets/lib/wonder-image/dist/lib/jquery/jquery.js?v=1721035200');

has('file mancante senza ?v',
    $head, "src=\"".APP_URL."/assets/lib/wonder-image/dist/lib/moment/moment.js\"");

unlink($jquery);

echo $fail === 0 ? "\nTutti i test passati\n" : "\n$fail test falliti\n";
exit($fail === 0 ? 0 : 1);
