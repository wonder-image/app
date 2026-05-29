# Componenti UI

I componenti UI vivono in `class/Elements/Components/` e vengono renderizzati
automaticamente dal tema attivo tramite `class/Themes/{Bootstrap,Wonder}/Components/`.

Seguono lo stesso pattern Element/Theme dei form: l'Element e' dichiarativo
(fluent API, nessun HTML), il renderer del tema produce il markup.

## Concern condiviso: `HasText`

Il trait `Wonder\Elements\Concerns\HasText` raccoglie le proprieta'
tipografiche comuni a tutti i componenti che mostrano testo.

Metodi disponibili:

| Metodo | Effetto Bootstrap |
|---|---|
| `text(string)` | Imposta il contenuto testuale |
| `muted()` | `text-body-secondary` |
| `small()` | `small` |
| `bold()` | `fw-bold` |
| `italic()` | `fst-italic` |
| `color(string)` | `text-{color}` (es. `'danger'`, `'success'`) |
| `align(string)` | `text-{align}` (es. `'center'`, `'end'`) |

Lato tema, il trait `Wonder\Themes\Bootstrap\Concerns\RendersText`
traduce queste proprieta' in classi Bootstrap 5.3.

Componenti che usano `HasText`: `HelpText`, `SectionTitle`, `Tooltip`, `Accordion`.

## `HelpText`

Testo di supporto, muted di default.

```php
use Wonder\Elements\Components\HelpText;

HelpText::make('Inserisci il codice fiscale senza spazi');

HelpText::make('Campo obbligatorio')
    ->small()
    ->color('danger')
    ->muted(false);
```

Metodi aggiuntivi: nessuno — usa solo `HasText`.

Default: `muted(true)`, `columnSpan(12)`.

## `SectionTitle`

Titolo di sezione con heading semantico e tooltip opzionale.

```php
use Wonder\Elements\Components\SectionTitle;

SectionTitle::make('Dati anagrafici');

SectionTitle::make('Impostazioni avanzate')
    ->level('h5')
    ->tooltip('Modifica solo se sai cosa stai facendo')
    ->bold();
```

Metodi aggiuntivi:

| Metodo | Descrizione |
|---|---|
| `level(string)` | Tag heading: `h1`..`h6` (default `h6`) |
| `tooltip(string)` | Aggiunge un'icona info con tooltip Bootstrap |

Default: `columnSpan(12)`, `level('h6')`.

## `Tooltip`

Icona con tooltip Bootstrap al passaggio del mouse.

```php
use Wonder\Elements\Components\Tooltip;

Tooltip::make('Questo campo e\' calcolato automaticamente');

Tooltip::make('Attenzione: azione irreversibile')
    ->placement('bottom')
    ->icon('bi bi-exclamation-triangle');
```

Metodi aggiuntivi:

| Metodo | Descrizione |
|---|---|
| `placement(string)` | Posizione: `top`, `bottom`, `left`, `right` (default `top`) |
| `icon(string)` | Classe icona (default `bi bi-info-circle`) |

## `Accordion`

Accordion Bootstrap con contenuto collassabile.

```php
use Wonder\Elements\Components\Accordion;

Accordion::make('Dettagli avanzati')
    ->components([
        HelpText::make('Questi campi sono opzionali'),
        // ...altri componenti
    ]);

Accordion::make('Sezione aperta')
    ->expanded()
    ->flush()
    ->components([
        // ...componenti
    ]);
```

Metodi aggiuntivi:

| Metodo | Descrizione |
|---|---|
| `components(array)` | Contenuto dell'accordion (array di Component) |
| `expanded(bool)` | Aperto di default (default `false`) |
| `flush(bool)` | Stile flush senza bordi (default `false`) |

Default: `columnSpan(12)`.

## `Card`

Blocco visivo con bordo e padding. Gia' documentato in
[Componenti backend](../backend/resource-manuale/componenti.md).

```php
use Wonder\Elements\Components\Card;

(new Card)->components([
    SectionTitle::make('Informazioni'),
    // ...input
])->columns(2)->columnSpan(2);
```

## `Container`

Contenitore senza stile visivo, utile per raggruppare componenti
in una griglia.

```php
use Wonder\Elements\Components\Container;

(new Container)->components([
    // ...componenti
])->columns(3)->gap(4);
```

## `Alert`

Notifica toast con livello di severita'.
Gia' documentato nella sezione Elements base.

```php
use Wonder\Elements\Components\Alert;

Alert::make('Operazione completata', 'success');

Alert::make('Errore durante il salvataggio', 'error')
    ->title('Errore')
    ->dismissible();
```

## Composizione

I componenti sono pensati per essere composti liberamente:

```php
use Wonder\Elements\Form\Form;
use Wonder\Elements\Components\{ Card, SectionTitle, HelpText, Accordion };

(new Form)->components([

    (new Card)->components([
        SectionTitle::make('Dati principali')->level('h5'),
        HelpText::make('Compila tutti i campi obbligatori')->small(),
        // ...input
    ])->columns(2)->columnSpan(2),

    (new Card)->components([
        Accordion::make('Opzioni avanzate')->components([
            HelpText::make('Queste impostazioni sono opzionali'),
            // ...input
        ]),
    ])->columnSpan(1),

])->columns(3);
```

## Struttura file

```
class/Elements/Components/          <- Element (dichiarativo)
├── Accordion.php
├── Alert.php
├── Card.php
├── Container.php
├── HelpText.php
├── SectionTitle.php
└── Tooltip.php

class/Elements/Concerns/            <- Trait condivisi Element
├── HasText.php                     <- tipografia: text, muted, bold, ...
├── CanSpanColumn.php
├── IsContainer.php
├── HasAttributes.php
├── HasStyle.php
├── HasColumns.php
├── HasGap.php
└── Renderer.php

class/Themes/Bootstrap/Components/  <- Renderer Bootstrap
├── Accordion.php
├── Alert.php
├── Card.php
├── Container.php
├── HelpText.php
├── SectionTitle.php
└── Tooltip.php

class/Themes/Bootstrap/Concerns/    <- Trait condivisi renderer
├── RendersText.php                 <- traduce HasText -> classi Bootstrap
├── Breakpoint.php
├── CanSpanColumn.php
├── HasColumns.php
└── HasGap.php
```
