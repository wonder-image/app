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

## Rendering nel backend (tema Bootstrap)

Gli stessi elementi (`Swiper`, `Gallery` e `Image`) hanno un renderer anche per il tema
**Bootstrap**, quindi si possono usare nelle pagine backend / `CustomPageSchema`. Il `Resolver`
sceglie il renderer in base a `Theme::get()`; in backend (`Theme::set('bootstrap')`) esce markup
Bootstrap 5.3 nativo:

| Elemento | Markup Bootstrap | Librerie |
|---|---|---|
| `Gallery` | griglia `row row-cols-* g-*`, ratio via `.ratio .ratio-*`, lightbox Fancybox | `fancyapps` |
| `Swiper` | `.swiper` (Swiper.js reale) con utility Bootstrap; zoom Panzoom / lightbox Fancybox | `swiper` (+ `fancyapps` se zoom/lightbox) |
| `Image` | `<picture>`/`<img>` con srcset+WebP; `object-fit-*` al posto delle classi lib | — |

Tutti e tre supportano `columnSpan()`. Il wrapper di colonna viene emesso solo
quando il metodo e stato chiamato esplicitamente; senza span il markup del
media non riceve alcun contenitore aggiuntivo.

{% hint style="info" %}
**Dipendenze on-demand:** a differenza del frontend Wonder, i renderer Bootstrap **abilitano da
soli** le librerie necessarie via `Dependencies` durante il render (Swiper.js, e Fancybox/Panzoom
solo per zoom/lightbox/download). Il contenuto di pagina è renderizzato prima di `Dependencies::Head()`
nel layout, quindi gli asset finiscono in `<head>` automaticamente: in backend **non** serve
dichiararle a mano.
{% endhint %}

Differenze rispetto al frontend Wonder: lo script di init gira su `window.addEventListener('load')`
(il backend non emette l'evento `'loaded'` della lib); `gap` è mappato sui gutter Bootstrap
`g-0..g-5` (clamp); `format='h-fit'` è una griglia ad altezza naturale (Bootstrap non ha masonry
nativo).

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
| `->columnSpan(int\|array)` | non dichiarato | Wrapper di colonna opt-in attorno a slider, thumbnails e script. |
| `->id(string)` / `->addClass(string)` | — | Ereditati da `Component`. |

### Esempio: zoom in-place (scheda prodotto)

```php
Wonder\App\Dependencies::swiper()::fancyapps();

echo __swiper($productImages)   // ['/path/1.jpg' => 'Prodotto', ...]
    ->zoom()
    ->fitContain()
    ->pagination();
```

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
| `->columnSpan(int\|array)` | non dichiarato | Wrapper di colonna opt-in attorno a griglia e script. |

La stessa regola vale per `Image`: `->columnSpan(6)` aggiunge un solo wrapper
attorno a `<img>` o `<picture>`; senza chiamata l'elemento resta privo di
contenitori aggiuntivi.

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
