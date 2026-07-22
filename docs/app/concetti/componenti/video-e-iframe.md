# Video e Iframe

`Video` e `Iframe` sono componenti media fluenti renderizzabili sia con il tema
frontend Wonder sia con il tema backend Bootstrap. Il `Resolver` seleziona il
renderer dal tema attivo; per un render puntuale si puo passare il tema a
`render('bootstrap')` o `render('wonder')`.

## Video

Il costruttore canonico e `Video::src()`. L'helper frontend `__v()` restituisce
lo stesso builder.

```php
use Wonder\Elements\Media\Video;

echo Video::src('/assets/video/intro.mp4')
    ->poster('/assets/video/intro.jpg')
    ->webm()              // opzionale: deriva intro.webm
    ->webp()              // usa intro.webp come poster
    ->autoplay()
    ->fitCover()
    ->fixed()
    ->filter()
    ->render();

// Equivalente per creare il builder:
$video = __v('/assets/video/intro.mp4');
```

Il poster e sempre presente: se `poster()` non viene chiamato, viene derivato
dalla source principale sostituendo l'estensione con `.jpg`. `webp()` riguarda
soltanto il formato del poster. La source video WebM e separata e non viene mai
aggiunta automaticamente: va abilitata con `webm()`.

Per compatibilita con il precedente video di background, `loop`, `muted` e
`playsinline` partono attivi; autoplay, fit e filtro restano invece espliciti e
possono essere composti in base al contesto.

| Metodo | Default | Descrizione |
|---|---|---|
| `poster(string $url)` | JPG derivato | Imposta un poster esplicito. |
| `webp(bool = true)` / `hasWebP()` | off | Usa la variante `.webp` del poster. |
| `webm(bool\|string = true)` | off | `true` deriva la source `.webm`; una stringa usa un URL esplicito; `false` la rimuove. |
| `autoplay(bool = true)` | off | Gestisce l'attributo booleano `autoplay`; disabilita `hover`. |
| `hover(bool = true)` | off | Avvia al mouse enter e mette in pausa al mouse leave; disabilita `autoplay`. |
| `start(string)` | manuale | Scorciatoia per `autoplay`, `hover` o `manual`. |
| `controls(bool = true)` | off | Mostra i controlli nativi. |
| `loop(bool = true)` | on | Gestisce il loop nativo. |
| `muted(bool = true)` | on | Gestisce l'audio disattivato. |
| `playsInline(bool = true)` | on | Evita il fullscreen forzato sui browser mobili compatibili. |
| `fitCover()` / `fitContain()` | off | Imposta l'object fit; le due modalita sono esclusive. |
| `fixed(bool = true)` | off | Rende il video fixed e applica `z-index: -1` se non e gia definito. |
| `filter(bool = true)` | off | Aggiunge dopo il video un overlay coerente con il tema. |
| `columnSpan(int\|array)` | non dichiarato | Aggiunge un wrapper di colonna opt-in attorno a video e filtro. |

Gli URL delle source sono emessi con `src`, non con il vecchio `data-src`: il
componente non dipende dal JavaScript lazy-loading specifico dei vecchi siti.
Per evitare il preload si puo usare `->attr('preload', 'none')`; un vero lazy
load basato sulla viewport richiede invece un runtime JavaScript dedicato.
La modalita `hover` e autonoma e aggiunge gli handler `mouseenter`/`mouseleave`
al tag video. Con una Content Security Policy che vieta gli handler inline,
usa `start('manual')` e collega `play()`/`pause()` dal JavaScript del sito.

L'overlay di un video non fixed e posizionato in modo assoluto: il contenitore
che ospita video e filtro deve quindi avere un contesto di posizionamento (per
esempio `p-r` in Wonder o `position-relative` in Bootstrap).

## Iframe

L'API dedicata resta intenzionalmente minima: URL e modalita fit. Attributi,
classi, id e stili aggiuntivi arrivano dall'API comune di `Component`.

```php
use Wonder\Elements\Media\Iframe;

echo Iframe::url('https://www.google.com/maps/embed')
    ->fitCover()
    ->attr('title', 'Google Maps')
    ->attr('allowfullscreen', true)
    ->render();
```

`fitCover()` e `fitContain()` sono mutuamente esclusivi. Sono inoltre disponibili
i metodi ereditati `class()`, `addClass()`, `id()`, `attr()`, `attributes()`,
`style()`, `styles()` e `columnSpan()`. L'iframe usa di default
`loading="lazy"` e `border: 0`; entrambi restano sovrascrivibili tramite l'API
comune degli attributi e stili.

`columnSpan()` e opt-in anche per l'iframe: senza chiamata il risultato e il
solo tag `<iframe>`; con uno span esplicito viene aggiunto un unico wrapper di
colonna.

Per sicurezza `url()` accetta URL relativi e schemi `http`/`https`, ma rifiuta
schemi attivi come `javascript:` e `data:`. Per contenuti esterni non fidati e
consigliato aggiungere anche un attributo `sandbox` adeguato al caso d'uso.

I metodi fit applicano le classi e il dimensionamento del box coerenti con il
tema. Il contenuto interno di un iframe resta pero un documento separato: i
browser non possono ritagliarlo con `object-fit` come avviene per immagini e
video.

### Iframe con ratio nel backend

Le utility Bootstrap `ratio` devono essere applicate al genitore diretto
dell'iframe. In un `formLayoutSchema()` usa `Container::noGrid()` per evitare
che il layout aggiunga `row` e gutter al wrapper:

```php
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;
use Wonder\Elements\Media\Iframe;

return (new Form())->components([
    SectionTitle::make('Mappa')->level(5),

    (new Container())
        ->noGrid()
        ->addClass('ratio ratio-16x9 img-thumbnail')
        ->components([
            Iframe::url($IMMOBILE->gmaps)
                ->fitCover()
                ->addClass('rounded')
                ->attr('allowfullscreen', true)
                ->attr('referrerpolicy', 'no-referrer-when-downgrade'),
        ]),
]);
```

Se il Container e figlio di un layout, il renderer Resource mantiene il suo
wrapper esterno `col-*` e produce all'interno un solo
`<div class="ratio ratio-16x9 img-thumbnail">`. Se il Container `noGrid()` e
invece la radice passata a `ResourceFormLayoutRenderer::renderLayout()`, il
wrapper `ratio` viene emesso direttamente, senza `row` esterna. Classi, `id`,
`style`, `data-*`, `aria-*` e attributi booleani impostati sul Container vengono
conservati. Lascia l'iframe senza `columnSpan()`, cosi non compare alcun wrapper
tra `.ratio` e il tag `<iframe>`.

## Classi per tema

| Opzione | Wonder | Bootstrap 5.3 |
|---|---|---|
| `fitCover()` | `bg bg-cover` | `object-fit-cover w-100 h-100` |
| `fitContain()` | `bg bg-contain` | `object-fit-contain w-100 h-100` |
| Video `fixed()` | `p-f w-100 h-100` | `position-fixed top-0 start-0 w-100 h-100` |
| `columnSpan(6)` | wrapper `col-6` | wrapper `col-span-6` |

Non sono richieste registrazioni o dipendenze: le classi sono risolte per
convenzione di namespace dal sistema Element / Theme.
