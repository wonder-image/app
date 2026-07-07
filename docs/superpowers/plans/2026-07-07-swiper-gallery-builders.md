# Swiper & Gallery — builder fluenti — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere due componenti frontend come builder fluenti — `__swiper($images)` (carosello con thumbnails + zoom Panzoom XOR lightbox Fancybox) e `__gallery($images)` (griglia responsive con lightbox) — che rimpiazza la funzione legacy `responsiveGallery()`.

**Architecture:** Stesso pattern di `Image`/`__ri()`: un **Element builder** (`class/Elements/Media/*`) che usa il trait `Renderer` e delega a un **renderer di tema** (`class/Themes/Wonder/Media/*`) risolto dal `Resolver` (`Wonder\Elements\Media\X` → `Wonder\Themes\Wonder\Media\X`). Le immagini di ogni slide/card passano dal builder `Wonder\Elements\Media\Image` (webp + srcset + skeleton), condiviso via un trait `HandlesMedia`.

**Tech Stack:** PHP 8.2+, Composer PSR-4 (`Wonder\` → `class/`), Swiper.js, Fancybox + Panzoom (bundle lib `fancyapps`), `wonder-image/lib` (utility CSS). Test: runner PHP standalone (no PHPUnit).

## Global Constraints

- PHP `^8.2` (composer platform 8.2.30).
- **Nessun auto-load dipendenze**: i componenti assumono che la pagina abbia dichiarato in testa `Wonder\App\Dependencies::swiper()::fancyapps();`. Non modificare `Dependencies.php`.
- **Nessuna nuova classe `.wi-*`** e nessun `<style>` con selettori nuovi: comporre solo utility esistenti di `wonder-image/lib` + classi standard swiper/fancybox/panzoom; l'istanza è targettizzata via `id`.
- Immagini sempre via il builder `Wonder\Elements\Media\Image` (mix con la lib): `->sizes(RESPONSIVE_IMAGE_SIZES)->hasWebP()`.
- `->zoom()` e `->lightbox()` sono **mutuamente esclusivi**.
- Input canonico immagini: array associativo `['src.jpg' => 'alt', ...]`; tollerare liste numeriche (`alt = ''`).
- Size di default da `RESPONSIVE_IMAGE_SIZES = [240,480,620,960,1200,1440,1920,2400]`: anteprima piccola, lightbox = max.
- Test: runner standalone eseguibile con `php tests/.../XTest.php`, `require vendor/autoload.php`, exit code 0/1.
- Commit message: chiudere con `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`.
- Branch di lavoro già creato: `feat/swiper-gallery-builders`.

---

### Task 1: Gallery (builder + renderer + trait condiviso) e rimozione `responsiveGallery()`

**Files:**
- Create: `class/Themes/Wonder/Media/Concerns/HandlesMedia.php`
- Create: `class/Elements/Media/Gallery.php`
- Create: `class/Themes/Wonder/Media/Gallery.php`
- Modify: `app/function/helper.php` (aggiungere `__gallery()`, dopo `__ri()` ~riga 156)
- Delete: `app/function/frontend/plugin/gallery.php`
- Modify: `app/function/frontend/plugin/function.php` (rimuovere il `require_once` di gallery.php, riga 4)
- Test: `tests/Elements/Media/GalleryTest.php`

**Interfaces:**
- Produces (usati dal Task 2):
  - Trait `Wonder\Themes\Wonder\Media\Concerns\HandlesMedia` con metodi protetti:
    - `normalizeImages(array $images): array` → lista di `['src'=>string,'alt'=>string,'position'=>int]`
    - `renderImage(string $src, string $alt, int $size, string $fit = 'cover', bool $draggable = false): string` (`$fit` ∈ `cover|contain|natural`)
    - `imageUrl(string $src, int $size): string`
    - `mediaId(mixed $class, string $prefix): string`
  - `Wonder\Elements\Media\Gallery::make(array $images = []): self`
  - helper globale `__gallery(array $images = []): \Wonder\Elements\Media\Gallery`

- [ ] **Step 1: Scrivere il test che fallisce**

Create `tests/Elements/Media/GalleryTest.php`:

```php
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
```

- [ ] **Step 2: Eseguire il test per verificarne il fallimento**

Run: `php tests/Elements/Media/GalleryTest.php`
Expected: FAIL — `Error: Class "Wonder\Elements\Media\Gallery" not found` (o simile).

- [ ] **Step 3: Creare il trait condiviso `HandlesMedia`**

Create `class/Themes/Wonder/Media/Concerns/HandlesMedia.php`:

```php
<?php

    namespace Wonder\Themes\Wonder\Media\Concerns;

    use Wonder\Elements\Media\Image;

    trait HandlesMedia
    {
        /**
         * Normalizza l'input immagini nella forma canonica.
         * ['src.jpg' => 'alt', ...]  oppure lista numerica ['a.jpg', ...] (alt = '').
         *
         * @return array<int, array{src:string, alt:string, position:int}>
         */
        protected function normalizeImages(array $images): array
        {
            $items = [];
            $position = 0;

            foreach ($images as $key => $value) {
                if (is_int($key)) {
                    $src = (string) $value;
                    $alt = '';
                } else {
                    $src = (string) $key;
                    $alt = (string) $value;
                }

                if ($src === '') { continue; }

                $items[] = [ 'src' => $src, 'alt' => $alt, 'position' => $position ];
                $position++;
            }

            return $items;
        }

        /**
         * Renderizza un'immagine via il builder Image (webp + srcset + skeleton).
         * $fit: 'cover' | 'contain' | 'natural'.
         */
        protected function renderImage(string $src, string $alt, int $size, string $fit = 'cover', bool $draggable = false): string
        {
            $img = Image::src($src)
                ->sizes(RESPONSIVE_IMAGE_SIZES)
                ->hasWebP()
                ->alt($alt)
                ->size($size)
                ->skeleton()
                ->loading();

            $draggable ? $img->notDraggable(false) : $img->notDraggable();

            if ($fit === 'contain') {
                $img->fitContain();
            } elseif ($fit === 'cover') {
                $img->fitCover();
            } else {
                $img->addClass('w-100');
            }

            return $img->render();
        }

        /** URL della variante alla size richiesta (per il data-src del lightbox). */
        protected function imageUrl(string $src, int $size): string
        {
            return Image::src($src)->size($size)->url();
        }

        /** Id esplicito (->id()) se presente, altrimenti generato. */
        protected function mediaId(mixed $class, string $prefix): string
        {
            $id = $class->getSchema('id');

            return (is_string($id) && $id !== '') ? $id : $prefix . '-' . bin2hex(random_bytes(4));
        }
    }
```

- [ ] **Step 4: Creare l'element builder `Gallery`**

Create `class/Elements/Media/Gallery.php`:

```php
<?php

    namespace Wonder\Elements\Media;

    use Wonder\Elements\Component;
    use Wonder\Elements\Concerns\{ Renderer };

    class Gallery extends Component {

        use Renderer;

        public function __construct( array $images = [] )
        {
            $this->schema('images', $images);
        }

        public static function make( array $images = [] ): self
        {
            return new self($images);
        }

        public function images( array $images ): self
        {
            return $this->schema('images', $images);
        }

        public function columns( int $desktop = 4, int $tablet = 3, int $mobile = 2 ): self
        {
            return $this->schema('columns', [ 'desktop' => $desktop, 'tablet' => $tablet, 'mobile' => $mobile ]);
        }

        public function gap( int|array $gap = 6 ): self
        {
            return $this->schema('gap', $gap);
        }

        public function format( string $format = 'h-fit' ): self
        {
            return $this->schema('format', $format);
        }

        public function download( bool $on = true ): self
        {
            return $this->schema('download', $on);
        }

        public function size( int $px ): self
        {
            return $this->schema('preview-size', $px);
        }

        public function fullSize( int $px ): self
        {
            return $this->schema('full-size', $px);
        }

    }
```

- [ ] **Step 5: Creare il renderer di tema `Gallery`**

Create `class/Themes/Wonder/Media/Gallery.php`:

```php
<?php

    namespace Wonder\Themes\Wonder\Media;

    use Wonder\Themes\Wonder\Component;
    use Wonder\Themes\Wonder\Media\Concerns\HandlesMedia;

    class Gallery extends Component {

        use HandlesMedia;

        public function render($class): string
        {
            $id          = $this->mediaId($class, 'gallery');
            $items       = $this->normalizeImages($class->getSchema('images') ?? []);
            $columns     = $class->getSchema('columns') ?? [ 'desktop' => 4, 'tablet' => 3, 'mobile' => 2 ];
            $gap         = $class->getSchema('gap') ?? 6;
            $format      = $class->getSchema('format') ?? 'h-fit';
            $download    = (bool) ($class->getSchema('download') ?? false);
            $previewSize = (int) ($class->getSchema('preview-size') ?? 480);
            $fullSize    = (int) ($class->getSchema('full-size') ?? max(RESPONSIVE_IMAGE_SIZES));

            $colDesktop = (int) $columns['desktop'];
            $colTablet  = (int) $columns['tablet'];
            $colMobile  = (int) $columns['mobile'];

            if (is_array($gap)) {
                $gapDesktop = $gap['desktop'] ?? 6;
                $gapTablet  = $gap['tablet'] ?? $gapDesktop;
                $gapMobile  = $gap['mobile'] ?? $gapTablet;
            } else {
                $gapDesktop = $gapTablet = $gapMobile = $gap;
            }

            // Bucket round-robin per device
            $buckets = [ 'desktop' => [], 'tablet' => [], 'mobile' => [] ];
            for ($i = 0; $i < $colDesktop; $i++) { $buckets['desktop'][$i] = []; }
            for ($i = 0; $i < $colTablet;  $i++) { $buckets['tablet'][$i]  = []; }
            for ($i = 0; $i < $colMobile;  $i++) { $buckets['mobile'][$i]  = []; }

            $d = $t = $m = 0;
            $hidden = "";

            foreach ($items as $item) {
                $buckets['desktop'][$d][] = $item;
                $buckets['tablet'][$t][]  = $item;
                $buckets['mobile'][$m][]  = $item;

                $d = ($d + 1) % $colDesktop;
                $t = ($t + 1) % $colTablet;
                $m = ($m + 1) % $colMobile;

                $full    = $this->imageUrl($item['src'], $fullSize);
                $caption = $item['alt'] !== '' ? " data-caption=\"" . $this->escape($item['alt']) . "\"" : '';
                $hidden .= "<a data-fancybox=\"$id\" data-src=\"$full\"$caption></a>";
            }

            $html  = "<div class='d-none'>$hidden</div>";
            $html .= "<div class='w-100 d-grid col-$colDesktop col-t-$colTablet col-p-$colMobile gap-$gapDesktop gap-t-$gapTablet gap-p-$gapMobile'>";
            $html .= $this->deviceColumns($id, $buckets['desktop'], $colDesktop, $gapDesktop, $format, $previewSize, 'tablet-none', false);
            $html .= $this->deviceColumns($id, $buckets['tablet'],  $colTablet,  $gapTablet,  $format, $previewSize, 'pc-none phone-none', false);
            $html .= $this->deviceColumns($id, $buckets['mobile'],  $colMobile,  $gapMobile,  $format, $previewSize, 'pc-none tablet-none', true);
            $html .= "</div>";

            $options = '{';
            if ($download) { $options .= "buttons: ['download', 'thumbs', 'close']"; }
            $options .= '}';

            $html .= "<script>Fancybox.bind('[data-fancybox=\"$id\"]', $options);</script>";

            return $html;
        }

        private function deviceColumns(string $id, array $buckets, int $cols, $gap, string $format, int $previewSize, string $visibility, bool $extraHFit): string
        {
            $html  = "";
            $extra = $extraHFit ? ' h-fit' : '';

            for ($i = 0; $i < $cols; $i++) {
                $html .= "<div class='w-100 $visibility$extra'><div class='w-100 d-grid col-1 gap-$gap'>";
                foreach ($buckets[$i] as $item) { $html .= $this->card($id, $item, $format, $previewSize); }
                $html .= "</div></div>";
            }

            return $html;
        }

        private function card(string $id, array $item, string $format, int $previewSize): string
        {
            if ($format === 'h-fit') {
                $img = $this->renderImage($item['src'], $item['alt'], $previewSize, 'natural');
                return "<a href='javascript:;' data-fancybox-trigger='$id' data-fancybox-index='{$item['position']}' class='col-1 h-fit'>$img</a>";
            }

            $img = $this->renderImage($item['src'], $item['alt'], $previewSize, 'cover');
            return "<a href='javascript:;' data-fancybox-trigger='$id' data-fancybox-index='{$item['position']}' class='col-1'><div class='f-$format o-hidden'>$img</div></a>";
        }

    }
```

- [ ] **Step 6: Aggiungere l'helper `__gallery()`**

In `app/function/helper.php`, subito dopo la funzione `__ri()` (chiusura `}` ~riga 156), aggiungere:

```php
    // Gallery responsive con lightbox Fancybox
    function __gallery(array $images = [])
    {

        return \Wonder\Elements\Media\Gallery::make($images);

    }
