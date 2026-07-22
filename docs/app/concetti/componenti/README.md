---
icon: puzzle-piece
---

# Componenti UI

## Cos'è

I **componenti** sono i blocchi con cui si compongono i layout di form e di
pagina nel backend, ma anche frammenti UI riusabili come bottoni, badge,
gruppi azioni e dropdown. Vivono in `class/Elements/Components/*` e si
combinano con i campi (`FormInput`) per ottenere layout a colonne o CTA
coerenti tra tema Bootstrap e tema Wonder.

I componenti media (`Image`, `Video`, `Iframe`, `Gallery`, `Swiper`) vivono
invece in `class/Elements/Media/*` e condividono lo stesso sistema Element /
Theme.

## A cosa serve

Strutturare un form (o una pagina) in sezioni ordinate — riquadri, colonne,
avvisi — senza scrivere HTML/CSS a mano.

## Dove si trova nel codice

| Componente | File | Cosa fa |
|---|---|---|
| `Card` | `Elements/Components/Card.php` | riquadro contenitore |
| `InfoCard` | `Elements/Components/InfoCard.php` | etichetta e valore descrittivo |
| `MetricCard` | `Elements/Components/MetricCard.php` | KPI con unita e confronto opzionale |
| `Container` | `Elements/Components/Container.php` | contenitore generico |
| `Accordion` | `Elements/Components/Accordion.php` | sezioni collassabili |
| `Alert` | `Elements/Components/Alert.php` | messaggio/avviso |
| `Text` | `Elements/Components/Text.php` | testo |
| `RichText` | `Elements/Components/RichText.php` | testo formattato |
| `HelpText` | `Elements/Components/HelpText.php` | testo di aiuto |
| `SectionTitle` | `Elements/Components/SectionTitle.php` | titolo di sezione |
| `Link` | `Elements/Components/Link.php` | link |
| `Tooltip` | `Elements/Components/Tooltip.php` | tooltip |
| `Button` | `Elements/Components/Button.php` | bottone / CTA |
| `Badge` | `Elements/Components/Badge.php` | badge / etichetta |
| `ButtonGroup` | `Elements/Components/ButtonGroup.php` | gruppo di bottoni |
| `Dropdown` | `Elements/Components/Dropdown.php` | bottone dropdown |
| `Image` | `Elements/Media/Image.php` | immagine responsive |
| `Video` | `Elements/Media/Video.php` | video HTML5 |
| `Iframe` | `Elements/Media/Iframe.php` | contenuto iframe |
| `Gallery` | `Elements/Media/Gallery.php` | gallery con lightbox |
| `Swiper` | `Elements/Media/Swiper.php` | carosello immagini |

I metodi di composizione arrivano da Concerns riusabili:

- `components(array)` — figli del contenitore (`Concerns/IsContainer.php`)
- `columns(int|array)` — numero di colonne (`Concerns/HasColumns.php`)
- `columnSpan(int|array)` — quante colonne occupa (`Concerns/CanSpanColumn.php`)
- `Container::noGrid()` — usa il Container come wrapper puro, senza classi
  `row`/gutter generate dal layout Bootstrap
- `href()/blank()/target()/rel()/title()/onclick()` — attributi link-like
  condivisi (`Concerns/HasLinkAttributes.php`) per `Link`, `Button`, `Badge`
  e per i link inline composti da `Text`

## Esempio: layout di un form con Card

Si usa in `Resource::formLayoutSchema()` combinando le Card con i campi
recuperati via `static::getInput('campo')` (il campo deve esistere in
`formSchema()`):

```php
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

public static function formLayoutSchema(): ?Form
{
    return (new Form)->components([
        (new Card)->components([
            static::getInput('name')->columnSpan(2),
            static::getInput('description')->columnSpan(2),
            static::getInput('cover')->columnSpan(2),
        ])->columns(2)->columnSpan(2),

        (new Card)->components([
            static::getInput('visible'),
        ])->columns(1)->columnSpan(1),
    ])->columns(3);
}
```

Esempio reale completo: `class/App/Resources/Css/CssAlertResource.php`.

## InfoCard e MetricCard

`Card` resta il contenitore generico per componenti arbitrari. Per mostrare
una coppia etichetta/valore nel backend usa invece `InfoCard`; sostituisce il
vecchio pattern `prettyInfo()` senza HTML scritto nella view:

