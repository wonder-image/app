---
icon: pen-field
---

# FormField

## Cos'è

`FormField` (`class/App/ResourceSchema/FormField.php`) è il DSL dei campi: una
facade con ~45 type-helper a catena (`text()`, `select()`, `fileDragDrop()`, …).
È anche il punto d'ingresso canonico nei `formSchema()`:
`FormField::key('nome')`.

Il type-helper **sceglie il tipo e cambia la classe dell'oggetto**: restituisce
l'istanza `Inputs\Input*` corrispondente (`->number()` → `InputNumber`,
`->select()` → `InputSelect`, …), che espone i soli modificatori sensati per
quel tipo. Da lì in poi l'autocomplete smette di suggerire `options()` su un
numero o `maxFile()` su una password.

```php
FormField::key('prezzo')->number()   // → Inputs\InputNumber
    ->decimals(2)                    // ✔ setter numerico
    ->required();                    // ✔ modificatore universale
    // ->options([...])              // ✘ non esiste su InputNumber
```

Chi preferisce partire già tipizzato può istanziare direttamente la classe:
`InputNumber::key('prezzo')->decimals(2)` è equivalente.

## A cosa serve

Dichiarare un input una volta e renderlo su entrambi i temi (Wonder frontend,
Bootstrap backend) con validazione, label ed errori coerenti.

## Dove si trova nel codice

- `class/App/ResourceSchema/Input.php` — base astratta con la sola **API
  universale**: `label`, `value`, `required`, `disabled`, `readonly`,
  `autocomplete`, `attribute`, `visibleWhen`, `hiddenWhen`, `error`,
  `inputName`, `storeAs`, `prepare`, `context`, `columnSpan`, `get`, `render`.
- `class/App/ResourceSchema/Inputs/Input*.php` — una classe per tipo, con i
  soli modificatori di quel tipo. I gruppi ricorrenti (opzioni, versione,
  formatting numerico, policy password, upload) stanno nei trait sotto
  `Inputs/Concerns/`.
- `class/App/ResourceSchema/FormField.php` — la facade coi type-helper, che
  passa da generico a tipizzato via `Input::morphInto()`.
- `class/App/ResourceSchema/RepeaterColumn.php` — specializzazione contestuale
  di `FormField` per i campi dentro un repeater.

## Esempio completo (copiabile)

```php
use Wonder\App\ResourceSchema\FormField;

public static function formSchema(): array
{
    return [
        FormField::key('name')->text()->required(),
        FormField::key('description')->textarea(),
        FormField::key('cover')->fileDragDrop('image', 'classic'),  // NON inputFileDragDrop
        FormField::key('visible')->select([
            'true'  => 'Visibile',
            'false' => 'Nascosto',
        ])->value('true')->required(),
    ];
}
```

{% hint style="danger" %}
Il metodo per il drag&drop si chiama **`fileDragDrop()`**, non
`inputFileDragDrop()`. Quest'ultimo **non esiste** e produce un errore "method
not found". L'helper imposta internamente `helper = 'inputFileDragDrop'`, ma il
metodo pubblico è `fileDragDrop()` (`FormField.php:311`).
{% endhint %}

## Type-helper disponibili

Tutti chainable su `FormField::key($name)`:

### Testo

`text()`, `hidden()`, `email()`, `tel()` / `phone()` (alias), `url()`,
`number()`, `price()`, `percentige()`, `color()`, `icon()`, `password()`,
`textGenerator($callback = null, $buttonLabel = null)` (input + bottone "GENERA"),
`button($text = null)` (un bottone fra i campi, con una didascalia accanto).

`text()` accetta `maxLength($n)`: l'input esce con l'attributo `maxlength`
in entrambi i temi, così il browser non lascia scrivere oltre. È solo un limite
di interfaccia: il controllo vero sulla lunghezza resta sul Model. Se passi già
`->attribute('maxlength="10"')` vince quello e l'attributo non esce due volte.

```php
FormField::key('sku')->text()->maxLength(32);
```

