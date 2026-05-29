# Form / Element / Theme system

Sistema di costruzione e rendering dei form basato su due strati separati:

- **Elements** (`class/Elements/Form/`) — oggetti di configurazione,
  fluent API, nessuna logica di rendering HTML.
- **Themes** (`class/Themes/{Wonder,Bootstrap}/Form/`) — renderer
  concreti che traducono un Element in HTML specifico per un tema.

La separazione permette di scrivere un form **una volta** e renderizzarlo
in 2 (o più) varianti grafiche: `Wonder` per il frontend pubblico,
`Bootstrap` per il backend admin.

## Le 3 cartelle

```
class/
├── Elements/Form/                 ← CONFIG layer (dichiarativo, no HTML)
│   ├── Field.php                  abstract base
│   ├── Form.php                   container
│   └── Components/
│       ├── InputText.php          fluent: placeholder(), maxLength(), pattern() ...
│       ├── InputEmail.php
│       ├── InputNumber.php / InputPassword.php / InputPrice.php / InputPercentige.php
│       ├── InputColor.php / InputTel.php / InputUrl.php / InputDatetime.php / InputTime.php
│       ├── Hidden.php
│       ├── Checkbox.php / CheckGroup.php
│       ├── Date.php / DatePicker.php / DateRange.php
│       ├── File.php
│       ├── Select.php             options(), placeholder()
│       ├── Textarea.php / TextareaEditor.php
│       └── Repeater.php           columns(), context()
│
├── Themes/Form/
│   └── AbstractFieldRenderer.php  ← base condivisa per Field di TUTTI i temi
│                                    (schema, hasError, hasValue, resolvedLabel,
│                                    render+renderField hook)
│
├── Themes/Wonder/Form/            ← RENDER layer per FRONTEND pubblico
│   ├── Field.php                  extends AbstractFieldRenderer
│   │                              (solo theme-specific: wi-* markup)
│   ├── Form.php                   container `<form class="wi-form">`
│   └── Components/                21 renderer concreti (NO Repeater — frontend non lo usa)
│
└── Themes/Bootstrap/Form/         ← RENDER layer per BACKEND admin
    ├── Field.php                  extends AbstractFieldRenderer
    │                              (form-floating, form-control, invalid-feedback)
    ├── Form.php
    └── Components/                22 renderer concreti (INCLUDE Repeater)
```

### `AbstractFieldRenderer` (base condivisa)

`class/Themes/Form/AbstractFieldRenderer.php` raccoglie tutto ciò che
sarebbe stato duplicato tra `Themes/Wonder/Form/Field` e
`Themes/Bootstrap/Form/Field`:

| Metodo | Responsabilità | Sovrascrivibile? |
|---|---|---|
| `render($class)` | Estrae lo schema dall'Element e chiama il pipeline | No (final-ish) |
| `renderInput()` | Markup dell'input — astratto | Sì, obbligatorio nei subclass |
| `renderField($input)` | Wrapping opzionale (default = identità) | Sì (Bootstrap lo usa per `form-floating`) |
| `hasError()` / `errorMessage()` | Stato errore di validazione | Raramente |
| `hasValue()` | Distingue "non impostato" da "valore presente" | No |
| `resolvedLabel()` | Label + `*` se required | No |
| Traits inclusi | `HasSchema`, `HasAttributes`, `HasIdentifier`, `EscapesHtml`, `CanSpanColumn` | — |

Restano nei subclass theme-specific solo `renderLabel()`, `renderError()`,
`inputClass()` (più `containerClass()` per Wonder, override `renderField()`
con form-floating per Bootstrap). Aggiungere un terzo tema = creare un
nuovo `Themes/<Nome>/Form/Field.php` che estende `AbstractFieldRenderer`
e implementa quei 3 metodi.

## Come si lega un Element al renderer

`Wonder\Themes\Resolver` fa il matching per **classe + tema** tramite
specchio di namespace:

```
Wonder\Elements\Form\Components\InputText
        ↓
Wonder\Themes\{NS}\Form\Components\InputText        ← NS = "Wonder" o "Bootstrap"
```

