<?php
/** php tests/Elements/Media/GalleryTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', sys_get_temp_dir());
define('ASSETS_VERSION', '1.0.0');
define('APP_VERSION', '2.1.0');
if (!defined('RESPONSIVE_IMAGE_SIZES')) { define('RESPONSIVE_IMAGE_SIZES', [240,480,620,960,1200,1440,1920,2400]); }
if (!defined('RESPONSIVE_IMAGE_WEBP')) { define('RESPONSIVE_IMAGE_WEBP', true); }

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\Theme;
use Wonder\Elements\Media\Gallery;

Theme::set('wonder');

$fail = 0;
function has(string $label, string $html, string $needle): void {
    global $fail;
    if (str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  missing: $needle\n"; }
}

$html = Gallery::make([ '/assets/upload/a.jpg' => 'Alpha', '/assets/upload/b.jpg' => 'Beta' ])
    ->id('gallery-test')->columns(4, 3, 2)->gap(6)->format('h-fit')->size(480)->fullSize(2400)
    ->render();

has('griglia colonne responsive', $html, "d-grid col-4 col-t-3 col-p-2 gap-6");
has('gruppo fancybox nascosto',   $html, "data-fancybox=\"gallery-test\"");
has('lightbox usa la size grande', $html, "/assets/upload/a-2400.jpg");
has('caption = alt',               $html, "data-caption=\"Alpha\"");
has('trigger indice 0',            $html, "data-fancybox-index='0'");
has('trigger indice 1',            $html, "data-fancybox-index='1'");
has('anteprima usa la size piccola', $html, "a-480.jpg");
has('script Fancybox.bind',        $html, "Fancybox.bind('[data-fancybox=\"gallery-test\"]'");

// Lista numerica tollerata (alt vuoto)
$html2 = Gallery::make([ '/assets/upload/c.jpg' ])->id('g2')->render();
has('lista numerica: trigger indice 0', $html2, "data-fancybox-index='0'");

echo "\n" . ($fail === 0 ? "PASS" : "FAIL ($fail)") . "\n";
exit($fail === 0 ? 0 : 1);
