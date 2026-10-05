---
icon: puzzle-piece
---

# Componenti UI

## Cos'è

I **componenti** sono i blocchi con cui si compongono i layout di form e di
pagina nel backend, ma anche frammenti UI riusabili come bottoni, badge,
gruppi azioni e dropdown. Vivono in `class/Elements/Components/*` e si
combinano con i campi (`FormField`) per ottenere layout a colonne o CTA
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
| `Modal` | `Elements/Components/Modal.php` | finestra Bootstrap con campi e bottoni (solo backend) |
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
| `Swiper` | `Elements/Media/Swiper.php` | carosello immagini o contenuti HTML/componenti |

I metodi di composizione arrivano da Concerns riusabili:

- `components(array)` — figli del contenitore (`Concerns/IsContainer.php`)
- `columns(int|array)` — numero di colonne (`Concerns/HasColumns.php`)
- `columnSpan(int|array)` — quante colonne occupa (`Concerns/CanSpanColumn.php`)
- `Container::noGrid()` — usa il Container come wrapper puro, senza classi
  `row`/gutter generate dal layout Bootstrap
- `Container::masonry(int $columns = 2, string $minWidth = '22rem', string $gap = '1rem')`
  — i figli si impilano dall'alto in basso e riempiono le colonne (multi-colonna
  CSS) invece di allinearsi per righe: niente buchi quando le schede hanno
  altezze diverse. Sotto `$minWidth` il contenuto torna a una colonna sola,
  senza media query. Ogni figlio resta intero, senza spezzarsi tra due colonne.
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

`<wi-card>` è dismesso: non aggiungerne di nuovi né chiamare
`wiCard()`/`wiCardLink()` in codice nuovo, usa `Card`, che rende
`<div class="card">` e non ha opzioni per cambiare tag. Le occorrenze esistenti
si sostituiscono quando si tocca la pagina.

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

## Accordion

`Accordion` usa lo stesso Element per il backend Bootstrap e per il frontend
Wonder. Titolo e descrizione semplice possono essere passati direttamente:

```php
use Wonder\Elements\Components\Accordion;

Accordion::make('Come funziona la spedizione?')
    ->description(
        'Prepariamo e affidiamo il pacco al corriere entro due giorni lavorativi.'
    )
    ->icon('chevron')
    ->titleSize('text')
    ->descriptionSize('text-small')
    ->expanded();
```

Nel tema Wonder il renderer riusa il dropdown collassabile della lib:
`wi-dropdown-box`, `wi-dropdown-title wi-switcher` e
`wi-dropdown-content`. `expanded(true)` aggiunge `wi-show` al box e rende
subito l'icona aperta corretta.

Nel renderer Wonder le varianti di `icon()` sono:

- `plus`: `bi-plus` da chiuso, `bi-dash` da aperto;
- `chevron`: `bi-chevron-down` da chiuso, `bi-chevron-up` da aperto;
- `plus-lg`: `bi-plus-lg` da chiuso, `bi-dash-lg` da aperto.

Sono accettati anche gli alias Bootstrap Icons completi, per esempio
`->icon('bi bi-plus-lg')`. Sempre nel renderer Wonder, i preset ammessi da
`titleSize()` e `descriptionSize()` sono `title-big`, `title`, `subtitle`,
`text` e `text-small`; una stringa vuota ripristina lo stile nativo del
componente. Bootstrap mantiene invece indicatore e tipografia nativi.

### Accordion dentro un form di Resource

Un accordion può contenere i campi di un form, ed è il modo di dare a una
scheda dei riquadri che si chiudono. Serve dichiarare le colonne, come su una
`Card`:

```php
(new Accordion('Scheda tecnica'))
    ->columns(12)
    ->columnSpan(12)
    ->components([
        static::getInput('material')->columnSpan(6),
        static::getInput('country')->columnSpan(6),
    ]);
```

`ResourceFormLayoutRenderer` lo tratta come un contenitore, al pari di `Card` e
`Container`: rende i figli da sé, dando a ognuno la larghezza calcolata sulle
colonne dell'accordion, e chiede al tema solo la cornice. Senza `columns()` il
corpo resta quello di sempre e i figli prendono tutta la larghezza — che è
anche il motivo per cui un accordion di solo testo non cambia aspetto.

