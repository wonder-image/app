<?php
/** php tests/Themes/Wonder/Media/ImageTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', sys_get_temp_dir());
define('ASSETS_VERSION', '1.0.0');
define('APP_VERSION', '2.1.0');
if (!defined('RESPONSIVE_IMAGE_SIZES')) { define('RESPONSIVE_IMAGE_SIZES', [240,480,620,960,1200,1440,1920,2400]); }
if (!defined('RESPONSIVE_IMAGE_WEBP')) { define('RESPONSIVE_IMAGE_WEBP', true); }

require __DIR__ . '/../../../../vendor/autoload.php';

use Wonder\App\Theme;
use Wonder\Elements\Media\Image;

Theme::set('wonder');

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

// --- picture + webp + srcset
$pic = Image::src('/assets/upload/a.jpg')->hasWebP()->size(960)->sizes(RESPONSIVE_IMAGE_SIZES)->alt('X')->render();
has('picture wrapper',   $pic, '<picture>');
has('source webp type',  $pic, 'type="image/webp"');
has('img base jpg 960',  $pic, 'a-960.jpg');
has('srcset webp 960',   $pic, 'a-960.webp');
has('alt',               $pic, 'alt="X"');

// --- classi lib per fit / skeleton / draggable (output invariato)
has('fit cover lib',   Image::src('/assets/upload/a.jpg')->size(480)->fitCover()->render(),   'bg bg-cover');
has('fit contain lib', Image::src('/assets/upload/a.jpg')->size(480)->fitContain()->render(), 'bg bg-contain');
has('skeleton lib',    Image::src('/assets/upload/a.jpg')->size(480)->skeleton()->render(),   'skeleton');
has('not draggable lib',Image::src('/assets/upload/a.jpg')->size(480)->notDraggable()->render(),'no-interaction unselectable');

// --- src .webp: ramo <img> (niente <picture>)
$w = Image::src('/assets/upload/a.webp')->hasWebP()->size(960)->alt('X')->render();
hasnt('webp src senza picture', $w, '<picture>');
has('webp src img 960',         $w, 'a-960.webp');

echo "\n" . ($fail === 0 ? "PASS" : "FAIL ($fail)") . "\n";
exit($fail === 0 ? 0 : 1);
