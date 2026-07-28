<?php
/** php tests/Elements/Media/SwiperTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', sys_get_temp_dir());
define('ASSETS_VERSION', '1.0.0');
define('APP_VERSION', '2.1.0');
if (!defined('RESPONSIVE_IMAGE_SIZES')) { define('RESPONSIVE_IMAGE_SIZES', [240,480,620,960,1200,1440,1920,2400]); }
if (!defined('RESPONSIVE_IMAGE_WEBP')) { define('RESPONSIVE_IMAGE_WEBP', true); }

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\Theme;
use Wonder\Elements\Components\Alert;
use Wonder\Elements\Media\Swiper;

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
function countIs(string $label, string $html, string $needle, int $expected): void {
    global $fail;
    $actual = substr_count($html, $needle);
    if ($actual === $expected) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  expected: $expected\n  actual: $actual\n"; }
}
function throwsInvalidArgument(string $label, callable $callback): void {
    global $fail;

    try {
        $callback();
        $fail++;
        echo "FAIL: $label\n  expected InvalidArgumentException\n";
    } catch (InvalidArgumentException) {
        echo "ok: $label\n";
    }
}

// --- Modalità ZOOM + thumbnails
$zoom = Swiper::make([ '/assets/upload/a.jpg' => 'Alpha', '/assets/upload/b.jpg' => 'Beta' ])
    ->id('swiper-zoom')->thumbnails()->zoom()->navigation()->pagination()->size(1440)->thumbsSize(240)
    ->render();

has('contenitore swiper',        $zoom, "id='swiper-zoom'");
has('swiper-wrapper',            $zoom, "swiper-wrapper");
has('slide principale grande',   $zoom, "a-1440.jpg");
has('markup panzoom',            $zoom, "f-panzoom__viewport");
has('strip thumbnails',          $zoom, "id='swiper-zoom-thumbs'");
has('thumbnail piccola',         $zoom, "a-240.jpg");
has('init Swiper principale',    $zoom, "new Swiper('#swiper-zoom'");
has('init Swiper thumbs',        $zoom, "new Swiper('#swiper-zoom-thumbs'");
has('init Panzoom',              $zoom, "new Panzoom");
has('zoom Wonder default 1:1',   $zoom, "f-1-1 f-panzoom__viewport");
has('navigation el',             $zoom, "swiper-button-next");
has('pagination el',             $zoom, "swiper-pagination");
hasnt('zoom non apre lightbox',  $zoom, "Fancybox.bind");

// --- Modalità LIGHTBOX (esclusiva con zoom)
$light = Swiper::make([ '/assets/upload/a.jpg' => 'Alpha' ])
    ->id('swiper-light')->thumbnails()->lightbox('galleria')->loop()->download()->fullSize(2400)
    ->render();

has('gruppo fancybox nascosto',  $light, "data-fancybox=\"galleria\"");
has('lightbox size grande',      $light, "/assets/upload/a-2400.jpg");
has('slide trigger',             $light, "data-fancybox-trigger='galleria'");
has('init Fancybox.bind',        $light, "Fancybox.bind('[data-fancybox=\"galleria\"]'");
has('download buttons',          $light, "buttons: ['download', 'thumbs', 'close']");
has('loop attivo',               $light, "loop: true");
hasnt('lightbox non usa panzoom', $light, "f-panzoom__viewport");

// --- Esclusività: lightbox() dopo zoom() vince
$excl = Swiper::make([ '/assets/upload/a.jpg' => 'A' ])->id('x')->zoom()->lightbox('g')->render();
hasnt('zoom disattivato da lightbox', $excl, "f-panzoom__viewport");
has('lightbox attivo',            $excl, "data-fancybox-trigger='g'");

// --- Ratio immagini/thumbs e classi slide
foreach (['wonder', 'bootstrap'] as $theme) {
    $styled = Swiper::make([
            '/assets/upload/a.jpg' => 'A',
            '/assets/upload/b.jpg' => 'B',
        ])
        ->id("swiper-styled-$theme")
        ->thumbnails()
        ->slidesPerView(2)
        ->ratio('3:2')
        ->thumbsRatio('1:1')
        ->slideClass(['swiper-slide', 'featured featured', "quote'"])
        ->thumbSlideClass('thumb-card thumb-card')
        ->render($theme);

    countIs("$theme ratio su ogni immagine principale", $styled, 'style="aspect-ratio: 3 / 2;"', 2);
    countIs("$theme ratio su ogni thumbnail", $styled, 'style="aspect-ratio: 1 / 1;"', 2);
    countIs("$theme classe custom su ogni slide", $styled, 'featured', 2);
    countIs("$theme classe slide escapata", $styled, 'quote&#039;', 2);
    countIs("$theme classe custom separata sui thumbs", $styled, 'thumb-card', 2);
    hasnt("$theme classe strutturale non duplicata", $styled, 'swiper-slide swiper-slide');
    hasnt("$theme classe custom non duplicata", $styled, 'featured featured');
    hasnt("$theme classe thumb non duplicata", $styled, 'thumb-card thumb-card');

    if ($theme === 'bootstrap') {
        hasnt('Bootstrap ratio esplicito rimuove il ratio root legacy', $styled, 'ratio-16x9');
        hasnt('Bootstrap ratio esplicito usa wrapper naturale', $styled, 'position-absolute top-0 swiper-wrapper');
    }
}

foreach (['wonder' => 'f-1-1', 'bootstrap' => 'ratio-1x1'] as $theme => $defaultRatioClass) {
    $zoomRatio = Swiper::make(['/assets/upload/a.jpg' => 'A'])
        ->id("swiper-zoom-ratio-$theme")
        ->zoom()
        ->ratio('4x3')
        ->slideClass('zoom-slide')
        ->render($theme);

    has("$theme zoom usa ratio esplicito", $zoomRatio, 'style="aspect-ratio: 4 / 3;"');
    has("$theme zoom conserva classi slide", $zoomRatio, 'zoom-slide');
    hasnt("$theme zoom rimuove ratio default", $zoomRatio, $defaultRatioClass);
}

foreach (['wonder', 'bootstrap'] as $theme) {
    $lightRatio = Swiper::make(['/assets/upload/a.jpg' => 'A'])
        ->id("swiper-light-ratio-$theme")
        ->lightbox('ratio-group')
        ->ratio('16/9')
        ->slideClass('gallery-slide')
        ->render($theme);

    has("$theme lightbox conserva classe slide", $lightRatio, 'gallery-slide');
    has("$theme lightbox conserva trigger", $lightRatio, 'ratio-group');
    has("$theme lightbox usa ratio esplicito", $lightRatio, 'style="aspect-ratio: 16 / 9;"');
}

$bootstrapLegacy = Swiper::make(['/assets/upload/a.jpg' => 'A'])
    ->id('swiper-bootstrap-legacy')
    ->render('bootstrap');
has('Bootstrap senza ratio conserva root 16:9', $bootstrapLegacy, 'ratio ratio-16x9 img-thumbnail rounded');
has('Bootstrap senza ratio conserva wrapper assoluto', $bootstrapLegacy, 'position-absolute top-0 swiper-wrapper');

$bootstrapZoomLegacy = Swiper::make(['/assets/upload/a.jpg' => 'A'])
    ->id('swiper-bootstrap-zoom-legacy')
    ->zoom()
    ->render('bootstrap');
has('Bootstrap zoom default resta 1:1', $bootstrapZoomLegacy, 'ratio ratio-1x1');

// --- Modalità CONTENUTI HTML / Component
foreach (['wonder' => 'bootstrap', 'bootstrap' => 'wonder'] as $theme => $oppositeTheme) {
    Theme::set($oppositeTheme);

    $content = Swiper::make(['/assets/upload/a.jpg' => 'A'])
        ->id("swiper-content-$theme")
        ->slides([
            "<article data-slide='raw'><strong>HTML trusted</strong></article>",
            Alert::make('Messaggio dal componente')->dismissible(false),
        ])
        ->thumbnails()
        ->zoom()
        ->lightbox('ignored')
        ->download()
        ->size(240)
        ->fullSize(2400)
        ->slidesPerView(1.05)
        ->spaceBetween(16)
        ->breakpoints([
            993 => ['slidesPerView' => 3, 'spaceBetween' => 20],
            769 => ['slidesPerView' => 2, 'spaceBetween' => 20],
        ])
        ->autoHeight()
        ->keyboard()
        ->watchOverflow()
        ->navigation()
        ->pagination()
        ->addClass('mt-6')
        ->attr('aria-label', 'Contenuti < recenti')
        ->render($theme);

    has("$theme HTML trusted non escapato", $content, "<article data-slide='raw'><strong>HTML trusted</strong></article>");
    countIs("$theme un wrapper per contenuto", $content, "class='swiper-slide'", 2);
    has("$theme classi root fluenti", $content, "class='swiper w-100 mt-6'");
    has("$theme attributi root escapati", $content, 'aria-label="Contenuti &lt; recenti"');
    has("$theme auto height", $content, 'autoHeight: true');
    has("$theme tastiera", $content, 'keyboard: { enabled: true }');
    has("$theme watch overflow", $content, 'watchOverflow: true');
    has("$theme breakpoint 769", $content, '"769":{"slidesPerView":2,"spaceBetween":20}');
    has("$theme breakpoint 993", $content, '"993":{"slidesPerView":3,"spaceBetween":20}');
    has("$theme navigation", $content, 'swiper-button-next');
    has("$theme pagination", $content, 'swiper-pagination');
    hasnt("$theme niente sorgente immagine", $content, 'a-240.jpg');
    hasnt("$theme niente thumbnails", $content, "-thumbs'");
    hasnt("$theme niente Panzoom", $content, 'f-panzoom');
    hasnt("$theme niente Fancybox", $content, 'Fancybox');

    if ($theme === 'wonder') {
        has('Component renderizzato esplicitamente Wonder', $content, 'wi-alert');
        hasnt('Component Wonder non usa Bootstrap', $content, "class='toast");
    } else {
        has('Component renderizzato esplicitamente Bootstrap', $content, "class='toast");
        hasnt('Component Bootstrap non usa Wonder', $content, 'wi-alert');
        hasnt('Bootstrap content mode senza ratio immagini', $content, 'ratio-16x9');
        hasnt('Bootstrap content mode senza wrapper assoluto', $content, 'position-absolute');
    }
}

foreach (['wonder', 'bootstrap'] as $theme) {
    $contentStyled = Swiper::make()
        ->id("swiper-content-styled-$theme")
        ->slides(['Uno', 'Due'])
        ->ratio('2:1')
        ->thumbsRatio('1:1')
        ->slideClass('content-slide')
        ->thumbSlideClass('ignored-thumb-class')
        ->thumbnails()
        ->render($theme);

    countIs("$theme content mode applica slideClass", $contentStyled, 'content-slide', 2);
    hasnt("$theme content mode ignora ratio immagini", $contentStyled, 'aspect-ratio');
    hasnt("$theme content mode ignora classi thumbs", $contentStyled, 'ignored-thumb-class');
    hasnt("$theme content mode ignora thumbnails", $contentStyled, "-thumbs'");
}

Theme::set('wonder');

$slidesLast = Swiper::make(['/assets/upload/a.jpg' => 'A'])
    ->id('slides-last')
    ->slides(["<article id='last-html'>Contenuto</article>"])
    ->render();
has('slides() dopo images() rende HTML', $slidesLast, "id='last-html'");
hasnt('slides() dopo images() azzera immagini', $slidesLast, 'a-1440.jpg');

$imagesLast = Swiper::make()
    ->slides(["<article id='first-html'>Contenuto</article>"])
    ->images(['/assets/upload/a.jpg' => 'A'])
    ->id('images-last')
    ->render();
has('images() dopo slides() rende immagini', $imagesLast, 'a-1440.jpg');
hasnt('images() dopo slides() azzera HTML', $imagesLast, "id='first-html'");

$watchOverflowOff = Swiper::make()
    ->slides(['Contenuto'])
    ->watchOverflow(false)
    ->render();
has('watchOverflow puo essere disattivato', $watchOverflowOff, 'watchOverflow: false');

foreach (['16:9', '16x9', '16/9', '16-9', ' 16 : 9 '] as $ratioVariant) {
    $normalizedRatio = Swiper::make(['/assets/upload/a.jpg' => 'A'])
        ->ratio($ratioVariant)
        ->render('wonder');
    has("ratio accetta {$ratioVariant}", $normalizedRatio, 'style="aspect-ratio: 16 / 9;"');
}

foreach (['0:1', '1:0', 'foo', '16:9; color:red'] as $invalidRatio) {
    throwsInvalidArgument(
        "ratio rifiuta {$invalidRatio}",
        static fn () => Swiper::make()->ratio($invalidRatio)
    );
    throwsInvalidArgument(
        "thumbsRatio rifiuta {$invalidRatio}",
        static fn () => Swiper::make()->thumbsRatio($invalidRatio)
    );
}

throwsInvalidArgument('breakpoint deve essere positivo', static fn () => Swiper::make()->breakpoints([
    0 => ['slidesPerView' => 1],
]));
throwsInvalidArgument('slidesPerView breakpoint deve essere positivo', static fn () => Swiper::make()->breakpoints([
    769 => ['slidesPerView' => 0],
]));
throwsInvalidArgument('spaceBetween breakpoint non puo essere negativo', static fn () => Swiper::make()->breakpoints([
    769 => ['spaceBetween' => -1],
]));

// Main slider, thumbnails e script restano nello stesso wrapper opt-in.
foreach (['wonder' => 'col-6', 'bootstrap' => 'col-span-6'] as $theme => $spanClass) {
    $prefix = '<div class="' . $spanClass . '">';
    $factory = static fn () => Swiper::make(['/assets/upload/a.jpg' => 'A'])
        ->id("swiper-span-$theme")
        ->thumbnails();

    $plain = $factory()->render($theme);
    $wrapped = $factory()->columnSpan(6)->render($theme);

    hasnt("$theme senza wrapper implicito", $plain, $prefix);
    has("$theme wrapper esplicito", $wrapped, $prefix);

    $inner = str_starts_with($wrapped, $prefix) && str_ends_with($wrapped, '</div>')
        ? substr($wrapped, strlen($prefix), -strlen('</div>'))
        : $wrapped;

    if ($inner === $plain) { echo "ok: $theme wrapper contiene tutto\n"; }
    else { $fail++; echo "FAIL: $theme wrapper contiene tutto\n"; }
}

echo "\n" . ($fail === 0 ? "PASS" : "FAIL ($fail)") . "\n";
exit($fail === 0 ? 0 : 1);