Se l'accordion porta `visibleWhen()`/`hiddenWhen()`, le regole passano alla
colonna che lo contiene: a sparire è lei, e la riga non tiene il margine di una
colonna vuota.

### Accordion a link

Dentro un riquadro già incorniciato un secondo bordo pesa. `link()` rende il
titolo come il «Compila le informazioni avanzate» del repeater — un bottone di
testo con `bi-chevron-down` — e sotto il corpo senza cornice, sempre con il
collapse di Bootstrap:

```php
Accordion::make('Compila le informazioni avanzate')
    ->link()
    ->columns(12)
    ->columnSpan(12)
    ->components([
        static::getInput('sku')->columnSpan(6),
        static::getInput('ean')->columnSpan(6),
    ]);
```

Il nodo porta la classe `wi-accordion-link`: con il CSS di `wonder-image/lib`
la freccia gira quando si apre; senza, resta ferma e il resto funziona.
`link()` è una variante del renderer Bootstrap, come `flush()`.

La descrizione stringa viene escapata. Per contenuti strutturati si possono
aggiungere Element figli, renderizzati con lo stesso tema richiesto
all'accordion:

```php
use Wonder\Elements\Components\Alert;

Accordion::make('Serve aiuto?')
    ->components([
        Alert::make('Contattaci e ti risponderemo al più presto.')
            ->dismissible(false),
    ]);
```

`flush()` resta una variante del renderer Bootstrap. Nel tema Wonder non
serve CSS o JavaScript aggiuntivo: il toggle e lo scambio icona sono già
forniti da `wonder-image/lib`; il sito deve includere Bootstrap Icons.

## Modal

