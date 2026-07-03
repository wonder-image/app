---
icon: clock-rotate-left
---

# Appendice: Table legacy

{% hint style="warning" %}
**Pagina storica.** L'API descritta qui (`Wonder\Backend\Table\Table` con
`addColumn(...)`) **non è più** l'API da usare per costruire liste. Per le nuove
risorse usa `Resource::tableSchema()` con `TableColumn`
([guida](README.md)). Questa appendice serve solo a capire codice esistente.
{% endhint %}

## Perché esiste ancora

Il rendering finale delle liste passa internamente da
`class/Backend/Table/Table.php` (basata su DataTables). Il **ponte** tra l'API
moderna e quella legacy è
`class/Backend/Support/ResourceTableRenderer.php`: prende i `TableColumn`
dichiarati in `tableSchema()` e li converte nelle chiamate `Table::addColumn()`
/ `Table::columns()`. Quindi:

```
Resource::tableSchema()  →  ResourceTableRenderer  →  Backend\Table\Table  →  HTML/DataTables
```

Tu lavori a sinistra; la `Table` legacy resta a destra come dettaglio interno.

## L'API legacy (riferimento)

Costruzione e configurazione tipiche della vecchia `Table`:

```php
$table = new \Wonder\Backend\Table\Table($table, $connection);
$table->endpoint($endpoint);
$table->query($query);                      // string o array; default deleted='false'
$table->queryOrder($col, $dir, $colFilter, $dirFilter);
$table->addColumn($label, $column, $orderable, $class, $hiddenDevice, $width, $format);
```

### `addColumn(...)`

Parametri (in ordine): `$label`, `$column`, `$orderable`, `$class`,
`$hiddenDevice`, `$width`, `$format`.

- **`$hiddenDevice`** — `mobile`/`tablet`/`desktop`: il runtime aggiunge la
  classe `not-mobile` / `not-tablet` / `not-desktop`, cioè **nasconde** la
  colonna su quel device (stessa semantica dell'API moderna).
- **`$width`** — `little` = 30px, `medium` = 120px, `big` = 180px, vuoto =
  `auto`.
- **`$format`** — array con `format` (`image`/`date`/`price`/`phone`/…),
  `function`, `value`, `href` (`modify`/`view`/`mailto`/`tel`/…).

### Metodi della cornice legacy

`title()`, `titleNResult()`, `buttonAdd()`, `buttonDownload()`, `filterDate()`,
`filterLimit()`, `filterSearch()`, `addFilter()`, `columns(array $columns)`
(accetta oggetti `Column` dalla `tableSchema()` moderna), `generate()`.

## Mappa legacy → moderno

| Legacy (`Table`) | Moderno (`TableColumn` / `TableLayoutSchema`) |
|---|---|
| `addColumn(..., $width = 'little')` | `TableColumn::key()->size('little')` |
| `addColumn(..., $hiddenDevice = 'mobile')` | `->hiddenDevice('mobile')` |
| `$format['href'] = 'modify'` | `->link('edit')` |
| `$format['function']` | `->function($name, $parameter, $return)` |
| colonna `menu` / `action_button` | `->button()->actions(['edit','delete'])` |
| `title()`, `filterSearch()`, `buttonAdd()` | `TableLayoutSchema::for()->title()->filters()->buttonAdd()` |

## Quando ti serve davvero la legacy

Praticamente mai per codice nuovo. La incontri solo se mantieni pagine vecchie
che costruiscono `Table` a mano. In quel caso, valuta di migrarle a una Resource
con `tableSchema()`.

## Funzioni badge deprecate (plugin.php)

`active()`, `visible()`, `evidence()` e `returnBadge()` di
`app/function/backend/plugin.php` sono wrapper deprecati che delegano a
`Wonder\Backend\Table\Badge\BooleanBadge`. Se il global `$NAME` non è
popolato l'`action` risulta vuota (niente più warning). Mappa di migrazione:

| Legacy | Nuova API |
|---|---|
| `->badge()->function('active', 'id', 'automaticResize')` | `->activeBadge()` |
| `->badge()->function('visible', 'id', 'automaticResize')` | `->visibleBadge()` |
| `->badge()->function('evidence', 'id', 'automaticResize')` | `->evidenceBadge()` |
| `active($value, $id)->automaticResize` | `BooleanBadge::active($value)->automaticResize()` |
| `returnBadge($text, $icon, $color)->badge` | `BooleanBadge::make(true)->on($text, $icon, $color)->badge()` |

### Nota di migrazione (whitelist)

Le pagine legacy dei siti che usano funzioni colonna custom via
`->function('nomeCustom', ...)` devono registrarle in bootstrap con
`\Wonder\Backend\Table\ColumnFunctionRegistry::allow('nomeCustom')`,
altrimenti la cella risulta vuota: il renderer (`Field::setValue()`) esegue
solo funzioni presenti nella whitelist server-side, per evitare che il nome
funzione arrivato dal POST di `list-table` inneschi una chiamata a funzione
PHP arbitraria.
