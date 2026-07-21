# Bootstrap Media Renderers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:test-driven-development. Ogni renderer nuovo/rifattorizzato parte da un test standalone che fallisce, poi implementazione. Steps con checkbox (`- [ ]`).

**Goal:** Aggiungere i renderer del tema **Bootstrap** per `Wonder\Elements\Media\Gallery`, `Wonder\Elements\Media\Swiper` e `Wonder\Elements\Media\Image`, così che questi elementi si possano renderizzare in backend (`Theme::set('bootstrap')`) invece di lanciare `RuntimeException` dal `Resolver`.

**Architecture:** Il `Resolver` mappa `Wonder\Elements\Media\X` → `Wonder\Themes\Bootstrap\Media\X` (nessun fallback cross-tema). Il backend carica **solo** Bootstrap 5.3 + Bootstrap Icons: le librerie JS extra (Swiper.js, Fancybox/Panzoom) vengono abilitate **on-demand** dal renderer via `Wonder\App\Dependencies` durante il render. `View::layout()` bufferizza il contenuto e `View::end()` lo cattura **prima** di eseguire `Dependencies::Head()`/`Body()` nel layout: le dipendenze registrate dal renderer compaiono quindi negli asset di pagina.

**Riuso (DRY):** due estrazioni theme-neutral in `Wonder\Themes\Concerns`:
- `HandlesMedia` (spostato da `Themes\Wonder\Media\Concerns`): `normalizeImages` / `renderImage` / `imageUrl` / `mediaId`. `renderImage()` costruisce un elemento `Image` e chiama `->render()`, che risolve al renderer del tema attivo → già neutrale.
- `RendersResponsiveImage` (nuovo): motore `<picture>`/srcset/webp/sizes/mime, con hook `applyThemeClasses($class)` sovrascrivibile per le classi CSS specifiche del tema. Usato da Wonder Image (output invariato) e Bootstrap Image.

**Tech Stack:** PHP 8.2, framework `wonder-image/app`. Tema Bootstrap in `class/Themes/Bootstrap/`, tema Wonder in `class/Themes/Wonder/`.

## Global Constraints