```php
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\InfoCard;

(new Container())
    ->columns(3)
    ->components([
        InfoCard::make('Locali', $IMMOBILE->locali),
        InfoCard::make('Camere da letto', $IMMOBILE->camere),
        InfoCard::make('Bagni', $IMMOBILE->bagni),
    ]);
```

Solo `null` e una stringa vuota usano il placeholder `--`: `0` e `'0'`
restano valori validi. Il placeholder e il livello del valore sono
configurabili con `->placeholder('n/d')` e `->valueLevel(4)`.

Per un KPI usa `MetricCard`, che sostituisce `wiCardStats()`:

```php
use Wonder\Elements\Components\MetricCard;

MetricCard::make('Fatturato', 120)
    ->unit(' EUR')
    ->compareTo(100);

MetricCard::make('Churn', 4.2)
    ->displayValue('4,2')
    ->unit('%')
    ->compareTo(5.1)
    ->lowerIsBetter()
    ->deltaPrecision(1);
```

`compareTo()` calcola il delta sul valore numerico originale; `displayValue()`
permette di presentarne una versione gia formattata. `higherIsBetter()` e il
default, mentre `lowerIsBetter()` inverte solo il significato del colore: la
freccia continua a rappresentare la direzione reale. Una baseline pari a zero
non causa divisioni per zero e mostra un delta non definito (`--`). Valore,
titolo, unita, tooltip e attributi vengono sempre escapati.

Entrambe le card sono componenti backend Bootstrap e supportano
`columnSpan()`. Nel `ResourceFormLayoutRenderer` la griglia possiede l'unico
wrapper `col-*`; per esempio, dentro `columns(3)` ogni card predefinita riceve
`col-4`. Nel rendering Bootstrap diretto, invece, `col-span-*` viene applicato
alla card stessa, senza un ulteriore contenitore. Essendo un renderer backend,
`ResourceFormLayoutRenderer` risolve esplicitamente tutti gli Element figli con
il tema Bootstrap, indipendentemente dal tema globale attivo.

## Esempio: Alert

```php
use Wonder\Elements\Components\Alert;

Alert::make('Operazione completata', 'success')
    ->title('Fatto')
    ->dismissible();
```

`Alert::make($message, $level = 'info')`; poi `->title()`, `->message()`,
`->level()`, `->dismissible()`. Livelli tipici: `info`, `success`, `warning`,
`danger`.

## Esempio: Button, Badge, Group, Dropdown

```php
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\ButtonGroup;
use Wonder\Elements\Components\Dropdown;

Button::make('Salva')
    ->variant('success')
    ->icon('bi bi-check2', 'start');

Button::post('/backend/publish/', 'Pubblica')
    ->variant('primary')
    ->icon('bi bi-cloud-arrow-up', 'start')
    ->confirm('Pubblicare ora?');

Badge::make('Bozza')
    ->variant('secondary')
    ->outline();

ButtonGroup::make([
    Button::make('Annulla')->variant('secondary')->outline(),
    Button::make('Pubblica')->variant('primary'),
    Dropdown::make('Altro')
        ->variant('secondary')
        ->outline()
        ->item('Duplica', '/duplicate')
        ->item('Esporta CSV', '/export.csv', ['blank' => true])
        ->divider()
        ->button('Elimina', [
            'attributes' => ['onclick' => "confirm('Eliminare?')"],
        ]),
])->label('Azioni record');
```

API principali:

- `Button`: `post()`, `variant()`, `outline()`, `size()`, `type()`, `confirm()`,
  `formAttributes()`, `disabled()`,
  `active()`, `block()`, `nowrap()`, `icon()`, `arrow()`, `href()/blank()`,
  `target()`, `rel()`, `title()`, `onclick()`, `download()`
- `Badge`: `variant()`, `outline()`, `pill()`, `icon()`, `href()/blank()`,
  `target()`, `rel()`, `title()`, `onclick()`, `download()`
- `ButtonGroup`: `components()`, `add()`, `label()`, `toolbar()`,
  `vertical()`, `size()`
- `Dropdown`: `variant()`, `outline()`, `size()`, `direction()`, `align()`,
  `item()`, `button()`, `divider()`, `header()`, `text()`
- `Link`: `href()`, `blank()`, `target()`, `rel()`, `title()`, `onclick()`,
  `download()`, `icon()`, `muted()`
