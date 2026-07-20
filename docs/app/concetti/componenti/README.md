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

## A cosa serve

Strutturare un form (o una pagina) in sezioni ordinate — riquadri, colonne,
avvisi — senza scrivere HTML/CSS a mano.

## Dove si trova nel codice

| Componente | File | Cosa fa |
|---|---|---|
| `Card` | `Elements/Components/Card.php` | riquadro contenitore |
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

I metodi di composizione arrivano da Concerns riusabili:

- `components(array)` — figli del contenitore (`Concerns/IsContainer.php`)
- `columns(int|array)` — numero di colonne (`Concerns/HasColumns.php`)
- `columnSpan(int|array)` — quante colonne occupa (`Concerns/CanSpanColumn.php`)
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
- **HTML a mano per un riquadro** → usa `Card`/`Container`, non markup custom.
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

## Checklist

- [ ] layout in `formLayoutSchema()` con `Card`/`Container`
- [ ] campi via `static::getInput('campo')` (presenti in `formSchema()`)
- [ ] `columns()` / `columnSpan()` coerenti
- [ ] nessun markup di layout scritto a mano
