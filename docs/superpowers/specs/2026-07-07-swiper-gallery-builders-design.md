# Swiper & Gallery — builder fluenti (design)

Data: 2026-07-07
Repo: `wonder-image/app` (framework)
Stato: approvato in brainstorming, in attesa di review dello spec

## 1. Obiettivo

Introdurre due nuovi componenti frontend come **builder fluenti** (stile `__ri()`), integrati
con `wonder-image/lib`:

1. **Swiper** (`__swiper($images)`): carosello immagini con
   - miniature (thumbnails / thumbs-gallery),
   - al click, **una** modalità esclusiva tra:
     - `->zoom()` — Panzoom in-place sullo slide (fancyapps/Panzoom),
     - `->lightbox()` — apertura Fancybox gallery con thumbs (fancyapps/Fancybox).
2. **Gallery** (`__gallery($images)`): griglia responsive di immagini con lightbox Fancybox,
   che **sostituisce** la funzione legacy `responsiveGallery()`.

Librerie: Swiper (https://swiperjs.com/demos), Fancybox (https://fancyapps.com/fancybox/),
Panzoom (https://fancyapps.com/panzoom/). Fancybox e Panzoom sono nello stesso bundle lib
`fancyapps` (`/dist/lib/fancyapps/fancyapps.{js,css}`).

Riferimenti reali (progetti clienti, solo come modello di markup/comportamento):
- thumbs + lightbox: `clients/bg-star/.../immobile/index.php`
- zoom in-place: `clients/textile-collection/.../prodotto/index.php`

## 2. Decisioni di design (dal brainstorming)

| Tema | Decisione |
|---|---|
| Stile API | **Builder fluente** = Component (`class/Elements/`) + renderer di tema (`class/Themes/Wonder/`), risolti dal `Resolver`. Esposti da helper `__swiper()` / `__gallery()`. |
| Click Swiper | **Modalità esclusive**: `->zoom()` XOR `->lightbox()`. Senza nessuna → solo swipe. |
| Gallery legacy | **Rimuovere** `responsiveGallery()` / `cardResponsiveGallery()`; il nuovo `__gallery()` le sostituisce. |
| Nomi helper | `__swiper()` / `__gallery()`. |
| Dipendenze | **Nessun auto-load.** Lo sviluppatore dichiara gli asset in testa alla pagina: `Wonder\App\Dependencies::swiper()::fancyapps();`. I componenti assumono gli asset già caricati. Nessuna modifica a `Dependencies.php`. |
| `responsiveGallery()` | Eliminata (nessun chiamante interno al framework). |

Nota sintassi dipendenze: `Dependencies::swiper()` ritorna un'istanza; la concatenazione
usa `::` (non `->`) perché il framework intercetta i metodi via `__callStatic` — stesso stile
di `app/bootstrap/frontend.php`.

## 3. Architettura & file

Pattern identico a `Image` (`__ri()`): il builder è un `Wonder\Elements\...` che usa il trait
`Renderer`; `render()` chiama `Resolver::renderer(static::class)` che mappa
`Wonder\Elements\Media\X` → `Wonder\Themes\Wonder\Media\X`.

| File | Azione | Ruolo |
|---|---|---|
| `class/Elements/Media/Swiper.php` | nuovo | builder `Wonder\Elements\Media\Swiper` |
| `class/Elements/Media/Gallery.php` | nuovo | builder `Wonder\Elements\Media\Gallery` |
| `class/Themes/Wonder/Media/Swiper.php` | nuovo | renderer markup + init |
| `class/Themes/Wonder/Media/Gallery.php` | nuovo | renderer markup + init |
| `app/function/helper.php` | modifica | helper `__swiper($images)` / `__gallery($images)` accanto a `__i`/`__ri` |
| `app/function/frontend/plugin/gallery.php` | **eliminato** | conteneva `responsiveGallery`/`cardResponsiveGallery` |
| `app/function/frontend/plugin/function.php` | modifica | rimuovere `require_once __DIR__."/gallery.php";` |
| `docs/app/concetti/componenti/swiper-e-gallery.md` | nuovo | documentazione |
| `docs/app/SUMMARY.md` | modifica | voce sotto "Componenti UI" |

`app/function/frontend/plugin/swiper.php` (`swiper_fashionSlider`) **resta invariato**: è un
widget diverso (fashion slider animato), fuori scope.

## 4. Dipendenze (dichiarazione in testa)

I componenti **non** caricano asset. La pagina deve dichiararli prima del render di `<head>`
(che avviene in `app/view/components/frontend/layout/head.php`, dove gira `Dependencies::Head()`):

```php
// in cima alla pagina, prima dell'HTML
Wonder\App\Dependencies::swiper();                 // solo swipe / thumbnails
Wonder\App\Dependencies::swiper()::fancyapps();    // + zoom o lightbox
```

Requisiti per modalità:
- swipe / `->thumbnails()` → `swiper` (i thumbs sono core Swiper, nessun `swiper-plugin`)
- `->zoom()` → `swiper` + `fancyapps`
- `->lightbox()` → `swiper` + `fancyapps`

La documentazione evidenzia questo requisito in modo prominente (il componente fallisce
silenziosamente lato JS se la lib non è caricata).

## 5. Componente Swiper

### 5.1 Builder `Wonder\Elements\Media\Swiper`

Estende `Wonder\Elements\Component`, usa il trait `Renderer`. Ogni metodo salva nello
`schema` e ritorna `self`.

```php
__swiper($images)
    // $images: ['a.jpg', ...] oppure [['src'=>, 'alt'=>, 'caption'=>, 'thumb'=>], ...]
    ->thumbnails(bool $on = true)      // strip miniature; usa 'thumb' se presente, altrimenti la stessa img
    ->zoom(bool $on = true)            // Panzoom in-place        ⟵ esclusivo con lightbox
    ->lightbox(?string $group = null)  // Fancybox gallery+thumbs ⟵ esclusivo con zoom
    ->loop(bool $on = true)
    ->autoplay(int $delayMs)
    ->navigation(bool $on = true)
    ->pagination(bool $on = true)
    ->slidesPerView(int|float $n)
    ->spaceBetween(int $px)
    ->thumbsPerView(int $n)            // default 4
    ->download(bool $on = true)        // bottone download nel lightbox
    // passthrough a __ri() per ogni slide:
    ->size(int $px) ->sizes(array) ->fitCover(bool) ->fitContain(bool)
    // ereditati da Component:
    ->id(string) ->addClass(string)
    ->render(): string
```

Esclusività: `zoom(true)` imposta `lightbox=false` e viceversa (come `fitCover`/`fitContain`
in `Image`). Default: entrambe off.

Normalizzazione input: una stringa → `['src'=>stringa]`; l'array è passato così com'è. `alt`
default = `''`; `caption` opzionale; `thumb` opzionale (fallback a `src`).

### 5.2 Renderer `Wonder\Themes\Wonder\Media\Swiper`

Genera un `id` (`code(5,'all','swiper-')`, come `swiper_fashionSlider`). Ogni immagine passa da
`__ri($src)->alt(...)->size(...)->fit...()` → eredita webp, srcset, skeleton, `no-interaction`
(il "mix con la lib"). Solo utility **esistenti** in `wonder-image/lib` + classi standard
swiper/fancybox/panzoom. **Nessuna nuova classe `.wi-*`**, nessun `<style>` con selettori
nuovi; l'istanza è targettizzata via `id`. Colore tema swiper lasciato alla lib/sito.

Markup — modalità **zoom** (modello textile):

```html
<div id="{id}" class="swiper w-100">
  <div class="swiper-wrapper">
    <!-- per slide -->
    <div class="swiper-slide w-100">
      <div class="f-panzoom w-100">
        <div class="f-1-1 f-panzoom__viewport w-100 o-hidden">
          <div class="f-panzoom__content p-a top start w-100 h-100">
            {__ri(...)->render()}
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="swiper-pagination"></div>          <!-- se pagination -->
  <div class="swiper-button-next"></div>         <!-- se navigation -->
  <div class="swiper-button-prev"></div>
</div>
<!-- se thumbnails -->
<div id="{id}-thumbs" class="swiper w-100 o-hidden mt-2">
  <div class="swiper-wrapper">
    <div class="swiper-slide o-hidden">{__ri(thumb)->render()}</div>
  </div>
</div>
<script>/* init */</script>
```

Markup — modalità **lightbox** (modello bg-star, robusto col `loop` che clona gli slide):

```html
<div class="d-none">
  <!-- per immagine -->
  <a data-fancybox="{group}" data-src="{src-original|src}" data-caption="{caption}"></a>
</div>
<div id="{id}" class="swiper w-100">
  <div class="swiper-wrapper">
    <!-- per slide -->
    <a class="swiper-slide o-hidden" data-fancybox-trigger="{group}" data-fancybox-index="{i}">
      {__ri(...)->render()}
    </a>
  </div>
  <!-- nav / pagination come sopra -->
</div>
<!-- thumbs come sopra -->
<script>/* init */</script>
```

Script di init (coerente con la convenzione esistente `window 'loaded'` usata da
`swiper_fashionSlider`; il thumbs-Swiper è istanziato prima del main):

```js
window.addEventListener('loaded', function () {
  var thumbs = /* se thumbnails */ new Swiper('#{id}-thumbs', {
    spaceBetween: 8, slidesPerView: {thumbsPerView}, freeMode: true, watchSlidesProgress: true
  });
  var main = new Swiper('#{id}', {
    /* loop, speed, grabCursor, watchSlidesProgress, keyboard */
    pagination:  { el: '#{id} .swiper-pagination' },                                   // se pagination
    navigation:  { nextEl: '#{id} .swiper-button-next', prevEl: '#{id} .swiper-button-prev' }, // se navigation
    thumbs:      { swiper: thumbs },                                                    // se thumbnails
    autoplay:    { delay: {delay} }                                                    // se autoplay
  });
  /* se zoom  */ document.querySelectorAll('#{id} .f-panzoom').forEach(function (el) {
                   new Panzoom(el, { click: 'toggleZoom', dblClick: 'toggleMax', panMode: 'mousemove' });
                 });
  /* se lightbox */ Fancybox.bind('[data-fancybox="{group}"]', { /* buttons se download */ });
});
```

## 6. Componente Gallery

Sostituisce `responsiveGallery()` con la stessa resa (griglia responsive a colonne +
lightbox Fancybox), ma immagini via `__ri()`.

### 6.1 Builder `Wonder\Elements\Media\Gallery`

```php
__gallery($images)
    // $images: [['src'=>, 'src-original'=>, 'alt'=>, 'caption'=>], ...] o path semplici
    ->columns(int $desktop = 4, int $tablet = 3, int $mobile = 2)
    ->gap(int|array $gap = 6)          // int o ['desktop'=>, 'tablet'=>, 'mobile'=>]
    ->format(string $format = 'h-fit') // 'h-fit' (altezza naturale) o ratio '1-1','3-2',...
    ->download(bool $on = true)        // bottone download nel lightbox
    ->size(int) ->sizes(array)         // passthrough a __ri()
    ->id(string) ->addClass(string)
    ->render(): string
```

### 6.2 Renderer `Wonder\Themes\Wonder\Media\Gallery`

Riproduce l'algoritmo di distribuzione a colonne di `responsiveGallery` (blocchi
`desktop`/`tablet`/`mobile` con `d-grid col-* col-t-* col-p-* gap-*`, visibilità
`tablet-none` / `pc-none phone-none` / `pc-none tablet-none`) e le ancore nascoste Fancybox
+ `Fancybox.bind`. Differenza: la card usa `__ri($src)->alt(...)->fitCover()->size(...)->render()`
invece di `<img>` grezzo. `format === 'h-fit'` → immagine ad altezza naturale; altrimenti wrap
`f-{format} o-hidden` con immagine `bg bg-cover`.

## 7. Rimozione `responsiveGallery()`

- Eliminare `app/function/frontend/plugin/gallery.php`.
- Rimuovere `require_once __DIR__."/gallery.php";` da `app/function/frontend/plugin/function.php`.
- Nessun chiamante interno al framework (verificato via grep): niente altro da sostituire.
- I progetti clienti che usano `responsiveGallery()` sono repo separati con `vendor/wonder-image/app`
  pinnato; migrano a `__gallery()` deliberatamente al momento dell'upgrade (fuori scope; coperto
  dalla doc di migrazione).

