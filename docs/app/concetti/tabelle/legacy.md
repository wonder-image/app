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

## Filtro per data globale deprecato (filter.php)

La funzione globale `filterDate()` di `app/function/backend/filter.php` è
deprecata. Quando migri una pagina che la chiama, usa il filtro della `Table`,
che passa da `Wonder\Backend\Filter\FilterDate` (come fa già
`app/html/backend/list.php`):

```php
$table->filterDate(true, $HOW_MANY_DAYS ?? 30, $FILTER_COLUMN ?? 'creation');
```

I parametri GET cambiano nome: `wi-from`, `wi-to`, `wi-month` e `wi-year`
diventano `date_from`, `date_to`, `month` e `year`.

Finché un sito la chiama, la funzione valida l'input come la classe. Nella
query entrano solo date gg/mm/aaaa valide, con la condizione di
`FilterDate::buildCondition()`: con un solo estremo valido diventa `>=` o
`<=`. `wi-month` e `wi-year` contano solo come interi, e link dei mesi, campi
data e campi nascosti sono escapati. I link dei mesi usano il mese numerico
(`?wi-month=9&wi-year=2026`): un vecchio link con il nome inglese
(`?wi-month=September&wi-year=2026`) mostra l'anno intero.

## Filtri legacy delle liste (filter.php)

Anche le altre funzioni globali di `app/function/backend/filter.php`
(`filter()`, `filterCustom()`, `createFilterCustom()`, `filterLimit()`,
`filterSearch()` e `createSearchBar()`) validano i parametri GET prima di
usarli:

- **Filtri personalizzati (`$FILTER_CUSTOM`)**: un valore entra nella query
  solo se è fra le opzioni che il form mostra (`array`, `database`,
  `function`, i filtri `visible`, `active` ed `evidence`, sezioni, categorie e
  sottocategorie; nei `tree` anche i figli in `child`), e sempre con l'escape
  della connessione. Gli altri valori valgono come assenti, anche quando
  arrivano da un link scritto a mano. Un filtro senza opzioni note ha solo
  l'escape. Form e query leggono le opzioni dalla stessa funzione,
  `filterCustomOptions()`: con un filtro attivo `filterCustom()` fa una query
  in più per le opzioni da database e chiama la funzione dei filtri
  `function`.
- **Colonne**: la `column` dei filtri, le colonne di `$FILTER_SEARCH` e
  `$FILTER_ORDER` passano da `Query::escapeIdentifier()`.
- **Direzione**: `$FILTER_DIRECTION` vale solo `ASC` o `DESC`, altrimenti
  `ASC`.
- **Limite**: `limit` vale solo `25`, `50`, `100`, `250`, `500` o `all`, cioè
  i bottoni; il resto mostra gli ultimi 25.
- **Ricerca**: i termini entrano nel `LIKE` con l'escape della connessione.
  Barra e titolo mostrano la ricerca codificata, senza la barra di
  `addslashes()`.
- **Campi nascosti** del form dei filtri: chiavi e valori codificati; i
  parametri a più valori restano nel form invece di diventare "Array".

`$QUERY_CUSTOM`, `$QUERY_ORDER` e `$QUERY_DIRECTION` restano frammenti SQL
scritti dal sito e finiscono nella query così come sono: non vanno mai
composti con valori della richiesta.
