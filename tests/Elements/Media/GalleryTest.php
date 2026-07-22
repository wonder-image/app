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
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;
use Wonder\Elements\Media\Gallery;
use Wonder\Elements\Media\Iframe;
use Wonder\Elements\Media\Image;
use Wonder\Elements\Media\Video;

Theme::set('wonder');

$fail = 0;
function has(string $label, string $html, string $needle): void {
    global $fail;
    if (str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  missing: $needle\n"; }
}
function same(string $label, string $actual, string $expected): void {
    global $fail;
    if ($actual === $expected) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n"; }
}
function mediaInnerHtml(string $html, string $prefix): string {
    if (!str_starts_with($html, $prefix) || !str_ends_with($html, '</div>')) {
        return $html;
    }

    return substr($html, strlen($prefix), -strlen('</div>'));
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

// Il wrapper di colonna e opt-in e deve contenere l'intero frammento media.
$mediaFactories = [
    'image' => static fn () => Image::src('/assets/upload/span.jpg')->hasWebP(),
    'video' => static fn () => Video::src('/assets/video/span.mp4')->filter(),
    'iframe' => static fn () => Iframe::url('https://example.test/embed')->attr('title', 'Embed'),
    'gallery' => static fn () => Gallery::make(['/assets/upload/span.jpg' => 'Span'])->id('gallery-span'),
];

foreach (['wonder' => 'col-1', 'bootstrap' => 'col-span-1'] as $theme => $spanClass) {
    $prefix = '<div class="' . $spanClass . '">';

    foreach ($mediaFactories as $name => $factory) {
        $plain = $factory()->render($theme);
        $wrapped = $factory()->columnSpan(1)->render($theme);

        same("$theme $name senza wrapper implicito", str_starts_with($plain, $prefix) ? 'wrapped' : 'plain', 'plain');
        same("$theme $name wrapper esplicito", str_starts_with($wrapped, $prefix) ? 'wrapped' : 'plain', 'wrapped');
        same("$theme $name contenuto invariato", mediaInnerHtml($wrapped, $prefix), $plain);
    }
}

$wonderResponsive = Image::src('/assets/upload/span.jpg')
    ->columnSpan(['default' => 12, 'md' => 6, 'xl' => 4])
    ->render('wonder');
has('span responsive Wonder usa solo classi lib', $wonderResponsive, '<div class="col-4 col-t-6 col-p-12">');

$bootstrapResponsive = Image::src('/assets/upload/span.jpg')
    ->columnSpan(['default' => 12, 'md' => 6, 'xl' => 4])
    ->render('bootstrap');
has('span Bootstrap usa la classe desktop disponibile', $bootstrapResponsive, '<div class="col-span-4">');

Theme::set('bootstrap');
$layoutPlain = ResourceFormLayoutRenderer::render(
    (new Form())->components([Image::src('/assets/upload/layout.jpg')])
);
same('layout Resource non aggiunge wrapper impliciti ai media', (string) substr_count($layoutPlain, 'col-span-'), '0');

$layoutSpanned = ResourceFormLayoutRenderer::render(
    (new Form())->components([Image::src('/assets/upload/layout.jpg')->columnSpan(1)])
);
same('layout Resource conserva il solo wrapper media esplicito', (string) substr_count($layoutSpanned, 'col-span-1'), '1');

$mapContainer = (new Container())
    ->noGrid()
    ->id('map-ratio')
    ->addClass('ratio ratio-16x9 img-thumbnail')
    ->attr('data-role', 'map')
    ->style('padding', '1rem')
    ->components([
        Iframe::url('https://example.test/embed')
            ->fitCover()
            ->addClass('rounded')
            ->attr('allowfullscreen', true),
    ]);
$mapLayout = ResourceFormLayoutRenderer::render(
    (new Form())->components([
        SectionTitle::make('Mappa')->level(5),
        $mapContainer,
    ])
);

has('SectionTitle permette il livello h5', $mapLayout, '>Mappa</h5>');
has('Container no-grid conserva il wrapper Resource', $mapLayout, '<div class="col-12"><div');
has('Container no-grid conserva le classi ratio', $mapLayout, 'class="ratio ratio-16x9 img-thumbnail"');
has('Container no-grid conserva id', $mapLayout, 'id="map-ratio"');
has('Container no-grid conserva data attribute', $mapLayout, 'data-role="map"');
has('Container no-grid conserva style', $mapLayout, 'style="padding: 1rem;"');
has('Iframe resta figlio diretto del ratio', $mapLayout, 'id="map-ratio"><iframe');
has('Iframe conserva classi custom e fit', $mapLayout, 'class="rounded object-fit-cover w-100 h-100"');
has('Iframe conserva attributi booleani', $mapLayout, 'allowfullscreen');
same('Container no-grid omette la row interna', str_contains($mapLayout, '<div class="col-12"><div class="row ') ? 'row' : 'plain', 'plain');
same('Container no-grid emette una sola classe ratio', (string) substr_count($mapLayout, 'ratio-16x9'), '1');

$directMap = $mapContainer->render('bootstrap');
has('Container diretto conserva il wrapper di span', $directMap, '<div class="col-span-1"><div');
has('Container diretto no-grid conserva le classi custom', $directMap, 'class="ratio ratio-16x9 img-thumbnail"');
same('Container diretto non duplica class', (string) substr_count($directMap, 'ratio-16x9'), '1');

$normalContainer = ResourceFormLayoutRenderer::render(
    (new Form())->components([
        (new Container())->components([
            Iframe::url('https://example.test/default-grid'),
        ]),
    ])
);
has('Container standard mantiene la griglia Resource', $normalContainer, '<div class="col-12"><div class="row g-3"');
Theme::set('wonder');

echo "\n" . ($fail === 0 ? "PASS" : "FAIL ($fail)") . "\n";
exit($fail === 0 ? 0 : 1);