`button()` è un `<button type="button">` senza `name`: non posta niente. Il
nome serve solo a trovarlo nello schema e a leggerne il value, che diventa la
didascalia accanto al bottone, escapata; `emptyCaption()` è quella da mostrare
quando il value è vuoto. La didascalia esce sempre, anche vuota, così uno
script la può riempire: la si trova con `[data-wi-button-caption]`, l'unico
segno uguale in tutti i temi. Le classi cambiano col tema (`small
text-body-secondary` in Bootstrap, `text-small` in Wonder), e un selettore o
un test scritto su quelle funziona in un tema solo. Di
default il bottone è `outline-secondary`; `opensModal($id)` gli mette
`data-bs-toggle="modal" data-bs-target="#$id"` per aprire una
[finestra](../componenti/README.md#modal), e gli attributi dati con
`attribute()` arrivano al bottone. Funziona in una `Card`, in un `Accordion` e
come colonna di un repeater, anche fra le informazioni avanzate: ogni riga ha
il suo bottone e la sua didascalia.

```php
FormField::key('cost_summary')->button('Costo')
    ->icon('bi bi-truck')
    ->emptyCaption('Nessun fornitore')
    ->opensModal('wi-cost-modal')
    ->columnSpan(4);
```

`icon()` è un nome di Bootstrap Icons (`bi-star`): a sinistra l'anteprima, a
destra un bottone che apre la raccolta con la ricerca (anche in italiano, lo
monta la lib con `data-wi-icon-picker`). Il valore si normalizza con
`OptionVisual::icon()`: minuscolo, `bi-` aggiunto se manca, e vuoto se non è un
nome valido — un'icona sconosciuta mostra `bi-question-square`.

#### Formatting numerico

Su `number()`, `price()` e `percentige()` (i tre condividono l'Element
`InputNumber`) sono chainable i setter di formatting, mirror dell'API di
`Wonder\Elements\Form\Components\InputNumber`:

- `decimal($n)` — cifre decimali mostrate.
- `integer()` — numero intero, nessuna cifra decimale: equivale a `decimal(0)`.
- `decimalSeparator($sep)` — separatore dei decimali (es. `','`).
- `groupSeparator($sep)` — separatore delle migliaia (es. `'.'`).
- `symbol($sym)` — simbolo/valuta (es. `'€'`).
- `symbolPlacement('p'|'s')` — `p` = prefix, `s` = suffix (altri valori
  vengono ignorati).
- `suffix($testo)` — unità di misura in coda al numero (`suffix(' kg')` →
  «2,50 kg»): equivale a `symbol($testo)->symbolPlacement('s')`. Prende il posto
  del simbolo di valuta, quindi **non va usato sui prezzi** (toglierebbe il €).
- `decimals($n)` — valore passato allo schema dell'Element (distinto da
  `decimal()`).

Senza chiamate i tre tipi escono già nel formato italiano, impostato
dall'Element (quindi uguale nei due temi, nelle colonne dei repeater e negli
helper legacy):

| Tipo | Decimali | Migliaia | Simbolo | Esempio |
|---|---|---|---|---|
| `number()` | `,` | nessuno | — | `1299,50` |
| `percentige()` | `,` | nessuno | `%` in coda (dalla lib) | `12,50%` |
| `price()` | `,` | `.` | ` €` in coda | `1.299,90 €` |

Una configurazione esplicita vince sempre, anche quando è vuota:
`groupSeparator('')` toglie il punto delle migliaia al prezzo e `symbol('')`
gli toglie il €. Un `decimalSeparator('')` invece è ignorato, perché un numero
senza separatore decimale non si scrive.

Il formato è solo di visualizzazione: AutoNumeric al submit del form manda il
numero puro (`1299.9`) e il PHP non deve interpretare «1.299,90 €». Vale per il
submit nativo dei form del backend; un invio che costruisce `FormData` a mano
senza passare dall'evento `submit` manderebbe il valore formattato.

```php
FormField::key('prezzo')->price();                   // «1.299,90 €»
FormField::key('peso')->number()->suffix(' kg');     // «2,50 kg»
FormField::key('pezzi')->number()->integer();        // «12»
FormField::key('importo')->number()
    ->decimal(2)
    ->groupSeparator('.')
    ->symbol('€')
    ->symbolPlacement('p');                          // «€1.299,90»
```

### Date / ora

`textDate()`, `textDatetime()`, `dateInput($min = null, $max = null)`,
`dateRange($min = null, $max = null)`, `timeInput($step = 900)`.

### Area di testo

`textarea($version = null)` — passa una stringa di versione per abilitare
l'editor rich-text.