- **Test = script PHP standalone** (no PHPUnit): `require vendor/autoload.php`, helper `has()`/`hasnt()`/`eq()`, `exit($fail===0?0:1)`; eseguiti con `php tests/<path>Test.php` dalla root. Output pulito (niente warning/notice). Stesso stile di `tests/Elements/Media/GalleryTest.php` e `SwiperTest.php`.
- **Output Wonder invariato:** l'estrazione dei trait NON deve cambiare il markup del tema Wonder. Lockato da `tests/Elements/Media/{Gallery,Swiper}Test.php` esistenti + nuovo `tests/Themes/Wonder/Media/ImageTest.php`.
- **On-demand deps:** `Dependencies::swiper()` per lo slider; `Dependencies::fancyapps()` per lightbox (Gallery/Swiper) e zoom/Panzoom (Swiper). Mai caricare la lib frontend (`wiLib`/`wiFrontend`) in backend.
- **Init JS in backend:** usare `window.addEventListener('load', …)` (il backend NON emette l'evento custom `'loaded'` della lib frontend).
- **Solo utility Bootstrap 5.3** nel markup Bootstrap: `row`/`row-cols-*`/`g-*`, `ratio ratio-*`, `object-fit-*`, `w-100`/`h-100`, `overflow-hidden`, `position-absolute`/`top-0`/`start-0`, `user-select-none`/`pe-none`. Le classi `f-panzoom*` provengono dal CSS di fancyapps (ok in zoom).
- `php -l` su ogni file PHP toccato; `composer dump-autoload` per le classi nuove.
- Modifiche framework: commit solo se richiesto dall'utente.

---

### Task 1: Estrai `HandlesMedia` in namespace theme-neutral

**Files:**
- Move: `class/Themes/Wonder/Media/Concerns/HandlesMedia.php` → `class/Themes/Concerns/HandlesMedia.php` (namespace `Wonder\Themes\Concerns`, corpo invariato — referenzia solo `Wonder\Elements\Media\Image`, già neutrale).
- Modify: `class/Themes/Wonder/Media/Gallery.php`, `class/Themes/Wonder/Media/Swiper.php` — aggiorna `use` da `Wonder\Themes\Wonder\Media\Concerns\HandlesMedia` a `Wonder\Themes\Concerns\HandlesMedia`.

**Interfaces:**
- Produces: `trait Wonder\Themes\Concerns\HandlesMedia` — `normalizeImages(array): array`, `renderImage(string,string,int,string,bool): string`, `imageUrl(string,int): string`, `mediaId(mixed,string): string`.

- [ ] **Step 1: Verifica riferimenti** — `grep -rIn 'Wonder\\\\Media\\\\Concerns\\\\HandlesMedia\|Media/Concerns/HandlesMedia' class tests` per assicurarsi che gli unici consumer siano Wonder Gallery/Swiper.
- [ ] **Step 2: Sposta il file e aggiorna il namespace + i 2 `use`.** Rimuovi la vecchia dir `class/Themes/Wonder/Media/Concerns/` se vuota.
- [ ] **Step 3: `composer dump-autoload` + `php -l`** sui 3 file.
- [ ] **Step 4: Regressione** — `php tests/Elements/Media/GalleryTest.php` e `php tests/Elements/Media/SwiperTest.php` devono restare **PASS** (pinnati a `Theme::set('wonder')`).

---

### Task 2: Estrai `RendersResponsiveImage` + rifattorizza Wonder Image

**Files:**
- Create: `class/Themes/Concerns/RendersResponsiveImage.php`
- Modify: `class/Themes/Wonder/Media/Image.php`
- Test (lock output Wonder): `tests/Themes/Wonder/Media/ImageTest.php`

**Interfaces:**
- Produces: `trait Wonder\Themes\Concerns\RendersResponsiveImage` — `use Wonder\Concerns\HasSchema; use Wonder\Themes\Concerns\HasAttributes;` (per `getSchema` + `renderAttributes`/`escape`). Metodi: `renderResponsiveImage($class): string` (entry: `init` poi picture/img), `init($class)`, `renderImg`, `renderPicture`, `renderSource`, `getMimeType`, `renderSrcSet`, `renderSizes`, hook `protected applyThemeClasses($class): void {}` (default no-op). `init()` chiama `applyThemeClasses($class)` **al posto** dei 4 `if`/`addClass` inline, prima di calcolare `$this->attributes`.
- `Wonder\Themes\Wonder\Media\Image` → `use RendersResponsiveImage;`, `render()` delega a `renderResponsiveImage($class)`, override `applyThemeClasses()` che riproduce **identiche** le classi lib attuali:
  - `fit-cover` → `bg bg-cover`; `fit-contain` → `bg bg-contain`; `skeleton` → `skeleton`; `draggable === false` → `no-interaction unselectable`.

- [ ] **Step 1: Test di lock (fallisce se cambia il markup)** — `tests/Themes/Wonder/Media/ImageTest.php` (Theme=wonder). Casi:
  - `Image::src('/assets/upload/a.jpg')->hasWebP()->size(960)->sizes(RESPONSIVE_IMAGE_SIZES)->alt('X')` → contiene `<picture>`, `<source type="image/webp"`, `a-960.jpg`, `a-960.webp`, `alt="X"`.
  - `->fitCover()` → `bg bg-cover`; `->fitContain()` → `bg bg-contain`; `->skeleton()` → `skeleton`; `->notDraggable()` → `no-interaction unselectable`.
  - src `.webp` con `hasWebP()` → ramo `<img>` (no `<picture>`).
- [ ] **Step 2: Crea il trait** spostando i metodi dal renderer Wonder verbatim; sostituisci i 4 `if` con `applyThemeClasses($class)`.
- [ ] **Step 3: Rifattorizza Wonder Image** per usare il trait + override hook.
- [ ] **Step 4: `composer dump-autoload` + `php -l`.**
- [ ] **Step 5: Verde** — nuovo ImageTest **PASS** + regressione Gallery/Swiper Wonder ancora **PASS**.

---

### Task 3: `Bootstrap\Media\Image`

**Files:**
- Create: `class/Themes/Bootstrap/Media/Image.php`
- Test: `tests/Themes/Bootstrap/Media/ImageTest.php`

**Interfaces:**
- `Wonder\Themes\Bootstrap\Media\Image extends Wonder\Themes\Bootstrap\Component` — `use RendersResponsiveImage;`, `render()` → `renderResponsiveImage($class)`. `applyThemeClasses()`:
  - `fit-cover` → `object-fit-cover w-100 h-100`; `fit-contain` → `object-fit-contain w-100 h-100`; `skeleton` → `bg-body-secondary`; `draggable === false` → classi `user-select-none pe-none` + attributo `draggable="false"` (via `$class->attr('draggable','false')`, prima del calcolo `$this->attributes`).

- [ ] **Step 1: Test fallito** (Theme=bootstrap): srcset/webp presenti (`a-960.jpg`, `a-960.webp`, `<picture>`); `->fitCover()` → `object-fit-cover`; `->fitContain()` → `object-fit-contain`; `->notDraggable()` → `draggable="false"` + `pe-none`.
- [ ] **Step 2: Implementa il renderer.**
- [ ] **Step 3: `composer dump-autoload` + `php -l` + test PASS.**

---

### Task 4: `Bootstrap\Media\Gallery`

**Files:**
- Create: `class/Themes/Bootstrap/Media/Gallery.php`
- Test: `tests/Themes/Bootstrap/Media/GalleryTest.php`

**Interfaces:**
- `Wonder\Themes\Bootstrap\Media\Gallery extends Bootstrap\Component` — `use HandlesMedia;`. `render()`:
  - Schema letti: `images`, `columns` (default 4/3/2), `gap` (int|array, default 6), `format` (default `h-fit`), `download` (bool), `preview-size` (default 480), `full-size` (default `max(RESPONSIVE_IMAGE_SIZES)`).
  - Griglia: `<div class="row row-cols-{mobile} row-cols-md-{tablet} row-cols-xl-{desktop} {gutter}">`. Gutter responsive da `gap` clampato 0–5: `g-{m} g-md-{t} g-xl-{d}` (scalare → stesso valore per tutti).
  - Cella: `<div class="col">` con `<a href="{full}" data-fancybox="{id}"{caption} class="d-block rounded overflow-hidden">{thumb}</a>`.
    - `format === 'h-fit'` → thumb = `renderImage(src,alt,previewSize,'natural')` (img `w-100`).
    - `format` tipo `1-1`/`4-3`/`16-9`/`21-9` → `<div class="ratio ratio-{NxN}">renderImage(src,alt,previewSize,'cover')</div>` (mappa `1-1→1x1`, `4-3→4x3`, `16-9→16x9`, `21-9→21x9`; sconosciuto → natural).
  - `Dependencies::fancyapps()` (lightbox + download).
  - Script: `window.addEventListener('load', function(){ Fancybox.bind('[data-fancybox="{id}"]', {opts}); });` — `opts` include `buttons: ['download','thumbs','close']` se `download`.

- [ ] **Step 1: Test fallito** (Theme=bootstrap): contiene `row row-cols-2 row-cols-md-3 row-cols-xl-4`, `data-fancybox="gallery-test"`, `a-2400.jpg` (full-size), `data-caption="Alpha"`, `a-480.jpg` (preview), `Fancybox.bind('[data-fancybox="gallery-test"]'`, e per `format('16-9')` → `ratio ratio-16x9`.
- [ ] **Step 2: Implementa** (griglia + Fancybox on-demand).
- [ ] **Step 3: `composer dump-autoload` + `php -l` + test PASS.**

---

### Task 5: `Bootstrap\Media\Swiper`

**Files:**
- Create: `class/Themes/Bootstrap/Media/Swiper.php`
- Test: `tests/Themes/Bootstrap/Media/SwiperTest.php`

**Interfaces:**
- `Wonder\Themes\Bootstrap\Media\Swiper extends Bootstrap\Component` — `use HandlesMedia;`. `render()` replica il flusso del renderer Wonder Swiper con:
  - `Dependencies::swiper()` sempre; `Dependencies::fancyapps()` se `zoom` o `lightbox`.
  - Utility Bootstrap: `overflow-hidden` (non `o-hidden`), `w-100`, `mt-2`.
  - Slide zoom: `<div class="swiper-slide w-100"><div class="f-panzoom w-100"><div class="f-panzoom__viewport ratio ratio-1x1 w-100 overflow-hidden"><div class="f-panzoom__content position-absolute top-0 start-0 w-100 h-100">{img}</div></div></div></div>`.
  - Slide lightbox: `<a class="swiper-slide overflow-hidden" data-fancybox="{group}" href="{full}"{caption}>{img}</a>`.
  - Slide semplice: `<div class="swiper-slide overflow-hidden">{img}</div>`.
  - Thumbs: `<div id="{id}-thumbs" class="swiper w-100 overflow-hidden mt-2"><div class="swiper-wrapper">…</div></div>`.
  - Pagination/navigation come Wonder (`swiper-pagination`, `swiper-button-next/prev`).
  - Script init in `window.addEventListener('load', function(){ … })`: `new Swiper('#{id}', {…})` con `slidesPerView`/`spaceBetween`/`loop`/`autoplay`/`pagination`/`navigation`/`thumbs`; `new Swiper('#{id}-thumbs', {…})` se thumbs; `new Panzoom(...)` su `.f-panzoom` se zoom; `Fancybox.bind('[data-fancybox="{group}"]', opts)` se lightbox (con `buttons` se `download`).

- [ ] **Step 1: Test fallito** (Theme=bootstrap):
  - zoom+thumbs+nav+pag: `id='swiper-zoom'`, `swiper-wrapper`, `a-1440.jpg`, `f-panzoom__viewport`, `id='swiper-zoom-thumbs'`, `a-240.jpg`, `new Swiper('#swiper-zoom'`, `new Swiper('#swiper-zoom-thumbs'`, `new Panzoom`, `swiper-button-next`, `swiper-pagination`, `overflow-hidden`, `window.addEventListener('load'`; `hasnt` `Fancybox.bind`.
  - lightbox+download+loop: `data-fancybox="galleria"`, `a-2400.jpg`, `Fancybox.bind('[data-fancybox="galleria"]'`, `buttons: ['download', 'thumbs', 'close']`, `loop: true`; `hasnt` `f-panzoom__viewport`.
  - esclusività `zoom()->lightbox('g')` → `hasnt` `f-panzoom__viewport`, `has` `data-fancybox="g"`.
- [ ] **Step 2: Implementa** il renderer (deps on-demand, evento `load`).
- [ ] **Step 3: `composer dump-autoload` + `php -l` + test PASS.**

---

### Task 6: Validazione finale

- [ ] `php -l` su tutti i file toccati/creati.
- [ ] `composer dump-autoload`.
- [ ] Esegui l'intera suite media: `php tests/Elements/Media/GalleryTest.php`, `SwiperTest.php`, `tests/Themes/Wonder/Media/ImageTest.php`, `tests/Themes/Bootstrap/Media/{Image,Gallery,Swiper}Test.php` → tutti **PASS**.
- [ ] Aggiorna `docs/app/*` se esiste una sezione temi/renderer (altrimenti nota nel report).

## Known simplifications

- `format='h-fit'` in Gallery non è masonry (Bootstrap non lo supporta nativamente): resa a griglia con immagini ad altezza naturale.
- `fit-cover`/`fit-contain` nelle slide Swiper "semplici" (senza `ratio`) mordono solo se la slide ha un'altezza definita dal contesto; zoom usa `ratio ratio-1x1`.
- `gap` mappato sui gutter Bootstrap `g-0..g-5` (clamp): la scala non è 1:1 con `gap-N` della lib.