```

- [ ] **Step 7: Rigenerare l'autoload ed eseguire il test (verde)**

Run:
```bash
composer dumpautoload -q
php tests/Elements/Media/GalleryTest.php
```
Expected: tutte le righe `ok:` e in fondo `PASS` (exit 0).

- [ ] **Step 8: Rimuovere `responsiveGallery()` legacy**

Delete `app/function/frontend/plugin/gallery.php`.

In `app/function/frontend/plugin/function.php` rimuovere la riga:
```php
    require_once __DIR__."/gallery.php";
```

- [ ] **Step 9: Verificare che non resti nessun chiamante interno e che il lint passi**

Run:
```bash
grep -rn "responsiveGallery\|cardResponsiveGallery" . --include="*.php" | grep -v "/vendor/" | grep -v "/docs/"
php -l app/function/frontend/plugin/function.php
php -l class/Elements/Media/Gallery.php
php -l class/Themes/Wonder/Media/Gallery.php
php -l class/Themes/Wonder/Media/Concerns/HandlesMedia.php
php -l app/function/helper.php
```
Expected: il `grep` non stampa nulla; ogni `php -l` stampa `No syntax errors detected`.

- [ ] **Step 10: Commit**

```bash
git add class/Elements/Media/Gallery.php class/Themes/Wonder/Media/Gallery.php \
        class/Themes/Wonder/Media/Concerns/HandlesMedia.php app/function/helper.php \
        app/function/frontend/plugin/function.php tests/Elements/Media/GalleryTest.php