L'HTML scritto dall'editor si dichiara sul Model con `Field::richText()` (vedi
[Model e database](../risorse/database.md#testo-formattato-richtext)): il campo
esce dal `sanitize` in scrittura e in lettura e l'HTML passa dalla whitelist di
`SafeHtml`. Per questo sul `FormField` non serve più
`->prepare('sanitize', false)`: la Resource unisce il formato del Model a quello
del form, e `rich_text` fa saltare il `sanitize` anche se il form lo chiedesse.

### Scelta

`select($options, $version = null)`, `radio($options, $searchBar = false)`,
`selectSearch($options, $multiple = false, $version = null)`, `checkbox()`,
`checkTree($options, $searchBar = false, $inputType = 'checkbox')`,
`dynamicCheck($url, $inputType = 'checkbox')`,
`checkBoolean($values = ['', 'true', 'false'], $trueLabel = null, $falseLabel = null)`,
`toggle($on = 'true', $off = 'false')`.

`toggle()` è l'interruttore acceso/spento delle pagine di configurazione: ogni
riga è una scelta con la sua etichetta e una descrizione breve sotto.

```php
FormField::key('orders')->toggle()
    ->label('Ordini')
    ->description('Gestione degli ordini con stati e scarico del magazzino.');
```

Un campo nascosto manda il valore "spento" quando l'interruttore è staccato,
quindi il form trasmette sempre un valore; `values('si', 'no')` cambia i due
valori postati.

`textList($options, $version = null)` — combobox "text + list": campo di
testo con dropdown filtrabile lato client su una lista *statica* di opzioni
(`[value => label]`). Da preferire a `select()` quando le opzioni sono molte e
serve la ricerca senza chiamate remote. Tema Wonder: input + radio nascosto col
value; tema Bootstrap: degrada a `select` ricercabile (`data-wi-select-search`).

`searchText($url)`, `searchRadio($url)` — ricerca *remota*: la dropdown è
popolata via AJAX dall'endpoint `$url` (`SearchRemote`). `searchText` è a testo
libero, `searchRadio` a selezione singola. La ricerca remota è resa dal JS del
tema Wonder (frontend); in Bootstrap degrada a un input testuale.

### Geo

`country($stateField = null)`, `states($country = null)`, `phonePrefix()`,
`googleAddress($restriction = [], $alias = null)`.

### File

`file($accept = 'image')` (upload classico),
`fileDragDrop($accept = 'image', $uploader = 'classic')` (Filepond drag&drop).
`$accept` è la categoria (`image`, `pdf`, `video`, `font`, `media`).

### Ripetibili

`repeater([RepeaterColumn, ...])` — vedi [Repeater](repeater.md).

### Speciali

`acceptDocument($type)` (checkbox di consenso a un documento legale; il `name`
deve essere `accept_<type>`), `recaptcha($action = null, $theme = null, $size = null)`.

## Modificatori

### Universali

Chainable su **qualsiasi** campo, prima o dopo il type-helper — vivono sulla
base `Input`:

`.label($s)`, `.value($v)`, `.required()`, `.disabled()`, `.readonly()`,
`.autocomplete($b|$s)`, `.attribute($s)`, `.visibleWhen(...)`,
`.hiddenWhen(...)`, `.error($s)`, `.columnSpan($n)`, `.columnFill($b = true)`,
`.prepare($k, $v)`, `.context($k, $v)`, `.storeAs($s)`, `.inputName($s)`.

`.conditionalAttributes()` non modifica: restituisce le regole di
`visibleWhen()` / `hiddenWhen()` come data-attribute, per i renderer che le
devono ripetere su un contenitore. `.columnFill()` conta solo nelle colonne di
un repeater (vedi [Repeater](repeater.md#larghezza-delle-colonne)).

I campi senza `.label()` esplicita pescano l'etichetta da `labelSchema()` della
Resource.

**Larghezza di default.** Nel backend un campo senza `.columnSpan()` dentro
un `Card`/`Container` a più colonne prende **una** colonna (la stessa regola
di Filament): in un contenitore `->columns(12)` è un dodicesimo, con
l'etichetta che va a capo parola per parola. Chi deve occupare la riga intera
lo dichiara: `->columnSpan(12)`. Vale anche per i campi riusati nel layout di
un `quickCreate()`.

### Type-specific

Disponibili **solo dopo il type-helper**, sulla classe del tipo:

| Tipo | Classe | Modificatori |
|---|---|---|
| `text()` | `InputText` | `maxLength` |
| `number()`, `price()`, `percentige()` | `InputNumber`, `InputPrice`, `InputPercentige` | `decimal`, `integer`, `decimalSeparator`, `groupSeparator`, `symbol`, `symbolPlacement`, `suffix`, `decimals` |
| `password()` | `InputPassword` | `minLength`, `requireUppercase`, `requireLowercase`, `requireNumber`, `requireSpecial` |
| `select()`, `selectSearch()` | `InputSelect`, `InputSelectSearch` | `options`, `multiple`, `version`, `old` |
| `textarea()`, `textList()` | `InputTextarea`, `InputTextList` | `version`, `old` (+ `options` su textList) |
| `radio()`, `checkbox()` | `InputRadio`, `InputCheckbox` | `options`, `searchBar`, `pills` |
| `checkTree()` | `InputCheckTree` | `options`, `searchBar`, `inputType`, `primaryField` |
| `dynamicCheck()` | `InputDynamicCheck` | `url`, `inputType` |
| `searchText()`, `searchRadio()` | `InputSearchText`, `InputSearchRadio` | `url` |
| `checkBoolean()` | `InputCheckBoolean` | `values`, `trueLabel`, `falseLabel` |
| `toggle()` | `InputToggle` | `values`, `description` (più `label()` della base) |
| `file()`, `fileDragDrop()` | `InputFile`, `InputFileDragDrop` | `accept`, `maxFile`, `maxSize`, `extensions`, `multiple` (+ `uploader` sul drag&drop) |
| `textDate()`, `dateInput()`, `dateRange()` | `InputTextDate`, `InputDate`, `InputDateRange` | `dateMin`, `dateMax` |
| `timeInput()` | `InputTime` | `timeStep` |
| `country()`, `states()` | `InputCountry`, `InputStates` | `stateField` / `country` |
| `repeater()` | `InputRepeater` | `columns`, `nested`, `relation`, `repeaterAddLabel`, `repeaterButtonClass`, `repeaterDelete*`, `repeaterSortable` |
| `acceptDocument()` | `InputAcceptDocument` | `documentType` |
| `textGenerator()` | `InputTextGenerator` | `callback`, `buttonLabel` |
| `button()` | `InputButton` | `text`, `icon`, `variant`, `outline`, `size`, `emptyCaption`, `opensModal` |
| `recaptcha()` | `InputReCaptcha` | `action`, `theme`, `size` |
| `googleAddress()` | `InputGoogleAddress` | `restriction`, `alias` |

`hidden()`, `color()`, `icon()`, `email()`, `tel()`/`phone()`, `url()`,
`textDatetime()` e `phonePrefix()` hanno solo i modificatori universali.

{% hint style="warning" %}
Chiama sempre **prima il type-helper, poi i modificatori**. L'ordine inverso
(`->options([...])->select()`) resta accettato — `FormField` tiene i vecchi
modificatori come shim `@deprecated` — ma se il type-helper riceve lo stesso
valore come argomento il suo default **sovrascrive** quello impostato prima:
`->options([...])->select()` perde le opzioni. Vale per `options`, `version`,
`multiple`, `searchBar`, `dateMin`/`dateMax`, `accept`, `uploader`.
{% endhint %}

### Creazione rapida (`quickCreate`)

Sui campi **foreign key** (`select`, `selectSearch`, `checkbox`, `checkTree`,
`searchText`/`searchRadio`, `dynamicCheck`) puoi aggiungere un "+ Aggiungi" che
apre un modal per creare al volo la risorsa collegata:

```php
// Default: i campi obbligatori del target
FormField::key('category_id')->select($categorie)
    ->quickCreate(CategoryResource::class);
```

`quickCreate(string $resourceClass, ?array $fields = null, ?Closure $layout = null, ?string $label = null, ?string $button = null)`
— `$fields` `null` = campi obbligatori; `$layout` = pannello custom; `$button`
= testo del bottone e titolo del modal. API,
precondizioni (store API del target), permessi e adapter per tipo sono in
[Creazione rapida da campo FK](quick-create.md).

### Password policy

Su un campo `password()` puoi dichiarare la policy: `.minLength($n)`,
`.requireUppercase()`, `.requireLowercase()`, `.requireNumber()`,
`.requireSpecial()`. Le stesse API esistono sul `Field` del Model
(`Field::key('password')->password()->minLength(8)`), così la policy è coerente
tra form e validazione server-side.

## File gia salvati e riordino

Nel backend, `fileDragDrop()` conserva i file gia salvati: salvare senza
modificare le immagini o cambiarne l'ordine non deve inviarne nuovamente i
byte, rinominarle o rigenerare le varianti. Vengono caricati solo i file nuovi;
la rimozione esplicita elimina i file esclusi dall'elenco finale.

Questo comportamento richiede le versioni aggiornate sia del framework sia
di `wonder-image/lib`, inclusi gli asset copiati nel sito. Il renderer abilita
il protocollo con `data-wi-file-references="true"`. Al `formdata` la lib invia
un campo JSON fratello `<nome>__wi_files`: stringhe per i nomi gia salvati,
interi per gli indici dei nuovi upload. Il nome fratello resta nella stessa
riga per i repeater. Il server accetta riferimenti solo dai valori precedenti
del record e valida l'elenco prima di rimuovere file; `[]` indica rimozione
totale, mentre l'assenza del campo mantiene il comportamento legacy.

Gli handler personalizzati che chiamano `uploadFiles()` direttamente devono
passare il valore salvato come quarto argomento e il manifest come quinto.
Se un handler non supporta ancora il protocollo, il campo puo disabilitarlo
tramite l'attributo `data-wi-file-references="false"`.

## Collegamenti con il resto

- **Upload**: il campo file nel form configura solo la resa. La logica di storage
  (estensioni, dimensioni, cartella) sta sul **Model** in `dataSchema()` con
  `Field::key('cover')->upload()->image()` e `static::$folder`. Vedi
  [Model e Database](../risorse/database.md).
- **Layout**: per disporre i campi in Card/colonne usa `formLayoutSchema()` con
  `static::getInput('campo')`. Vedi [Componenti](../componenti/README.md).
- **Rendering**: come un `FormField` diventa HTML è spiegato in
  [Sistema Form / Theme / Element](theme-system.md).

## Visibilità condizionale

Un input può essere mostrato/nascosto in base al valore di un **altro campo** del
form, senza scrivere JavaScript: il toggle è gestito dal JS backend di
`wonder-image/lib` (`setConditional()`), quindi funziona con qualsiasi tipo di input.

```php
FormField::key('provider')->select(['getrix' => 'Getrix', 'gestim' => 'Gestim']);

// Mostrato solo se `provider` vale getrix o gestim
FormField::key('code')->text()->visibleWhen('provider', ['getrix', 'gestim']);

// Mostrato solo per gestim
FormField::key('site_id')->text()->visibleWhen('provider', 'gestim');

// Logica inversa: nascosto quando `provider` vale getrix
FormField::key('feed_url')->text()->hiddenWhen('provider', 'getrix');
```

- `->visibleWhen(string $field, string|array $values)` — mostra il campo solo
  quando il campo di riferimento assume uno dei valori indicati.
- `->hiddenWhen(string $field, string|array $values)` — logica inversa.

Sotto il cofano vengono aggiunti i data-attribute `data-visible-when` /
`data-hidden-when` (via `attribute()`), e la regola resta anche nello schema
del campo (`conditionalAttributes()`). Fuori dai repeater i renderer non la
usano. In una colonna di repeater il renderer Bootstrap la ripete sul
contenitore della colonna, che si nasconde tutto
(vedi [Repeater](repeater.md#colonne-che-compaiono-con-un-altro-campo)).
I campi nascosti **non** vengono disabilitati: i loro valori vengono comunque
inviati e salvati.

Nei layout del backend (`ResourceFormLayoutRenderer`) la colonna di un campo
condizionale si marca `data-wi-conditional-container`: la lib nasconde la
colonna intera e non solo la casella, così dentro una `row g-3` non resta il
margine di una colonna vuota. Per la stessa ragione un campo `hidden()` sta
direttamente nella riga, senza colonna.

### Opzioni con un segno

Nelle opzioni di `select()`, `checkbox()`, `radio()` una voce può essere un
array con, oltre a `name`, un segno da mostrare accanto all'etichetta:

```php
FormField::key('color')->checkbox()->pills()->options([
    '1' => ['name' => 'Rosso', 'color' => '#d33'],
    '2' => ['name' => 'Fantasia', 'image' => 'https://…/fantasia.webp'],
    '3' => ['name' => 'Vegano', 'icon' => 'bi-leaf'],
]);
```

Vale un segno solo, in quest'ordine: `image`, poi `icon`, poi `color`. Li
sceglie `OptionVisual::of()`, che li ripulisce anche — un colore solo
esadecimale, un'immagine solo `http(s)` o relativa e senza virgolette, un'icona
solo `bi-…` — e scarta quello che non passa. Spunte e pillole lo stampano
davanti all'etichetta; `select()` lo mette in `data-wi-image|icon|color`
sull'`<option>`, dove lo legge Select2 della lib.

### Spunte a pillole

`checkbox()->pills()` e `radio()->pills()` rendono le voci come pillole in
linea invece che incolonnate in un riquadro alto centoventi pixel che scorre.
Serve agli elenchi corti — cinque taglie, tre gusti — dove il riquadro occupa
dieci volte lo spazio di quello che mostra. Per un elenco lungo resta la forma
normale, con la sua barra di ricerca.

Sopra le pillole l'etichetta è un titolo piccolo; con `->label('')` il titolo
non esce, ed è la forma di una pillola sola, come il «Preferito» di una riga:

```php
RepeaterColumn::key('preferred')->radio(['1' => 'Preferito'])->pills()->label('');
```

Dentro un repeater la radio vuole le righe annidate (`->nested()`): senza, ogni
riga posta `preferred[]` e le radio di tutte le righe fanno un gruppo solo. Le
righe aggiunte con «Aggiungi» hanno id e `for` propri, come i `name` (vedi
[Repeater](repeater.md)). Un campo `required()` con `->label('')` resta senza
titolo: l'asterisco da solo non ne fa uno.

### La voce principale di un albero

`checkTree()->primaryField('main_category')` segna con una stella la voce
principale fra quelle spuntate, e ne tiene l'id nel campo indicato (di solito
un `hidden()` dello stesso form). La lib lo tiene aggiornato: la prima spunta
prende la stella, un clic sulla stella vuota di un'altra voce spuntata la
sposta, togliere la spunta alla principale la passa alla prima rimasta. Solo
per gli alberi a checkbox. Il server deve comunque ricontrollare che l'id stia
fra le voci spuntate.

### Chi elenca una risorsa

`listsResource(CategoryResource::class)` dichiara di quale risorsa un campo
elenca le righe. Serve al quick-create: una riga creata dal «+ Aggiungi» di un
campo appartiene alla risorsa, non a quel campo, e ogni altro campo della
pagina che la elenca deve vedersela comparire. Chi dichiara `quickCreate()` lo
ottiene da sé; questo metodo è per i campi che la risorsa la elencano
soltanto.

### Un riquadro intero

Gli stessi due metodi stanno anche sui componenti di layout (`Card`,
`Container`, `Accordion`): un interruttore che governa un blocco lo apre e lo
chiude tutto, invece che una casella alla volta.

```php
(new Card)->components([...])->columns(12)->columnSpan(12)
    ->visibleWhen('has_variants', 'true');
```

Il riquadro si marca da sé come contenitore, così il JS nasconde esattamente
lui e non un suo genitore. Card, Accordion e bottone di creazione rapida, dentro
il form di una Resource, portano le regole sulla loro colonna. Anche qui i campi dentro continuano a essere
inviati: se il "no" deve significare qualcosa, il server lo deve rileggere dal
POST e comportarsi di conseguenza.

## Errori comuni

- **`->inputFileDragDrop(...)`** → non esiste; usa **`->fileDragDrop(...)`**.
- **HTML di input scritto a mano** → vietato; modella sempre con `FormField`.
- **Modificatore chiamato prima del type-helper** → funziona ma può essere
  sovrascritto (vedi il riquadro sopra): metti sempre il type-helper per primo.
- **`Call to undefined method`** su un modificatore → stai chiamando un setter
  di un altro tipo (es. `options()` dopo `->number()`). Controlla la tabella dei
  modificatori type-specific.
- **Config upload nel form invece che nel Model** → estensioni/peso/cartella
  vanno in `dataSchema()`.
- **`getInput('campo')` per un campo non in `formSchema()`** → eccezione
  `Input resource non trovato`.

## Checklist

- [ ] import `use Wonder\App\ResourceSchema\FormField;`
- [ ] ogni input dichiarato con `FormField::key(...)`
- [ ] drag&drop con `fileDragDrop()` (non `inputFileDragDrop`)
- [ ] storage del file configurato nel Model (`dataSchema()` + `$folder`)
- [ ] nessun `<input>` HTML nelle view
