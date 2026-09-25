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

`addFilter()` accetta come ottavo argomento una `Closure` con una condizione
propria: è quella di `tableLayoutSchema()->filterQuery()` (vedi
[TableColumn e tableLayoutSchema](tablecolumn.md)).

## Tabelle aggregate (GROUP BY)

Il flusso moderno `Resource::tableSchema()` rende **righe di una tabella** (una
riga = un record). Per una tabella **aggregata** (una riga = un gruppo, es.
statistiche per attività) si usa direttamente `Backend\Table\Table` con due
metodi dedicati:

```php
$table = new \Wonder\Backend\Table\Table('scheduler_runs');
$table->query("started_at >= '$cutoff' AND status NOT IN ('pending','running','skipped')");
$table->select("MIN(id) AS id, task_key, COUNT(*) AS runs, AVG(duration_ms) AS average_ms, …");
$table->groupBy('task_key');
$table->queryOrder('runs', 'DESC');
$table->addColumn('Esecuzioni', 'runs', true);
$table->addColumn('Durata (s)', 'average_ms', true, '', '', '', ['formatter' => 'app-scheduler.duration']);
echo $table->generate(false);
```

Regole:

- **`select(string|array $columns)`** — la lista SELECT esplicita (aggregati con
  alias). Le colonne mostrate (`addColumn`) referenziano quegli **alias**
  (`runs`, `average_ms`, …); l'ordinamento server-side usa gli stessi alias.
- **`groupBy(string|array $columns)`** — la clausola `GROUP BY`. Con il group by
  i conteggi `recordsTotal` / `recordsFiltered` contano i **gruppi** (SSP avvolge
  la query raggruppata in una subquery).
- **`id` sintetico obbligatorio** — il renderer di cella (`Field`) richiede
  `$row['id']`: includi sempre `MIN(id) AS id` (o simile) nella `select()`.
- **Filtro dati** — passa il `WHERE` (es. il periodo) con `query(...)`; viene
  applicato **prima** del raggruppamento.
- **Formattazione delle celle in PHP, non in JS** — usa `->formatter()` (vedi
  sotto), mai uno `<script>new DataTable(...)>` a mano.

**Sicurezza**: `select` e `group_by` sono frammenti SQL generati server-side e
**firmati** (`ConfigCodec`, HMAC con `APP_KEY`) esattamente come `query` /
`query_filter`; `ListProvider::fetch` li verifica prima di passarli a
`SSP::complex`. Il client non può alterarli senza rompere la firma. Chiave
assente ⇒ nessun group by (tabelle non aggregate invariate).

Nelle Resource le colonne calcolate si dichiarano con `tableLayoutSchema()->select()`
([TableColumn e tableLayoutSchema](tablecolumn.md)): il renderer passa a
`Table::select()` `` `tabella`.* `` più quelle colonne, senza `groupBy()`.

### Formatter di cella (`->formatter()`)

Per formattare una cella in PHP ricevendo **l'intera riga** (utile per gli
aggregati: valore medio + "Max… · Tot…"), dichiara la colonna in un
`tableSchema()` con `->formatter(fn(array $row): string => …)`. La closure viene
auto-registrata in `ColumnFormatterRegistry` sotto `{slug}.{colonna}` in **ogni**
request (rendering e endpoint SSP), e la si referenzia da `addColumn` con
`['formatter' => '{slug}.{colonna}']`. Come per `->function()`, il nome è una
**whitelist**: un nome non registrato ⇒ cella vuota, mai esecuzione arbitraria.

Riferimento completo: `Wonder\App\Resources\Scheduler\DashboardResource`
(`statisticsTable()` + `tableSchema()` con i formatter delle celle).

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