git rm app/function/frontend/plugin/gallery.php
git commit -m "feat(media): __gallery() builder fluente; rimuove responsiveGallery legacy

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Swiper (builder + renderer)

**Files:**
- Create: `class/Elements/Media/Swiper.php`
- Create: `class/Themes/Wonder/Media/Swiper.php`
- Modify: `app/function/helper.php` (aggiungere `__swiper()` dopo `__gallery()`)
- Test: `tests/Elements/Media/SwiperTest.php`

**Interfaces:**
- Consumes: trait `Wonder\Themes\Wonder\Media\Concerns\HandlesMedia` (Task 1), builder `Wonder\Elements\Media\Image`.
- Produces:
  - `Wonder\Elements\Media\Swiper::make(array $images = []): self`
  - helper globale `__swiper(array $images = []): \Wonder\Elements\Media\Swiper`

- [ ] **Step 1: Scrivere il test che fallisce**

Create `tests/Elements/Media/SwiperTest.php`:

```php
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

echo "\n" . ($fail === 0 ? "PASS" : "FAIL ($fail)") . "\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Eseguire il test per verificarne il fallimento**

Run: `php tests/Elements/Media/SwiperTest.php`
Expected: FAIL — `Error: Class "Wonder\Elements\Media\Swiper" not found`.

- [ ] **Step 3: Creare l'element builder `Swiper`**

Create `class/Elements/Media/Swiper.php`:

```php
<?php

    namespace Wonder\Elements\Media;

    use Wonder\Elements\Component;
    use Wonder\Elements\Concerns\{ Renderer };

    class Swiper extends Component {

        use Renderer;

        public function __construct( array $images = [] )
        {
            $this->schema('images', $images);
        }

        public static function make( array $images = [] ): self
        {
            return new self($images);
        }

        public function images( array $images ): self
        {
            return $this->schema('images', $images);
        }

        public function thumbnails( bool $on = true ): self
        {
            return $this->schema('thumbnails', $on);
        }

        public function zoom( bool $on = true ): self
        {
            if ($on) { $this->schema('lightbox', false); }
            return $this->schema('zoom', $on);
        }

        public function lightbox( ?string $group = null ): self
        {
            $this->schema('zoom', false);
            if ($group !== null) { $this->schema('lightbox-group', $group); }
            return $this->schema('lightbox', true);
        }

        public function loop( bool $on = true ): self          { return $this->schema('loop', $on); }
        public function autoplay( int $delayMs ): self         { return $this->schema('autoplay', $delayMs); }
        public function navigation( bool $on = true ): self    { return $this->schema('navigation', $on); }
        public function pagination( bool $on = true ): self    { return $this->schema('pagination', $on); }
        public function slidesPerView( int|float $n ): self    { return $this->schema('slides-per-view', $n); }
        public function spaceBetween( int $px ): self          { return $this->schema('space-between', $px); }
        public function thumbsPerView( int $n ): self          { return $this->schema('thumbs-per-view', $n); }
        public function download( bool $on = true ): self      { return $this->schema('download', $on); }
        public function size( int $px ): self                  { return $this->schema('size', $px); }
        public function thumbsSize( int $px ): self            { return $this->schema('thumbs-size', $px); }
        public function fullSize( int $px ): self              { return $this->schema('full-size', $px); }

        public function fitCover( bool $on = true ): self
        {
            if ($on) { $this->schema('fit-contain', false); }
            return $this->schema('fit-cover', $on);
        }

        public function fitContain( bool $on = true ): self
        {
            if ($on) { $this->schema('fit-cover', false); }
            return $this->schema('fit-contain', $on);
        }

    }
