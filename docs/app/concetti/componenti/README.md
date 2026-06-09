---
icon: puzzle-piece
---

# Componenti UI

## Cos'è

I **componenti** sono i blocchi con cui si compongono i layout di form e di
pagina nel backend: Card, Container, Alert, Accordion, ecc. Vivono in
`class/Elements/Components/*` e si combinano con i campi (`FormInput`) per
ottenere layout a colonne.

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

I metodi di composizione arrivano da Concerns riusabili:

- `components(array)` — figli del contenitore (`Concerns/IsContainer.php`)
- `columns(int|array)` — numero di colonne (`Concerns/HasColumns.php`)
- `columnSpan(int|array)` — quante colonne occupa (`Concerns/CanSpanColumn.php`)

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

## Collegamenti con il resto

- I campi dentro le Card sono sempre `FormInput`/`FormField`: vedi
  [Form](../form/README.md).
- Il layout della tabella usa una cornice analoga (`TableLayoutSchema`): vedi
  [Tabelle](../tabelle/tablecolumn.md).
- I componenti rispettano il design system `wonder-image/lib` (classi `.wi-*`):
  non inventare nuovi nomi `.wi-*` a livello framework.

## Errori comuni

- **`getInput('campo')` per un campo assente** → eccezione
  `Input resource non trovato`. Il campo deve stare in `formSchema()`.
- **Colonne sballate** → `columns()` del contenitore e `columnSpan()` dei figli
  devono essere coerenti.
- **HTML a mano per un riquadro** → usa `Card`/`Container`, non markup custom.

## Charts

Per i grafici (LineChart, PieChart su Chart.js) vedi la pagina
[Charts](charts.md).

## Checklist

- [ ] layout in `formLayoutSchema()` con `Card`/`Container`
- [ ] campi via `static::getInput('campo')` (presenti in `formSchema()`)
- [ ] `columns()` / `columnSpan()` coerenti
- [ ] nessun markup di layout scritto a mano