Il Resolver lavora **solo nel tema richiesto** (niente fallback
cross-theme). Per ogni tema cerca lungo la catena di parent class
dell'Element:

1. **Tema unico**: usa il tema esplicito passato a `render('bootstrap')`
   oppure quello attivo via `Theme::set()`. Niente fallback automatico
   su un altro tema: se il renderer richiesto non c'è in quel tema,
   il Resolver solleva eccezione. Comportamento intenzionale:
   fail-fast > markup di un tema sbagliato a sorpresa.
2. **Catena element-class**: classe concreta → parent class → ... .
   Se manca un renderer specifico per `InputText`, il Resolver prova
   `Field` (parent) come fallback. Questo è un meccanismo OOP standard,
   non un fallback cross-theme: lo stesso tema risponde con un renderer
   generico se uno specifico non esiste.

Se nessuna combinazione (tema corrente, gerarchia classe) produce un
renderer concreto non-astratto, viene sollevata `RuntimeException`
con la lista dei tentativi.

## Selezione del tema attivo

`Wonder\App\Theme` è il punto di controllo runtime:

```php
use Wonder\App\Theme;

Theme::set('bootstrap');       // attiva il tema per le successive render()
Theme::get();                  // 'bootstrap'
Theme::available();            // ['wonder', 'bootstrap']
```

Default = `'wonder'` (vedi `class/Themes/Registry.php:11`). Cambia
default con `Registry::setDefault('bootstrap')` se l'app è
primariamente backend.

Il tema può anche essere specificato **per singola render** senza
toccare lo stato globale:

```php
echo $field->render('bootstrap');     // rendering una-tantum in Bootstrap
echo $field->render();                // usa il tema attivo
```

## API consumer (esempi)

### Form semplice end-to-end

```php
use Wonder\Elements\Form\Form;
use Wonder\Elements\Form\Components\{ InputText, InputEmail, Select, Textarea };
use Wonder\App\Theme;

Theme::set('wonder');           // frontend pubblico

$form = new Form();
$form
    ->add(
        (new InputText('name'))
            ->label('Nome')
            ->placeholder('Mario Rossi')
            ->required()
            ->maxLength(80)
    )
    ->add(
        (new InputEmail('email'))
            ->label('Email')
            ->required()
    )
    ->add(
        (new Select('subject'))
            ->label('Motivo')
            ->options([
                'info' => 'Richiesta informazioni',
                'quote' => 'Richiesta preventivo',
                'other' => 'Altro',
            ])
            ->placeholder('Seleziona...')
            ->required()
    )
    ->add(
        (new Textarea('message'))
            ->label('Messaggio')
            ->required()
    );

echo $form->render();           // HTML "wi-*" classes
```

### Render dello stesso campo in entrambi i temi

```php
$emailField = (new InputEmail('email'))->label('Email')->required();

$frontendHtml = $emailField->render('wonder');     // <div class="wi-input ..."> ... </div>
$backendHtml  = $emailField->render('bootstrap');  // <div class="form-floating"> ... </div>
```

L'oggetto Element è lo stesso: due render diversi.

### Disattivare il "floating label" (`noFloating()`)

Di default ogni campo usa il pattern "floating label" (in Wonder la
label "galleggia" sopra l'input quando ha valore; in Bootstrap viene
applicato `<div class="form-floating">`). Per disattivarlo si usa
l'API `noFloating()` disponibile sia su singoli `Field` che sull'intero
`Form`.

#### Su singolo input

```php
$field = (new InputText('nick'))
    ->label('Nickname')
    ->noFloating();
```

Effetti theme-specific:
- **Wonder**: aggiunge `wi-nf` al `.wi-input-container`, il CSS frontend
  sopprime l'animazione della label.
- **Bootstrap**: salta il `<div class="form-floating">`. L'input resta
  con il suo `<label>` ma renderizzato in modo statico.

#### Su intero form (propagato ai children)

```php
$form = (new Form())
    ->noFloating()                                  // default: tutti no-floating
    ->components([
        (new InputText('name'))->label('Nome'),     // eredita no-floating
        (new InputEmail('email'))                   // override puntuale: rimane floating
            ->label('Email')
            ->noFloating(false),
    ]);
```