```

- [ ] **Step 4: Creare il renderer di tema `Swiper`**

Create `class/Themes/Wonder/Media/Swiper.php`:

```php
<?php

    namespace Wonder\Themes\Wonder\Media;

    use Wonder\Themes\Wonder\Component;
    use Wonder\Themes\Wonder\Media\Concerns\HandlesMedia;

    class Swiper extends Component {

        use HandlesMedia;

        public function render($class): string
        {
            $id       = $this->mediaId($class, 'swiper');
            $items    = $this->normalizeImages($class->getSchema('images') ?? []);

            $thumbs   = (bool) ($class->getSchema('thumbnails') ?? false);
            $zoom     = (bool) ($class->getSchema('zoom') ?? false);
            $lightbox = (bool) ($class->getSchema('lightbox') ?? false);
            $group    = $class->getSchema('lightbox-group') ?? ($id . '-lightbox');

            $size      = (int) ($class->getSchema('size') ?? 1440);
            $thumbSize = (int) ($class->getSchema('thumbs-size') ?? 240);
            $fullSize  = (int) ($class->getSchema('full-size') ?? max(RESPONSIVE_IMAGE_SIZES));
            $fit       = ($class->getSchema('fit-contain') ?? false) ? 'contain' : 'cover';

            $slides = "";
            $thumbSlides = "";
            $hidden = "";
            $i = 0;

            foreach ($items as $item) {

                if ($zoom) {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit, true);
                    $slides .= "<div class='swiper-slide w-100'><div class='f-panzoom w-100'><div class='f-1-1 f-panzoom__viewport w-100 o-hidden'><div class='f-panzoom__content p-a top start w-100 h-100'>$img</div></div></div></div>";
                } elseif ($lightbox) {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit);
                    $slides .= "<a class='swiper-slide o-hidden' data-fancybox-trigger='$group' data-fancybox-index='$i'>$img</a>";
                    $full    = $this->imageUrl($item['src'], $fullSize);
                    $caption = $item['alt'] !== '' ? " data-caption=\"" . $this->escape($item['alt']) . "\"" : '';
                    $hidden .= "<a data-fancybox=\"$group\" data-src=\"$full\"$caption></a>";
                } else {
                    $img = $this->renderImage($item['src'], $item['alt'], $size, $fit);
                    $slides .= "<div class='swiper-slide o-hidden'>$img</div>";
                }

                if ($thumbs) {
                    $thumbImg = $this->renderImage($item['src'], $item['alt'], $thumbSize, 'cover');
                    $thumbSlides .= "<div class='swiper-slide o-hidden'>$thumbImg</div>";
                }

                $i++;
            }

            $html = "";

            if ($lightbox && $hidden !== '') { $html .= "<div class='d-none'>$hidden</div>"; }

            $html .= "<div id='$id' class='swiper w-100'><div class='swiper-wrapper'>$slides</div>";
            if ($class->getSchema('pagination')) { $html .= "<div class='swiper-pagination'></div>"; }
            if ($class->getSchema('navigation')) { $html .= "<div class='swiper-button-next'></div><div class='swiper-button-prev'></div>"; }
            $html .= "</div>";

            if ($thumbs) { $html .= "<div id='$id-thumbs' class='swiper w-100 o-hidden mt-2'><div class='swiper-wrapper'>$thumbSlides</div></div>"; }

            $html .= $this->script($class, $id, $group, $thumbs, $zoom, $lightbox);

            return $html;
        }

        private function script($class, string $id, string $group, bool $thumbs, bool $zoom, bool $lightbox): string
        {
            $lines = [ "window.addEventListener('loaded', function () {" ];

            if ($thumbs) {
                $tpv = (int) ($class->getSchema('thumbs-per-view') ?? 4);
                $lines[] = "  var thumbs = new Swiper('#$id-thumbs', { spaceBetween: 8, slidesPerView: $tpv, freeMode: true, watchSlidesProgress: true });";
            }

            $opts = [ 'grabCursor: true', 'watchSlidesProgress: true' ];
            $opts[] = 'slidesPerView: ' . ($class->getSchema('slides-per-view') ?? 1);
            $opts[] = 'spaceBetween: ' . (int) ($class->getSchema('space-between') ?? 0);
            if ($class->getSchema('loop'))       { $opts[] = 'loop: true'; }
            if ($class->getSchema('autoplay'))   { $opts[] = 'autoplay: { delay: ' . (int) $class->getSchema('autoplay') . ' }'; }
            if ($class->getSchema('pagination')) { $opts[] = "pagination: { el: '#$id .swiper-pagination', clickable: true }"; }
            if ($class->getSchema('navigation')) { $opts[] = "navigation: { nextEl: '#$id .swiper-button-next', prevEl: '#$id .swiper-button-prev' }"; }
            if ($thumbs)                         { $opts[] = 'thumbs: { swiper: thumbs }'; }

            $lines[] = "  var main = new Swiper('#$id', { " . implode(', ', $opts) . " });";

            if ($zoom) {
                $lines[] = "  document.querySelectorAll('#$id .f-panzoom').forEach(function (el) { new Panzoom(el, { click: 'toggleZoom', dblClick: 'toggleMax', panMode: 'mousemove' }); });";
            }

            if ($lightbox) {
                $options = '{';
                if ($class->getSchema('download')) { $options .= "buttons: ['download', 'thumbs', 'close']"; }
                $options .= '}';
                $lines[] = "  Fancybox.bind('[data-fancybox=\"$group\"]', $options);";
            }

            $lines[] = "});";

            return "<script>\n" . implode("\n", $lines) . "\n</script>";
        }

    }
