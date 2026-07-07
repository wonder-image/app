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
