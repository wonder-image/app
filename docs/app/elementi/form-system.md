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
├── Themes/Wonder/Form/            ← RENDER layer per FRONTEND pubblico
│   ├── Field.php                  abstract renderer (helper inputClass, renderLabel, renderError)
│   └── Components/                21 renderer concreti (NO Repeater — frontend non lo usa)
│
└── Themes/Bootstrap/Form/         ← RENDER layer per BACKEND admin
    ├── Field.php                  abstract renderer (form-floating, form-control)
    ├── Form.php
    └── Components/                22 renderer concreti (INCLUDE Repeater)
```

## Come si lega un Element al renderer

`Wonder\Themes\Resolver` fa il matching per **classe + tema** tramite
specchio di namespace:

```
Wonder\Elements\Form\Components\InputText
        ↓
Wonder\Themes\{NS}\Form\Components\InputText        ← NS = "Wonder" o "Bootstrap"
```

Il Resolver percorre 2 catene fino a trovare un renderer concreto:

1. **Catena tema**: tema corrente → `fallback()` del tema → default
   (`wonder`). Permette di avere un tema specializzato (es. `Tailwind`)
   che fa fallback a `Bootstrap` per i component non implementati.
2. **Catena element**: classe concreta → parent class → ... fino a
   `Component`. Se manca un renderer specifico per `InputText`, il
   Resolver prova `Field` (parent) come fallback generico.

Se nessuna combinazione di (tema, classe) produce un renderer concreto
non-astratto, viene sollevata `RuntimeException` con la lista dei
tentativi.

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
   Se in futuro servisse, basta creare
   `class/Themes/Wonder/Form/Components/Repeater.php`.
3. **Duplicazione helper tra temi**: `renderLabel()`, `renderError()`,
   `inputClass()` sono reimplementati identici in Wonder e Bootstrap.
   Si potrebbe estrarre un `class/Themes/AbstractFieldRenderer.php`
   condiviso. Refactor a basso rischio ma medio impatto sul codice
   esistente — vale la pena solo se aggiungi un terzo tema.
4. **`fallback()` di Wonder e Bootstrap**: entrambi ritornano `null`.
   Il Resolver allora include il default (`wonder`) in fondo alla
   chain. Se vuoi che `Bootstrap` deleghi a `Wonder` quando manca un
   component, imposta `Bootstrap::fallback() == 'wonder'` (oggi non
   serve perché Bootstrap è il più completo).

## File chiave (mappa)

| Concetto | File | Cosa fa |
|---|---|---|
| Config base | `class/Elements/Form/Field.php` | fluent API: label, value, error, required, readonly, disabled |
| Form container | `class/Elements/Form/Form.php` | aggrega Element con `IsContainer` |
| Render base Wonder | `class/Themes/Wonder/Form/Field.php` | helper `renderLabel/renderError/inputClass` con classi `wi-*` |
| Render base Bootstrap | `class/Themes/Bootstrap/Form/Field.php` | helper con `form-control` + `form-floating` |
| Selettore tema runtime | `class/App/Theme.php` | `Theme::set('bootstrap')` / `Theme::get()` |
| Registry temi | `class/Themes/Registry.php` | autobot `Wonder` + `Bootstrap`, `setDefault` |
| Cascade resolver | `class/Themes/Resolver.php` | matching (tema-chain × element-chain) |
| Bridge FormSchema → Element | `class/App/Support/FormFieldElementFactory.php` | traduzione `helper` → Element + hydrate |
| Trait render | `class/Elements/Concerns/Renderer.php` | metodo `render()` che delega a `Resolver` |

## TL;DR

- `Elements/Form/Components/<X>` = **cos'è il campo** (label, validazioni, opzioni)
- `Themes/<Tema>/Form/Components/<X>` = **come si disegna** in quel tema
- Tema attivo via `Wonder\App\Theme::set()`, override per singola render con `$el->render('tema')`
- Resolver fa match automatico per namespace, con fallback su parent class + tema di fallback + default