```

- [ ] **Step 5: Aggiungere l'helper `__swiper()`**

In `app/function/helper.php`, subito dopo la funzione `__gallery()` aggiunta nel Task 1:

```php
    // Swiper: carosello con thumbnails + zoom (Panzoom) o lightbox (Fancybox)
    function __swiper(array $images = [])
    {

        return \Wonder\Elements\Media\Swiper::make($images);

    }
```

- [ ] **Step 6: Rigenerare autoload ed eseguire il test (verde)**

Run:
```bash
composer dumpautoload -q
php tests/Elements/Media/SwiperTest.php
```
Expected: tutte `ok:` e `PASS` (exit 0).

- [ ] **Step 7: Lint**

Run:
```bash
php -l class/Elements/Media/Swiper.php
php -l class/Themes/Wonder/Media/Swiper.php
php -l app/function/helper.php
```
Expected: `No syntax errors detected` per ognuno.

- [ ] **Step 8: Commit**

```bash
git add class/Elements/Media/Swiper.php class/Themes/Wonder/Media/Swiper.php \
        app/function/helper.php tests/Elements/Media/SwiperTest.php
git commit -m "feat(media): __swiper() builder con thumbnails, zoom Panzoom e lightbox Fancybox

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Documentazione

**Files:**
- Create: `docs/app/concetti/componenti/swiper-e-gallery.md`
- Modify: `docs/app/SUMMARY.md:41` (aggiungere voce sotto "Componenti UI")

