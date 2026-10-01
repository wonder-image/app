<?php
/** php tests/Themes/Bootstrap/Media/GalleryTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', sys_get_temp_dir());
define('ASSETS_VERSION', '1.0.0');
define('APP_VERSION', '2.1.0');
if (!defined('RESPONSIVE_IMAGE_SIZES')) { define('RESPONSIVE_IMAGE_SIZES', [240,480,620,960,1200,1440,1920,2400]); }
if (!defined('RESPONSIVE_IMAGE_WEBP')) { define('RESPONSIVE_IMAGE_WEBP', true); }

require __DIR__ . '/../../../../vendor/autoload.php';

use Wonder\App\Theme;
use Wonder\App\Dependencies;
use Wonder\Elements\Media\Gallery;

Theme::set('bootstrap');

$fail = 0;
function has(string $label, string $html, string $needle): void {
    global $fail;
    if (str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  missing: $needle\n"; }
}
function hasnt(string $label, string $html, string $needle): void {
    global $fail;
    if (!str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  unexpected: $needle\n"; }
}

// --- griglia Bootstrap + lightbox Fancybox on-demand
$html = Gallery::make([ '/assets/upload/a.jpg' => 'Alpha', '/assets/upload/b.jpg' => 'Beta' ])
    ->id('gallery-test')->columns(4, 3, 2)->gap(6)->format('h-fit')->size(480)->fullSize(2400)->download()
    ->render();

has('griglia responsive Bootstrap', $html, 'row row-cols-2 row-cols-md-3 row-cols-xl-4');
has('gutter g-5',                    $html, 'g-5');
has('anchor fancybox',               $html, 'data-fancybox="gallery-test"');
has('href full-size',                $html, 'a-2400.jpg');
has('caption = alt',                 $html, 'data-caption="Alpha"');
has('anteprima preview-size',        $html, 'a-480.jpg');
has('init Fancybox.bind',            $html, 'Fancybox.bind(\'[data-fancybox="gallery-test"]\'');
has('download buttons',              $html, "buttons: ['download', 'thumbs', 'close']");
has('init su load backend',          $html, "window.addEventListener('load'");
hasnt('niente classi lib griglia',   $html, 'd-grid col-4');

// --- Fancybox abilitato on-demand
has('fancyapps in head deps', Dependencies::Head(), 'fancyapps');

// --- format aspect-ratio -> Bootstrap ratio
$ratio = Gallery::make([ '/assets/upload/a.jpg' => 'Alpha' ])->id('g-ratio')->format('16-9')->render();
has('ratio 16x9', $ratio, 'ratio ratio-16x9');
has('cover object-fit', $ratio, 'object-fit-cover');

// --- ratio arbitrario "W-H" (non tra i 4 nativi Bootstrap) -> .ratio + --bs-aspect-ratio
$r32 = Gallery::make([ '/assets/upload/a.jpg' => 'Alpha' ])->id('g32')->format('3-2')->render();
has('ratio custom box',  $r32, "class='ratio'");
has('aspect ratio var',  $r32, '--bs-aspect-ratio');

// --- lista numerica tollerata (alt vuoto)
$html2 = Gallery::make([ '/assets/upload/c.jpg' ])->id('g2')->render();
has('lista numerica: cella col', $html2, 'data-fancybox="g2"');

echo "\n" . ($fail === 0 ? "PASS" : "FAIL ($fail)") . "\n";
exit($fail === 0 ? 0 : 1);
