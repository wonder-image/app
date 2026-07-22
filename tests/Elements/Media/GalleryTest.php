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
use Wonder\Elements\Components\InfoCard;
use Wonder\Elements\Components\MetricCard;
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

$rootMapLayout = ResourceFormLayoutRenderer::renderLayout($mapContainer);
has('Container root no-grid conserva le classi ratio', $rootMapLayout, '<div class="ratio ratio-16x9 img-thumbnail"');
has('Container root no-grid conserva id e attributi', $rootMapLayout, 'id="map-ratio"');
has('Container root no-grid mantiene iframe figlio diretto', $rootMapLayout, 'id="map-ratio"><iframe');
same('Container root no-grid non genera row', str_contains($rootMapLayout, 'class="row ') ? 'row' : 'plain', 'plain');

$normalContainer = ResourceFormLayoutRenderer::render(
    (new Form())->components([
        (new Container())->components([
            Iframe::url('https://example.test/default-grid'),
        ]),
    ])
);
has('Container standard mantiene la griglia Resource', $normalContainer, '<div class="col-12"><div class="row g-3"');

$normalRootContainer = ResourceFormLayoutRenderer::renderLayout(
    (new Container())
        ->id('root-grid')
        ->addClass('root-custom')
        ->components([
            Iframe::url('https://example.test/root-grid'),
        ])
);
has('Container root standard mantiene la griglia', $normalRootContainer, '<div class="row g-3 root-custom"');
has('Container root standard conserva gli attributi', $normalRootContainer, 'id="root-grid"');

$infoCard = InfoCard::make('<Camere>', 0)
    ->id('rooms-card')
    ->addClass('shadow-sm')
    ->attr('data-role', 'info')
    ->style('min-height', '8rem')
    ->render('bootstrap');
has('InfoCard fa escape del titolo', $infoCard, '&lt;Camere&gt;');
has('InfoCard conserva lo zero', $infoCard, '<h5 class="card-title mb-0">0</h5>');
has('InfoCard fonde classi e span sulla card', $infoCard, 'class="card border h-100 col-span-1 shadow-sm"');
has('InfoCard conserva id e attributi custom', $infoCard, 'id="rooms-card"');
has('InfoCard conserva data attribute', $infoCard, 'data-role="info"');
has('InfoCard conserva gli style', $infoCard, 'style="min-height: 8rem;"');
same('InfoCard non crea wrapper di span', str_starts_with($infoCard, '<div class="col-span-1"><div') ? 'wrapped' : 'direct', 'direct');

$missingInfo = InfoCard::make('Bagni', null)->render('bootstrap');
has('InfoCard usa il placeholder solo per valori mancanti', $missingInfo, '<h5 class="card-title mb-0">--</h5>');

$infoGrid = ResourceFormLayoutRenderer::renderLayout(
    (new Container())
        ->columns(3)
        ->components([
            InfoCard::make('Locali', 4),
            InfoCard::make('Camere', 2),
            InfoCard::make('Bagni', 1),
        ])
);
same('InfoCard in Resource usa tre colonne native', (string) substr_count($infoGrid, '<div class="col-4">'), '3');
same('InfoCard in Resource non duplica col-span', (string) substr_count($infoGrid, 'col-span-'), '0');

$wideInfoGrid = ResourceFormLayoutRenderer::renderLayout(
    (new Container())
        ->columns(3)
        ->components([
            InfoCard::make('Superficie', 120)->columnSpan(2),
        ])
);
has('InfoCard rispetta columnSpan nel layout Resource', $wideInfoGrid, '<div class="col-8"><div class="card border h-100">');

$metricUp = MetricCard::make('<Fatturato>', 120)
    ->displayValue('EUR <120>')
    ->unit(' EUR')
    ->compareTo(100)
    ->render('bootstrap');
has('MetricCard fa escape di titolo e valore', $metricUp, '&lt;Fatturato&gt;');
has('MetricCard fa escape del display value', $metricUp, 'EUR &lt;120&gt; EUR');
has('MetricCard mostra incremento corretto', $metricUp, 'bi-arrow-up');
has('MetricCard colora incremento positivo', $metricUp, 'text-success');
has('MetricCard mostra percentuale con segno', $metricUp, '+20%');
has('MetricCard espone il precedente nel tooltip', $metricUp, 'data-bs-title="100 EUR"');
has('MetricCard rende il tooltip raggiungibile da tastiera', $metricUp, 'tabindex="0"');

$metricDown = MetricCard::make('Churn', 80)
    ->compareTo(100)
    ->lowerIsBetter()
    ->render('bootstrap');
has('MetricCard mostra decremento corretto', $metricDown, 'bi-arrow-down');
has('MetricCard lowerIsBetter colora il calo positivo', $metricDown, 'text-success');
has('MetricCard mostra decremento con segno', $metricDown, '-20%');

$metricEqual = MetricCard::make('Ordini', 100)
    ->compareTo(100)
    ->render('bootstrap');
has('MetricCard gestisce valori uguali', $metricEqual, 'bi-dash');
has('MetricCard rende zero percento', $metricEqual, '> 0%</h6>');

$metricZeroBaseline = MetricCard::make('Ordini', 10)
    ->compareTo(0)
    ->render('bootstrap');
has('MetricCard evita divisione per zero', $metricZeroBaseline, 'text-body-secondary');
has('MetricCard segnala percentuale non definita', $metricZeroBaseline, '> --</h6>');

$metricSmallDelta = MetricCard::make('Conversione', 105)
    ->compareTo(100)
    ->render('bootstrap');
has('MetricCard mantiene due decimali per delta piccoli', $metricSmallDelta, '+5.00%');

$metricNoComparison = MetricCard::make('Stato', 'n/a')
    ->compareTo(100)
    ->render('bootstrap');
same('MetricCard ignora confronti non numerici', str_contains($metricNoComparison, 'data-bs-toggle="tooltip"') ? 'trend' : 'plain', 'plain');

$metricRoundedToZero = MetricCard::make('Errore', 99.999)
    ->compareTo(100)
    ->render('bootstrap');
has('MetricCard normalizza il delta arrotondato a zero', $metricRoundedToZero, 'bi-dash');
has('MetricCard non mostra meno zero', $metricRoundedToZero, '> 0.00%</h6>');

Theme::set('wonder');
$forcedBootstrapLayout = ResourceFormLayoutRenderer::renderLayout(
    (new Container())->components([
        SectionTitle::make('Backend')->level(5),
        InfoCard::make('Valore', 1),
    ])
);
has('Resource layout forza Bootstrap per i componenti figli', $forcedBootstrapLayout, '>Backend</h5>');
has('Resource layout mantiene InfoCard Bootstrap col tema globale Wonder', $forcedBootstrapLayout, '<div class="card border h-100">');
Theme::set('wonder');

echo "\n" . ($fail === 0 ? "PASS" : "FAIL ($fail)") . "\n";
exit($fail === 0 ? 0 : 1);
