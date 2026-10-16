# Immagini di Swiper e Gallery

`Image::sizes(array)` elenca le risoluzioni dei file generati. `displaySizes(string)` configura invece l'attributo HTML `sizes`, ossia lo spazio occupato dall'immagine nel layout.

```php
Swiper::make($photos)
    ->priority()
    ->imageSizes('(max-width: 768px) 100vw, 66vw')
    ->thumbnails()
    ->thumbsImageSizes('(max-width: 768px) 25vw, 17vw');

Gallery::make($photos)->columns(4, 3, 2);
Gallery::make($photos)->imageSizes('(max-width: 768px) 100vw, 40vw');
```

La priorità è opt-in: solo la prima foto principale diventa eager con fetchpriority high e senza skeleton. `priority(false)` la disabilita. Le altre foto e le miniature restano lazy, con decoding async. Le opzioni immagini non intervengono sulle slide di contenuto generico.

Gallery ricava sizes dalle colonne: breakpoint Wonder 768/992px, Bootstrap md/xl (768/1200px). Il valore automatico presume un contenitore largo quanto il viewport e non sottrae i gap: usare imageSizes per contenitori più stretti. Swiper conserva il comportamento precedente se non vengono specificati imageSizes e thumbsImageSizes.

Entrambi i temi usano il renderer Image del tema esplicito. Gli URL remoti non ricevono varianti inventate; per le immagini locali le varianti devono già esistere. Il rendering non converte né scarica immagini.

## Immagine mancante

Ogni `<img>` di `Image` (e quindi di `__i()`, `__ri()`, Swiper e Gallery)
porta `data-wi-image-name` con il nome del file passato a `src()`: senza
cartella, query e frammento, e senza il suffisso della variante (`-960`) o il
`.webp` del `<picture>`. Se il browser non riesce a caricare l'immagine, la lib
(dalla 2.1.2-alpha.30) scrive quel nome dentro il box, così si vede subito
quale file manca. Il renderer non controlla il disco: lo segnala il browser
con l'evento `error`. Un'immagine lenta non viene mai marcata.

Con `src('')` o una cartella il nome è vuoto: l'`<img>` riceve anche
`data-wi-image-missing-label` con il testo tradotto
`components.media.image_missing` («Immagine non impostata»).

```php
Image::src($url)->missingLabel(false); // solo questa immagine
```

`IMAGE_MISSING_LABEL=false` nel `.env` la spegne su tutto il sito; di default è
attiva anche in produzione. Un `data-wi-image-name` passato con `attr()` vince
su quello calcolato.

Verifica: `php tests/responsive-media.php`.

Le immagini Gallery con formato naturale usano width 100% e height auto: gli attributi HTML width/height riservano il rapporto intrinseco senza bloccare l'altezza alla risoluzione del file. I formati cover/contain mantengono il riempimento del contenitore.