- `Text::link(...)`: stesse opzioni del concern link condiviso, più `icon`,
  `class`, `muted`, `attributes`

## Layout dei media

Tutti i media supportano `columnSpan(int|array)` tramite la base comune
`Elements/Media/Media`. Il contenitore di colonna e strettamente opt-in:
senza una chiamata esplicita a `columnSpan()` il renderer restituisce il media
senza alcun wrapper aggiuntivo.

```php
echo Image::src('/assets/upload/cover.jpg')->render();
// <img ...> oppure <picture>...</picture>

echo Image::src('/assets/upload/cover.jpg')
    ->columnSpan(6)
    ->render();
// Wonder:   <div class="col-6">...</div>
// Bootstrap:<div class="col-span-6">...</div>
```

Per i componenti con piu nodi, il wrapper racchiude l'intero frammento: video
e filtro, gallery e script, oppure Swiper principale, thumbnails e script.
Nel tema Wonder gli span responsive sono proiettati sulle classi disponibili
`col-*`, `col-t-*`, `col-p-*`; Bootstrap emette la classe desktop realmente
disponibile `col-span-*`.

Per applicare una utility che deve stare sul genitore diretto del media, come
il `ratio` Bootstrap di un iframe, usa un `Container` con `noGrid()`:

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

Quando il Container e figlio di un layout Resource resta il solo wrapper
esterno `col-*`; quello interno conserva classi, `id`, stili e attributi custom,
ma non riceve `row` o `g-*`. Se invece il Container `noGrid()` viene passato
direttamente a `ResourceFormLayoutRenderer::renderLayout()`, viene renderizzato
come unico wrapper radice, senza una `row` esterna. Non impostare
`columnSpan()` sull'iframe in questo caso: senza span il tag resta figlio
diretto di `.ratio`.

## Collegamenti con il resto

- I campi dentro le Card sono sempre `FormInput`/`FormField`: vedi
  [Form](../form/README.md).
- Il layout della tabella usa una cornice analoga (`TableLayoutSchema`): vedi
  [Tabelle](../tabelle/tablecolumn.md).
- I componenti rispettano il design system `wonder-image/lib` e i pattern
  Wonder esistenti (`.btn`, `.badge`, `.btn-group`, `.wi-dropdown-*`,
  `.wi-alert`): non inventare nuovi nomi `.wi-*` a livello framework.

## Errori comuni

- **`getInput('campo')` per un campo assente** → eccezione
  `Input resource non trovato`. Il campo deve stare in `formSchema()`.
- **Colonne sballate** → `columns()` del contenitore e `columnSpan()` dei figli
  devono essere coerenti.
- **`ratio` che non mantiene le proporzioni** → applicalo a un
  `Container::noGrid()`, lasciando il media figlio senza `columnSpan()`.
- **HTML a mano per un riquadro** → usa `Card`/`Container`; per coppie
  etichetta/valore e KPI usa `InfoCard`/`MetricCard`.
- **CTA o badge scritti a mano** → usa `Button` / `Badge` / `ButtonGroup` /
  `Dropdown`, non markup ad-hoc nel Resource o nella view.

`Button::post($action, $label)` rende un `<form method="post">` con un vero
`<button type="submit">`. `Button::to($action, $label)->type('post')` è
equivalente. Usa `->confirm($message)` per la conferma e `->formAttributes()`
solo per attributi aggiuntivi del form.

## Charts

Per i grafici (LineChart, PieChart su Chart.js) vedi la pagina
[Charts](charts.md).

## Swiper e Gallery

Per i caroselli di immagini (`__swiper()` con thumbnails + zoom Panzoom o
lightbox Fancybox) e le gallery responsive (`__gallery()`, che sostituisce la
vecchia `responsiveGallery()`) vedi la pagina
[Swiper e Gallery](swiper-e-gallery.md).

## Video e Iframe

Per video HTML5 con poster, source WebM opzionale e avvio configurabile, oppure
iframe con object fit coerente tra Wonder e Bootstrap, vedi
[Video e Iframe](video-e-iframe.md).

## Checklist

- [ ] layout in `formLayoutSchema()` con `Card`/`Container`
- [ ] campi via `static::getInput('campo')` (presenti in `formSchema()`)
- [ ] `columns()` / `columnSpan()` coerenti
- [ ] media senza wrapper salvo `columnSpan()` esplicito
- [ ] nessun markup di layout scritto a mano