**Interfaces:**
- Consumes: API pubbliche `__swiper()` / `__gallery()` (Task 1-2).
- Produces: nessuna interfaccia di codice.

- [ ] **Step 1: Scrivere la pagina di documentazione**

Create `docs/app/concetti/componenti/swiper-e-gallery.md`:

````markdown
# Swiper e Gallery

Due componenti frontend per mostrare immagini, costruiti come **builder fluenti** (stesso
pattern di `__ri()`): ogni immagine passa dal builder `Image`, quindi eredita **WebP + srcset +
skeleton** in automatico.

- `__swiper($images)` — carosello con miniature opzionali e, al click, **zoom in-place** (Panzoom)
  **oppure** apertura della **gallery a schermo intero** (Fancybox). Le due modalità sono esclusive.
- `__gallery($images)` — griglia responsive di immagini con lightbox Fancybox. Sostituisce la
  vecchia funzione `responsiveGallery()`.

## Dipendenze (obbligatorie, in testa alla pagina)

I componenti **non** caricano da soli le librerie: dichiarale prima dell'HTML, così finiscono nel
`<head>`.

```php
Wonder\App\Dependencies::swiper();                 // solo swipe / thumbnails
Wonder\App\Dependencies::swiper()::fancyapps();    // + zoom o lightbox (Panzoom/Fancybox)
```

