---
icon: pen-field
---

# FormField e FormInput

## Cos'è

`FormField` (`class/App/ResourceSchema/FormField.php`) è il DSL dei campi: una
classe con ~45 type-helper a catena (`text()`, `select()`, `fileDragDrop()`, …)
che mutano l'istanza e ritornano `self`. `FormInput` ne è la sottoclasse usata in
`formSchema()`: si parte sempre da `FormInput::key('nome')`.

## A cosa serve

Dichiarare un input una volta e renderlo su entrambi i temi (Wonder frontend,
Bootstrap backend) con validazione, label ed errori coerenti.

## Dove si trova nel codice

- `class/App/ResourceSchema/FormField.php` (estende `Input`, che tiene la
  "macchina" condivisa: label, attribute, prepare, context, render).
- `class/App/ResourceSchema/FormInput.php`.

## Esempio completo (copiabile)

```php
use Wonder\App\ResourceSchema\FormInput;

public static function formSchema(): array
{
    return [
        FormInput::key('name')->text()->required(),
        FormInput::key('description')->textarea(),
        FormInput::key('cover')->fileDragDrop('image', 'classic'),  // NON inputFileDragDrop
        FormInput::key('visible')->select([
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

Tutti chainable su `FormInput::key($name)`:

### Testo

`text()`, `hidden()`, `email()`, `tel()` / `phone()` (alias), `url()`,
`number()`, `price()`, `percentige()`, `color()`, `password()`,
`textGenerator($callback = null, $buttonLabel = null)` (input + bottone "GENERA").

#### Formatting numerico

Su `number()`, `price()` e `percentige()` (i tre condividono l'Element
`InputNumber`) sono chainable i setter di formatting, mirror dell'API di
`Wonder\Elements\Form\Components\InputNumber`:

- `decimal($n)` — cifre decimali mostrate.
- `decimalSeparator($sep)` — separatore dei decimali (es. `','`).
- `groupSeparator($sep)` — separatore delle migliaia (es. `'.'`).
- `symbol($sym)` — simbolo/valuta (es. `'€'`).
- `symbolPlacement('p'|'s')` — `p` = prefix, `s` = suffix (altri valori
  vengono ignorati).
- `decimals($n)` — valore passato allo schema dell'Element (distinto da
  `decimal()`).

Sono opt-in: senza chiamate i tre type rendono come prima.

```php
FormInput::key('prezzo')->number()
    ->decimal(2)
    ->decimalSeparator(',')
    ->groupSeparator('.')
    ->symbol('€')
    ->symbolPlacement('p');
```

### Date / ora

`textDate()`, `textDatetime()`, `dateInput($min = null, $max = null)`,
`dateRange($min = null, $max = null)`, `timeInput($step = 900)`.

### Area di testo

`textarea($version = null)` — passa una stringa di versione per abilitare
l'editor rich-text.

### Scelta

`select($options, $version = null)`, `radio($options, $searchBar = false)`,
`selectSearch($options, $multiple = false, $version = null)`, `checkbox()`,
`checkTree($options, $searchBar = false, $inputType = 'checkbox')`,
`dynamicCheck($url, $inputType = 'checkbox')`,
`checkBoolean($values = ['', 'true', 'false'], $trueLabel = null, $falseLabel = null)`.

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

## Modificatori comuni

Chainable su qualsiasi campo:

`.label($s)`, `.value($v)`, `.required()`, `.disabled()`, `.readonly()`,
`.multiple()`, `.attribute($s)`, `.options($a)`, `.searchBar($b)`,
`.columnSpan($n)`, `.error($s)`, `.prepare($k, $v)`, `.context($k, $v)`,
`.nested($b)`, `.version($s)`, `.file($type)`, `.uploader($name)`,
`.dateMin($s)`, `.dateMax($s)`, `.timeStep($n)`, `.maxSize($n)`, `.maxFile($n)`,
`.extensions($a)`, `.storeAs($s)`, `.inputName($s)`, `.relation($obj)`.

I campi senza `.label()` esplicita pescano l'etichetta da `labelSchema()` della
Resource.

### Password policy

Su un campo `password()` puoi dichiarare la policy: `.minLength($n)`,
`.requireUppercase()`, `.requireLowercase()`, `.requireNumber()`,
`.requireSpecial()`. Le stesse API esistono sul `Field` del Model
(`Field::key('password')->password()->minLength(8)`), così la policy è coerente
tra form e validazione server-side.

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
FormInput::key('provider')->select(['getrix' => 'Getrix', 'gestim' => 'Gestim']);

// Mostrato solo se `provider` vale getrix o gestim
FormInput::key('code')->text()->visibleWhen('provider', ['getrix', 'gestim']);

// Mostrato solo per gestim
FormInput::key('site_id')->text()->visibleWhen('provider', 'gestim');

// Logica inversa: nascosto quando `provider` vale getrix
FormInput::key('feed_url')->text()->hiddenWhen('provider', 'getrix');
```

- `->visibleWhen(string $field, string|array $values)` — mostra il campo solo
  quando il campo di riferimento assume uno dei valori indicati.
- `->hiddenWhen(string $field, string|array $values)` — logica inversa.

Sotto il cofano vengono aggiunti i data-attribute `data-visible-when` /
`data-hidden-when` (via `attribute()`); nessuna modifica ai renderer dei temi.
I campi nascosti **non** vengono disabilitati: i loro valori vengono comunque
inviati e salvati.

## Errori comuni

- **`->inputFileDragDrop(...)`** → non esiste; usa **`->fileDragDrop(...)`**.
- **HTML di input scritto a mano** → vietato; modella sempre con `FormInput`.
- **Config upload nel form invece che nel Model** → estensioni/peso/cartella
  vanno in `dataSchema()`.
- **`getInput('campo')` per un campo non in `formSchema()`** → eccezione
  `Input resource non trovato`.

## Checklist

- [ ] import `use Wonder\App\ResourceSchema\FormInput;`
- [ ] ogni input dichiarato con `FormInput::key(...)`
- [ ] drag&drop con `fileDragDrop()` (non `inputFileDragDrop`)
- [ ] storage del file configurato nel Model (`dataSchema()` + `$folder`)
- [ ] nessun `<input>` HTML nelle view