## 8. Documentazione

Nuova pagina `docs/app/concetti/componenti/swiper-e-gallery.md`:
- API dei due builder (tabelle metodi),
- requisito dipendenze in testa alla pagina (box in evidenza),
- modalità click esclusive (`zoom` vs `lightbox`),
- esempi tratti dai due progetti reali,
- tabella di migrazione `responsiveGallery(...)` → `__gallery(...)`.

Registrazione in `docs/app/SUMMARY.md` sotto "Componenti UI" (accanto a Charts).

## 9. Validazione

- `php -l` su ogni file PHP toccato.
- `composer dumpautoload` (nuove classi in `class/`).
- Verifica da un sito (es. rsvp-site / il flusso in memoria): push su main + `composer update`,
  poi rendere uno `__swiper()` con `->thumbnails()->zoom()`, uno con `->thumbnails()->lightbox()`,
  e un `__gallery()`, controllando markup, caricamento asset e init JS in pagina.
- Confermare che nessun renderer manchi (il `Resolver` fa fail-fast con eccezione esplicita).

## 10. Fuori scope / rischi

- `swiper_fashionSlider` non toccato.
- Nessun caricamento automatico dipendenze: se lo sviluppatore dimentica `Dependencies::...`,
  il componente non si inizializza (documentato).
- CSS: nessuna nuova classe `.wi-*`; se in futuro servisse styling dedicato è una modifica
  lato `wonder-image/lib`, non del framework.
- Rottura per i siti che chiamano `responsiveGallery()` all'upgrade: attesa e voluta, mitigata
  dalla doc di migrazione.