| Cosa usi | Dipendenze |
|---|---|
| swipe / `->thumbnails()` | `swiper` |
| `->zoom()` | `swiper` + `fancyapps` |
| `->lightbox()` | `swiper` + `fancyapps` |

> La concatenazione usa `::` (non `->`): `Dependencies::swiper()` ritorna un'istanza e i metodi
> sono intercettati via `__callStatic`.

## Input immagini

Forma canonica associativa **`['percorso.jpg' => 'testo alt', ...]`** (la chiave è il percorso,
il valore è l'`alt`). È accettata anche una lista semplice `['a.jpg', 'b.jpg']` (alt vuoto).

## `__swiper($images)`

```php
echo __swiper([
        '/assets/upload/gallery/1.jpg' => 'Salotto',
        '/assets/upload/gallery/2.jpg' => 'Cucina',
    ])
    ->thumbnails()
    ->lightbox()          // oppure ->zoom()
    ->navigation()
    ->pagination();
```

| Metodo | Default | Descrizione |
|---|---|---|
| `->thumbnails(bool = true)` | off | Strip di miniature (derivate dalle stesse immagini a `thumbsSize`). |
| `->zoom(bool = true)` | off | Panzoom in-place sullo slide. **Esclusivo** con `lightbox`. |
| `->lightbox(?string $group = null)` | off | Click → Fancybox gallery con thumbs. **Esclusivo** con `zoom`. |
| `->loop(bool = true)` | off | Loop infinito. |
| `->autoplay(int $ms)` | off | Autoplay con ritardo in ms. |
| `->navigation(bool = true)` | off | Frecce prev/next. |
| `->pagination(bool = true)` | off | Bullet di paginazione. |
| `->slidesPerView(int\|float)` | `1` | Slide visibili. |
| `->spaceBetween(int $px)` | `0` | Spazio tra slide. |
| `->thumbsPerView(int)` | `4` | Miniature visibili nella strip. |
| `->download(bool = true)` | off | Bottone download nel lightbox. |
| `->size(int $px)` | `1440` | Size della slide principale. |
| `->thumbsSize(int $px)` | `240` | Size delle miniature. |
| `->fullSize(int $px)` | max sizes | Size dell'immagine nel lightbox. |
| `->fitCover()` / `->fitContain()` | cover | Adattamento immagine (contain consigliato con `zoom`). |
| `->id(string)` / `->addClass(string)` | — | Ereditati da `Component`. |

## `__gallery($images)`

```php
echo __gallery([
        '/assets/upload/gallery/1.jpg' => 'Salotto',
        '/assets/upload/gallery/2.jpg' => 'Cucina',
        '/assets/upload/gallery/3.jpg' => 'Bagno',
    ])
    ->columns(4, 3, 2)
    ->gap(6)
    ->download();
```

| Metodo | Default | Descrizione |
|---|---|---|
| `->columns(int $d, int $t, int $m)` | `4, 3, 2` | Colonne desktop/tablet/mobile. |
| `->gap(int\|array)` | `6` | Gap uniforme o `['desktop'=>,'tablet'=>,'mobile'=>]`. |
| `->format(string)` | `'h-fit'` | `'h-fit'` (altezza naturale) o ratio (`'1-1'`, `'3-2'`, …). |
| `->download(bool = true)` | off | Bottone download nel lightbox. |
| `->size(int $px)` | `480` | Size dell'anteprima in griglia (piccola). |
| `->fullSize(int $px)` | max sizes | Size dell'immagine nel lightbox (grande). |

## Migrazione da `responsiveGallery()`

La funzione `responsiveGallery()` è stata **rimossa**. Sostituzione:

```php
// prima
echo responsiveGallery($GALLERY, 6, true, 'h-fit');
// dopo
echo __gallery($images)->gap(6)->download()->format('h-fit');
```

Nota sull'input: `responsiveGallery()` accettava
`[['src'=>, 'src-original'=>, 'alt'=>, 'caption'=>], ...]`. Con `__gallery()` passa
`['percorso.jpg' => 'alt', ...]`: l'anteprima è generata dal builder `Image` a `size()` piccola
e l'immagine del lightbox a `fullSize()` grande (niente più `src`/`src-original` separati).
````

- [ ] **Step 2: Registrare la pagina nell'indice**

In `docs/app/SUMMARY.md`, dopo la riga 41 (`  * [Charts](concetti/componenti/charts.md)`), aggiungere:

```markdown
  * [Swiper e Gallery](concetti/componenti/swiper-e-gallery.md)
```

- [ ] **Step 3: Commit**

```bash
git add docs/app/concetti/componenti/swiper-e-gallery.md docs/app/SUMMARY.md
git commit -m "docs(componenti): documenta __swiper() e __gallery(), migrazione responsiveGallery

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- Builder fluenti + renderer di tema → Task 1 (Gallery), Task 2 (Swiper).
- Helper `__swiper()`/`__gallery()` → Task 1 Step 6, Task 2 Step 5.
- Click esclusivo zoom/lightbox → Swiper element `zoom()`/`lightbox()` + test esclusività (Task 2 Step 1).
- Thumbnails → Swiper `thumbnails()` + strip nel renderer + test.
- Mix con la lib (`Image`) → trait `HandlesMedia::renderImage()`.
- Input `[src=>alt]` + size anteprima/lightbox → `normalizeImages()` + `renderImage`/`imageUrl` + test size.
- Dipendenze in testa, no auto-load → Global Constraints + doc (Task 3), nessuna modifica a `Dependencies.php`.
- Rimozione `responsiveGallery()` → Task 1 Step 8-9.
- Doc + SUMMARY → Task 3.

**Placeholder scan:** nessun TBD/TODO; ogni step di codice contiene il codice completo.

**Type consistency:** `HandlesMedia` definisce `normalizeImages`/`renderImage`/`imageUrl`/`mediaId`
usati identici nei due renderer; gli schema key (`preview-size`, `full-size`, `fit-contain`,
`lightbox-group`, `thumbs-per-view`, …) sono scritti/letti con le stesse stringhe tra element e
renderer.