`Modal` è una finestra Bootstrap che si scrive nel layout di una Resource come
un `Accordion`: un titolo, un corpo a griglia con i suoi campi e i bottoni in
fondo. La apre un bottone fra i campi con `opensModal()` (vedi
[FormField → button()](../form/form-field.md#testo)), anche dalle righe di un
repeater.

```php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Modal;

Modal::make('Costo del fornitore')
    ->id('wi-cost-modal')
    ->size('lg')
    ->columns(12)
    ->components([
        FormField::key('wi_cost_code')->text()->label('Codice fornitore')->columnSpan(6),
        FormField::key('wi_cost_price')->price()->label('Costo')->columnSpan(6),
    ])
    ->footer([
        Button::make('Annulla')->variant('secondary')->attr('data-bs-dismiss', 'modal'),
        Button::make('Salva')->attr('data-wi-cost-save', 'true'),
    ]);
```

| Metodo | Effetto |
|---|---|
| `make(string $title)` | titolo nell'intestazione (`h5.modal-title[data-wi-modal-title]`, escapato) |
| `id(string)` | l'id della finestra, quello di `opensModal()`; senza, `wi-modal-<casuale>` |
| `size('sm'\|'lg'\|'xl')` | larghezza del dialogo; altri valori lanciano `InvalidArgumentException` |
| `scrollable()` | il corpo scorre, intestazione e bottoni restano fermi |
| `columns()` / `gap()` | la griglia del corpo (`modal-body row g-3`), come su una `Card` |
| `components(array)` | i campi e i componenti del corpo |
| `footer(array)` | i bottoni in fondo, in fila (`Components\Button`, senza colonna); se c'è, prende il posto di `cancel()` / `submit()` |
| `form(string $action, string $method = 'post', array $hidden = [])` | corpo e bottoni in un `<form>` con token CSRF e un `<input type="hidden">` per campo; metodi `get` e `post`, valori nascosti scalari o `null` |
| `cancel(string $label = '')` | il bottone Annulla, che chiude la finestra (`components.buttons.cancel`) |
| `submit(string $label = '', string $variant = 'primary')` | il bottone Salva, `type="submit"` (`components.buttons.save`) |
| `help(string $text)` | un'icona con tooltip accanto al titolo |
| `dialogClass()`, `headerClass()`, `titleClass()`, `bodyClass()`, `footerClass()` | classi aggiunte a quelle del tema su quella parte |

Il markup è `.modal.fade[tabindex=-1][data-wi-modal-detach]` →
`.modal-dialog.modal-dialog-centered` → `.modal-content` con
`.modal-header`, `.modal-body` e `.modal-footer`.

Tre cose da sapere:

- **Niente `<form>` dentro, se non lo chiedi.** La finestra nasce nel form
  della Resource e un form annidato il browser lo butta via. I bottoni del
  fondo sono `type="button"`: a leggere e scrivere i campi è uno script della
  pagina, che trova la riga che ha aperto la finestra in
  `event.relatedTarget` di `show.bs.modal`. Una finestra con `form()` va
  invece resa fuori da ogni form (vedi sotto).
- **Esce dal form.** Uno script, stampato una volta per pagina, sposta ogni
  `.modal[data-wi-modal-detach]` in fondo al `body` al `DOMContentLoaded`:
  i campi della finestra non partono con il record. È lo stesso passo della
  [creazione rapida](../form/quick-create.md).
- **Nessuna colonna attorno.** `ResourceFormLayoutRenderer` rende il corpo con
  le colonne della finestra (un campo con `columnSpan(6)` su `columns(12)`
  diventa `col-6`) ma non mette la finestra in una colonna della scheda. Anche
  `renderLayout()` la tratta così. I campi del corpo ricevono valori ed errori
  come gli altri, perché `ResourcePagePresenter` scende nei `components`.

Sul tema Wonder la finestra esce solo con `frontend()` e un `id()` esplicito
e usa la `wi-modal` della lib; senza `frontend()` il renderer restituisce una
stringa vuota, così una scheda condivisa fra i due temi resta in piedi.

### Modal con form

Con `form()` la finestra invia da sé: corpo e bottoni stanno in un `<form>`
con il token CSRF (`Csrf::fieldFor()`, solo per POST e con una sessione
attiva) e i campi nascosti. In fondo arrivano Annulla e poi Salva, anche
senza chiamare `cancel()` e `submit()`; `footer()` li sostituisce del tutto.

```php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Elements\Components\Modal;

Modal::make('Registra pagamento')
    ->id('pay-12')
    ->help('Il pagamento resta modificabile fino alla chiusura.')
    ->form(action: $url, hidden: ['order_id' => 12])
    ->columns(12)
    ->components([FormField::key('amount')->price()->label('Importo')->required()->columnSpan(6)])
    ->cancel('Indietro')
    ->submit('Registra', variant: 'success');
```

| | Bootstrap | Wonder (`frontend()`) |
|---|---|---|
| form | `<form method action>` fra `.modal-header` e la fine di `.modal-content`; con `scrollable()` anche `d-flex flex-column overflow-hidden`, così il corpo scorre | `<form class="wi-modal-form" method action>` attorno a `.wi-modal-body` e `.wi-modal-footer` |
| Annulla | `btn-outline-secondary`, `type="button"`, `data-bs-dismiss="modal"` | `btn-dark-o` come i dialoghi della lib, `type="button"`, `wi-close-modal` |
| Salva | `btn-<variant>`, `type="submit"` | `btn-<variant>`, `type="submit"` |
| `help()` | componente `Tooltip` subito dopo l'`h5`, fuori dal titolo che gli script possono riscrivere | `span.wi-modal-help[data-wi-toggle="tooltip"]` nell'`h2`, con `tabindex="0"` e `aria-label`; `data-wi-title` è escapato due volte perché la lib lo scrive come HTML |

Una `Modal` con `form()` dentro il layout di una Resource lancia
`LogicException`: il suo `<form>` finirebbe annidato in quello della Resource.
Rendila fuori, per esempio nei `page_modals` delle pagine account, e aprila
con `Button::opensModal($id)`. Il token esce da solo, ma la verifica resta del
handler: chiama `Csrf::verify()` (vedi [CSRF](../form/csrf.md)).

### Classi delle parti

`Modal`, `Dropdown` hanno un metodo per ogni parte interna che possiedono;
le classi passate si aggiungono a quelle del tema, nello stesso attributo
`class`:

| Componente | Metodi | Parte |
|---|---|---|
| `Modal` | `dialogClass()` | `.modal-dialog` / `.wi-modal-content` |
| | `headerClass()`, `titleClass()` | intestazione e titolo |
| | `bodyClass()`, `footerClass()` | corpo e fondo |
| `Dropdown` | `toggleClass()` | il bottone che apre il menu |
| | `menuClass()` | `.dropdown-menu` / `.wi-dropdown-list` |
| | `itemClass()` | ogni voce cliccabile (link, bottone, POST) |

Le classi della radice restano su `addClass()`. Un nuovo componente con parti
usa `Elements\Concerns\HasPartAttributes` (metodi `protected`, uno pubblico
per parte) e, nei renderer, `Themes\Concerns\RendersPartAttributes`
(`partAttributes()`, `partClass()`).

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
        ->action('Copia link', ['data-copy' => '/p/12'])
        ->divider()
        ->item('Elimina', '/backend/delete/12', [
            'method' => 'post',
            'variant' => 'danger',
            'confirm' => 'Eliminare il record?',
            'confirm_ok' => 'Elimina',
        ]),
])->label('Azioni record');
```

Le conferme passano dalla lib (`wi.confirm`, attributi `data-wi-confirm*`):
niente `window.confirm` né `onclick` scritti a mano.
`Button::confirm($message, title: null, ok: null, variant: null)` mette la
conferma sul form di `post()` e, per gli altri bottoni e link, sul tag
stesso; la lib usa `danger` come variante di default per i POST e
`primary` per il resto.

Opzioni di una voce del `Dropdown` (`item()`, `button()`, `action()`):

| Opzione | Effetto |
|---|---|
| `method` | `get` o `post`; `post` rende un form con token CSRF, `action` uguale all'href e un `<button type="submit">` come voce. Un href vuoto lancia `InvalidArgumentException` |
| `confirm`, `confirm_title`, `confirm_ok`, `confirm_variant` | la conferma della lib, sul form per le voci POST e sul tag per le altre |
| `variant` | colore della voce: `text-<variant>` in Bootstrap, `tx-<variant>` in Wonder |
| `active`, `disabled`, `icon`, `title`, `target`, `rel`, `blank`, `attributes` | come prima; una `class` negli `attributes` entra nello stesso attributo della voce |

`action($label, $attributes = [], $options = [])` è una voce
`<button type="button">` senza href, per le azioni JavaScript; `text()` è una
voce di solo testo (`dropdown-item-text` in Bootstrap).

API principali:

- `Button`: `post()`, `variant()`, `outline()`, `size()`, `type()`,
  `confirm($message, title:, ok:, variant:)`,
  `formAttributes()`, `disabled()`,
  `active()`, `block()`, `nowrap()`, `icon()`, `arrow()`, `href()/blank()`,
  `target()`, `rel()`, `title()`, `onclick()`, `download()`
- `Badge`: `variant()`, `outline()`, `pill()`, `icon()`, `href()/blank()`,
  `target()`, `rel()`, `title()`, `onclick()`, `download()`
- `ButtonGroup`: `components()`, `add()`, `label()`, `toolbar()`,
  `vertical()`, `size()`
- `Dropdown`: `variant()`, `outline()`, `size()`, `direction()`, `align()`,
  `item()`, `button()`, `action()`, `divider()`, `header()`, `text()`,
  `toggleClass()`, `menuClass()`, `itemClass()`
- `Modal`: `form()`, `cancel()`, `submit()`, `help()`, `dialogClass()`,
  `headerClass()`, `titleClass()`, `bodyClass()`, `footerClass()`
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

- I campi dentro le Card sono sempre dichiarati con `FormField`: vedi
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
equivalente. Il form porta il token CSRF. Usa
`->confirm($message, title: ..., ok: ..., variant: ...)` per la conferma della
lib (`data-wi-confirm*` sul form) e `->formAttributes()` solo per attributi
aggiuntivi del form.

Due cose da sapere con la [barra di salvataggio](../form/save-bar.md):

- **Mai dentro il form di una Resource.** Il browser butta via il `<form>`
  annidato e il bottone invia il form della Resource. Mettilo nelle azioni
  della tabella o fuori dal form.
- **Due domande se il form è sporco.** Fuori dal form della Resource,
  `Button::post` chiede prima la sua `confirm()`; se il form della Resource ha
  modifiche non salvate, poi arriva anche la conferma della barra
  (`labels.confirmOther`). È voluto.

## Charts

Per i grafici (LineChart, PieChart su Chart.js) vedi la pagina
[Charts](charts.md).

## Swiper e Gallery

Per i caroselli di immagini o componenti HTML (`__swiper()` / `->slides()`),
con breakpoint responsive, ratio per immagini/miniature, classi slide e opzioni
immagine come thumbnails, zoom Panzoom o lightbox Fancybox, e per le gallery
responsive (`__gallery()`, che sostituisce la vecchia `responsiveGallery()`), vedi la pagina
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
