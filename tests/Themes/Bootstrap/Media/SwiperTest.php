<?php
/** php tests/Themes/Bootstrap/Media/SwiperTest.php */
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
use Wonder\Elements\Media\Swiper;

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

// --- ZOOM + thumbnails + nav + pagination (Swiper.js reale)
$zoom = Swiper::make([ '/assets/upload/a.jpg' => 'Alpha', '/assets/upload/b.jpg' => 'Beta' ])
    ->id('swiper-zoom')->thumbnails()->zoom()->navigation()->pagination()->size(1440)->thumbsSize(240)
    ->render();

has('contenitore swiper',       $zoom, "id='swiper-zoom'");
has('swiper-wrapper',           $zoom, "swiper-wrapper");
has('slide principale grande',  $zoom, "a-1440.jpg");
has('markup panzoom',           $zoom, "f-panzoom__viewport");
has('aspect ratio bootstrap',   $zoom, "ratio ratio-1x1");
has('utility overflow bootstrap',$zoom, "overflow-hidden");
has('strip thumbnails',         $zoom, "id='swiper-zoom-thumbs'");
has('thumbnail piccola',        $zoom, "a-240.jpg");
has('init Swiper principale',   $zoom, "new Swiper('#swiper-zoom'");
has('init Swiper thumbs',       $zoom, "new Swiper('#swiper-zoom-thumbs'");
has('init Panzoom',             $zoom, "new Panzoom");
has('init su load backend',     $zoom, "window.addEventListener('load'");
has('navigation el',            $zoom, "swiper-button-next");
has('pagination el',            $zoom, "swiper-pagination");
hasnt('zoom non apre lightbox', $zoom, "Fancybox.bind");
hasnt('niente evento loaded lib',$zoom, "addEventListener('loaded'");
hasnt('niente o-hidden lib',    $zoom, "'swiper-slide o-hidden'");

// --- LIGHTBOX (esclusiva con zoom) + download + loop
$light = Swiper::make([ '/assets/upload/a.jpg' => 'Alpha' ])
    ->id('swiper-light')->thumbnails()->lightbox('galleria')->loop()->download()->fullSize(2400)
    ->render();

has('gruppo fancybox',          $light, 'data-fancybox="galleria"');
has('lightbox size grande',     $light, "/assets/upload/a-2400.jpg");
has('init Fancybox.bind',       $light, 'Fancybox.bind(\'[data-fancybox="galleria"]\'');
has('download buttons',         $light, "buttons: ['download', 'thumbs', 'close']");
has('loop attivo',              $light, "loop: true");
hasnt('lightbox non usa panzoom',$light, "f-panzoom__viewport");

// --- Esclusività: lightbox() dopo zoom() vince
$excl = Swiper::make([ '/assets/upload/a.jpg' => 'A' ])->id('x')->zoom()->lightbox('g')->render();
hasnt('zoom disattivato da lightbox', $excl, "f-panzoom__viewport");
has('lightbox attivo',                $excl, 'data-fancybox="g"');

// --- Librerie abilitate on-demand
$head = Dependencies::Head();
has('swiper in head deps',   $head, 'swiper');
has('fancyapps in head deps',$head, 'fancyapps');

echo "\n" . ($fail === 0 ? "PASS" : "FAIL ($fail)") . "\n";
exit($fail === 0 ? 0 : 1);
