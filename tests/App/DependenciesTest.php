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

$GLOBALS['FRONTEND'] = true;
Dependencies::wiFrontend();
has('frontend defers head scripts by default', Dependencies::Head(), 'moment.js" defer></script>');
has('frontend defers body scripts by default', Dependencies::Body(), 'body-end.js" defer></script>');

mkdir($sandbox.'/assets/lib/wonder-image/dist/frontend', 0777, true);
file_put_contents($sandbox.'/assets/lib/wonder-image/dist/frontend/lib.css', '.test{display:block}');
file_put_contents($sandbox.'/assets/lib/wonder-image/dist/frontend/head.css', '.glass{filter:url(../images/glass.svg#container-glass)}');
Dependencies::wiLib();
has('frontend inlines wi-lib by default', Dependencies::Head(), '<style>.test{display:block}</style>');
has(
    'frontend inlines core CSS and resolves its assets',
    Dependencies::Head(),
    'url('.APP_URL.'/assets/lib/wonder-image/dist/images/glass.svg#container-glass)',
);

mkdir($sandbox.'/assets/lib/wonder-image/dist/lib/swiperjs', 0777, true);
file_put_contents($sandbox.'/assets/lib/wonder-image/dist/lib/swiperjs/swiper.css', '.swiper{display:block}');
Dependencies::swiper();
has('frontend inlines Swiper CSS by default', Dependencies::Head(), '<style>.swiper{display:block}</style>');

$GLOBALS['FRONTEND'] = false;
has('backend remains synchronous', Dependencies::Head(), 'moment.js"></script>');
$GLOBALS['FRONTEND'] = true;
Dependencies::deferFrontend(false);
has('legacy frontend can opt out', Dependencies::Head(), 'moment.js"></script>');

echo $fail === 0 ? "\nTutti i test passati\n" : "\n$fail test falliti\n";
exit($fail === 0 ? 0 : 1);