Regola di precedenza: la flag del Form viene applicata ai children
SOLO se il child non l'ha impostata esplicitamente. Quindi
`noFloating(false)` su un singolo input bypassa il default del Form.
La propagazione avviene a render-time nel renderer del Form (vedi
`Themes\Wonder\Form\Form::propagateNoFloating()` e l'equivalente
Bootstrap).

#### Quando si usa

- Form compatti / inline (es. barra di ricerca, login).
- Layout "non-floating" voluti dal design (form di registrazione molto
  lunghi dove la label sopra-statica è più leggibile).
- Component che concettualmente non hanno floating sensato (es.
  `Checkbox`, `File` — questi già usano internamente
  `renderField(input, false)` lato Bootstrap, l'API `noFloating()` è
  per i casi opt-in dell'utente).

### Repeater (solo backend Bootstrap)

```php
use Wonder\Elements\Form\Components\Repeater;
use Wonder\Elements\Form\Components\InputText;
use Wonder\App\Theme;

Theme::set('bootstrap');

$repeater = (new Repeater('languages'))
    ->label('Lingue parlate')
    ->columns([
        (new InputText('language'))->label('Lingua'),
        (new InputText('level'))->label('Livello'),
    ]);

echo $repeater->render();
```

Il `Repeater` non ha (volutamente) un renderer in `Themes/Wonder/Form/Components/`:
non ha senso a livello frontend pubblico. Se chiami `->render('wonder')`
su un Repeater il Resolver solleva un'eccezione esplicita coi tentativi.

## Bridge col vecchio sistema `ResourceSchema/FormSchema`

Coesistono due API:

1. **Vecchia (dichiarativa, alto livello)** in `class/App/ResourceSchema/`:
   `Resource::formSchema()` ritorna un `FormSchema` con `FormField`
   helpers (`text()`, `select()`, `inputRepeater()`, ...). Usata dai
   backend CRUD auto-generati dalle Resource.
2. **Nuova (oggetti, basso livello)** in `class/Elements/Form/` +
   `class/Themes/*/Form/`. Pensata per essere usata anche fuori dal
   contesto Resource (form di contatto frontend, wizard, ecc.).

Il ponte è `Wonder\App\Support\FormFieldElementFactory`:

```php
use Wonder\App\Support\FormFieldElementFactory;

$html = FormFieldElementFactory::render($formField, 'bootstrap');
```

Internamente:
- Legge `$formField->get('helper')` (`'text'`, `'select'`, `'inputRepeater'`, ...)
- Crea l'Element corrispondente (`InputText`, `Select`, `Repeater`, ...)
- Trasferisce label/value/error/attributes via `hydrate()`
- Chiama `$element->render($theme)`

Quindi puoi continuare a definire form con `Resource::formSchema()` e
ottenere automaticamente il rendering Element-based.

## Refactor `FormField` → `Input` + `Inputs/*`

Il vecchio `FormField` era una facade monolitica con ~45 type-helper
(`text()`, `password()`, `file()`, ...) e tutto lo schema/render mixato.
La direzione attuale ricalca il pattern di `Wonder\Data\Fields\*`:
classi dedicate per tipo, base condivisa.

### Gerarchia

```
class/App/ResourceSchema/
├── Input.php                          ← base astratta (label, attribute,
│                                        prepare, context, value, render,
│                                        __toString, ...)
├── FormField.php                      ← extends Input
│                                        mantiene i 45 type-helper (text, select,
│                                        file, password, acceptDocument, …)
│                                        per piena retrocompatibilità
├── InputSchema.php                    ← dispatcher mirror di Wonder\Data\UploadSchema
│                                        (InputSchema::key('p')->password()->...)
└── Inputs/
    ├── InputText.php                  ← extends Input, helper='text'
    └── InputPassword.php              ← extends Input, helper='password'
                                         + setters policy (vedi sotto)
```

Il `FormFieldElementFactory::render()` accetta una `Input` (type hint
comune), quindi sia il vecchio `FormField` che le nuove `Input*` sono
indifferenti al render.

### Le tre forme equivalenti

```php
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\InputSchema;
use Wonder\App\ResourceSchema\Inputs\InputPassword;

// 1. Legacy: FormField facade (retro-compatibile, copre tutti i tipi)
FormField::key('password')->password()->required()->minLength(8);

// 2. Diretto: classe Input dedicata
InputPassword::key('password')->required()->minLength(8);

// 3. Dispatcher: mirror del pattern Wonder\Data\UploadSchema
InputSchema::key('password')->password()->required()->minLength(8);
```

Tutte producono lo stesso HTML. La (1) resta la strada principale finché
il refactor non migra tutti i 45 helper. (2)/(3) servono per i tipi già
estratti — al momento `text`, `password`.

### Ordine di migrazione (informativo)

1. ✅ `Input` base + `InputText` + `InputPassword`
2. Da fare: `InputFile`, `InputAcceptDocument`
3. Da fare: choice family (`InputSelect`, `InputRadio`, `InputCheckbox`,
   `InputCheckTree`, `InputDynamicCheck`, `InputCheckBoolean`)
4. Da fare: date/time (`InputDate`, `InputTime`, `InputDateRange`)
5. Da fare: specialistici (`InputRepeater`, `InputGoogleAddress`,
   `InputReCAPTCHA`, `InputTextGenerator`, `InputCountry`, `InputStates`,
   `InputPhonePrefix`)

A ogni step gli helper rimasti su `FormField` continuano a funzionare —
zero big-bang. Quando l'ultimo tipo è migrato, `FormField::key()`
diventerà un dispatcher puro e `FormFieldElementFactory::make()` sparirà
(la logica di costruzione si sposterà in `Input*::buildElement()`
polimorfico).

## Password policy fluent

`InputPassword` (sia Element che ResourceSchema) espone setters per le
regole di policy:

| Setter | Effetto |
|---|---|
| `->minLength(int $n)` | Almeno N caratteri |
| `->requireUppercase()` | Almeno una maiuscola |
| `->requireLowercase()` | Almeno una minuscola |
| `->requireNumber()` | Almeno un numero |
| `->requireSpecial()` | Almeno un carattere non alfanumerico |

Esempio:

```php
FormField::key('password')->password()
    ->required()
    ->minLength(8)
    ->requireUppercase()
    ->requireNumber()
    ->requireSpecial()
    ->label(__t('components.forms.fields.password.label'))
```

Le regole vivono in `prepare['password_rules']`. Da lì vengono propagate
**a tre livelli** in un colpo solo:

1. **Render** — `class/Themes/Wonder/Form/Components/InputPassword.php`
   emette l'icona occhio (`togglePassword(...)`) e una `<ul
   class="wi-password-rules">` con `data-wi-rule="..."`. Il client
   `wonder-image/lib` switcha le icone `bi-x ↔ bi-check2` live mentre
   l'utente digita.
2. **Validazione server-side** — `Resource::prepareFormatFromInput()`
   copia `password_rules` in `format`. `formToArray()` (`app/function/sql.php`)
   istanzia un `Wonder\Data\Validators\PasswordPolicyValidator` con quel
   set di regole e setta `$ALERT = 977` se fallisce.
3. **Model-side** — `Wonder\Data\Fields\Password` ha gli stessi setters
   (`minLength`, `requireUppercase`, ...). Se preferisci dichiarare la
   policy nel `Model::dataSchema()` invece che nel `Resource::formSchema()`,
   il `PasswordPolicyValidator` viene aggiunto al field con `syncPolicyValidator()`.

Le stringhe vivono in `resources/lang/{it,en,fr,de,es}/components.json`
sotto `forms.password_rules.*` e `forms.password_errors.*`, con il
placeholder `{{count}}` (sintassi nativa del `TranslationProvider`, non
printf-style). Il renderer e il validator wrappano `__t()` in
try/catch così la pagina non si abbatte se un sito non ha le chiavi
aggiornate.

Codice di alert per il validator: `977` ("Password non valida") — definito
in `resources/lang/{lang}/notifications.json`.

## `acceptDocument` fluent

Checkbox di accettazione di un documento legale (privacy, terms, ...):

```php
FormField::key('accept_privacy_policy')->acceptDocument('privacy_policy')->required()
```

- Il `name` del field resta quello passato a `::key()` — deve essere
  `accept_<type>` perché il pipeline consent cerca esattamente quel
  prefisso nel POST.
- Il render produce **input checkbox** `accept_<type>` + **hidden**
  `<type>_id` con id e label HTML del documento attivo nella lingua
  corrente.
- La persistenza in `consent_events` è automatica: i Resource controller
  invocano `recordResourceConsents()` dopo `afterStore()` (vedi
  [Registrazione consensi](../app/utente/registrazione-consensi.md)).

## File: DataTransfer re-hydration

Quando un `FormField::key('cv')->file('pdf')` viene precompilato con un
`value` che punta a file già caricati, il renderer Wonder emette uno
`<script>` che ricostruisce `input.files` lato client via
`DataTransfer`:

```html
<div class="wi-input-container file compiled">
    <label for="input_abc" class="wi-label">CV</label>
    <input type="file" id="input_abc" name="cv[]" accept="application/pdf"
           data-wi-max-file="3" data-wi-max-size="10485760" data-wi-check="true" multiple>
    <span class="alert-error"></span>
    <script>
    var dataTransfer = new DataTransfer();

    dataTransfer.items.add(new File([], 'cv-andrea.pdf', { type: 'application/pdf' }));
    document.querySelector('input[type="file"]#input_abc').files = dataTransfer.files;
    </script>
</div>
```

Il client (`wonder-image/lib`) usa `input.files` per rendere lo stato
`compiled` del controllo e per il check `max-file`. Il MIME viene
risolto da `mime_content_type()` quando il file esiste su disco,
altrimenti dall'estensione, con fallback `application/octet-stream`.

## `->extensions()` accetta stringa o array

Setter file fluent equivalenti:

```php
FormField::key('cv')->file('pdf')->extensions(['pdf', 'doc', 'docx'])
FormField::key('cv')->file('pdf')->extensions('pdf, doc, docx')
FormField::key('cv')->file('pdf')->extensions('.pdf | .doc | .docx')
```

Le tre forme producono lo stesso `prepare['extensions'] = ['pdf', 'doc',
'docx']` (lowercase, niente punto iniziale, dedupe).

## Aggiungere un nuovo component end-to-end

Esempio: voglio un `InputRange` (slider numerico).

### 1. Element (config layer)

```php
// class/Elements/Form/Components/InputRange.php
<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

class InputRange extends Field
{
    public string $type = 'range';

    public function min(int $min): self  { return $this->attr('min', $min); }
    public function max(int $max): self  { return $this->attr('max', $max); }
    public function step(int|float $step): self { return $this->attr('step', $step); }
}
```

Nessuna logica di rendering qui. Solo fluent API che popola `$this->schema`.

### 2. Renderer Wonder (frontend)

```php
// class/Themes/Wonder/Form/Components/InputRange.php
<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class InputRange extends Field
{
    public function renderInput(): string
    {
        $name = $this->schema['name'] ?? '';
        $value = $this->schema['value'] ?? '';
        $attrs = $this->renderAttributes();

        return '<input type="range" class="wi-range" name="'.$name.'" value="'.$value.'" '.$attrs.'>';
    }
}
```

### 3. Renderer Bootstrap (backend)

```php
// class/Themes/Bootstrap/Form/Components/InputRange.php
<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class InputRange extends Field
{
    public function renderInput(): string
    {
        $name = $this->schema['name'] ?? '';
        $value = $this->schema['value'] ?? '';

        return '<input type="range" class="form-range" name="'.$name.'" value="'.$value.'">';
    }
}
```

### 4. Uso

```php
echo (new InputRange('volume'))->label('Volume')->min(0)->max(100)->step(5)->render();
```

Il Resolver fa automaticamente il matching: nessuna registrazione
esplicita. Convention over configuration.

### Se vuoi che il bridge `FormFieldElementFactory` lo supporti

Aggiungi un case nel `match ($helper)` dentro `FormFieldElementFactory::make()`
(file `class/App/Support/FormFieldElementFactory.php:48`) e poi puoi
usarlo da `Resource::formSchema()`.

## Pattern Renderer / Element via trait

Field nel layer Elements **usa due trait**:

```php
abstract class Field extends Component {
    use CanSpanColumn, Renderer;
    // ...
}
```

- `Wonder\Elements\Concerns\Renderer` espone `render(?string $theme = null): string`
  e delega al `Resolver::renderer(static::class, $theme)`.
- `Wonder\Elements\Concerns\CanSpanColumn` per il layout grid (`columnSpan()`).

Questo è il motivo per cui da fuori scrivi `$field->render()` invece
di `Resolver::renderer($field)->render($field)`: il trait fa
internamente la chiamata.

## Note di pulizia (potenziali follow-up)

Cose viste durante l'audit, non rotture, ma utili da sapere:

1. **`renderInput()` stub in `Elements/Form/Components/`**: 10 file
   (Select, Repeater, Checkbox, CheckGroup, Date, DatePicker, DateRange,
   File, Hidden, Textarea) hanno un metodo
   `protected function renderInput(): string { return ''; }`. È
   **dead code**: il layer Elements è puramente dichiarativo, il
   render lo fa il tema. Sono residui copy-paste — sicuri da
   rimuovere quando si fa pulizia.
2. **Repeater non in Wonder**: intenzionale (frontend non lo usa).
   Niente fallback automatico verso Bootstrap: chiamare
   `->render('wonder')` su un Repeater produce `RuntimeException`
   esplicita. Se servirà, basta creare
   `class/Themes/Wonder/Form/Components/Repeater.php`.
3. **`renderField()` di Bootstrap ha signature estesa**: il metodo nel
   subclass ha `($input, bool $floating = true)` mentre la base ha solo
   `($input)`. PHP accetta il parametro opzionale aggiuntivo. È usato da
   alcuni Component Bootstrap (es. Checkbox) per disattivare il pattern
   `form-floating`. Da tenere d'occhio se mai si formalizzasse la
   signature in `AbstractFieldRenderer`.

## File chiave (mappa)

| Concetto | File | Cosa fa |
|---|---|---|
| Config base | `class/Elements/Form/Field.php` | fluent API: label, value, error, required, readonly, disabled |
| Form container | `class/Elements/Form/Form.php` | aggrega Element con `IsContainer` |
| Base renderer condivisa | `class/Themes/Form/AbstractFieldRenderer.php` | schema, hasError, hasValue, resolvedLabel, render+renderField hook, trait base |
| Render base Wonder | `class/Themes/Wonder/Form/Field.php` | extends Abstract; helper `wi-*`: inputClass, containerClass, renderLabel, renderError |
| Form container Wonder | `class/Themes/Wonder/Form/Form.php` | `<form class="wi-form">` |
| Render base Bootstrap | `class/Themes/Bootstrap/Form/Field.php` | extends Abstract; override renderField (form-floating), helper `form-control`/`is-invalid`/`invalid-feedback` |
| Form container Bootstrap | `class/Themes/Bootstrap/Form/Form.php` | `<form ...>` con grid Bootstrap |
| Selettore tema runtime | `class/App/Theme.php` | `Theme::set('bootstrap')` / `Theme::get()` |
| Registry temi | `class/Themes/Registry.php` | autobot `Wonder` + `Bootstrap`, `setDefault` |
| Resolver | `class/Themes/Resolver.php` | matching solo nel tema richiesto, no fallback cross-theme |
| Bridge FormSchema → Element | `class/App/Support/FormFieldElementFactory.php` | traduzione `helper` → Element + hydrate |
| Trait render lato Element | `class/Elements/Concerns/Renderer.php` | metodo `render()` che delega a `Resolver` |

## TL;DR

- `Elements/Form/Components/<X>` = **cos'è il campo** (label, validazioni, opzioni)
- `Themes/<Tema>/Form/Components/<X>` = **come si disegna** in quel tema
- `Themes/Form/AbstractFieldRenderer` = **base condivisa** tra i Field di ogni tema (helper, schema, label/required, error state)
- Tema attivo via `Wonder\App\Theme::set()`, override per singola render con `$el->render('tema')`
- Il Resolver lavora **solo nel tema richiesto**: niente fallback cross-theme. Match per namespace + parent-class chain. Se non trova, throw esplicito.
