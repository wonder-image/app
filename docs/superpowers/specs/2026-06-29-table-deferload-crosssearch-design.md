# Table: pre-render prima pagina + ricerca cross-table

- **Data**: 2026-06-29
- **Repo**: `wonder-image/app` (framework) + `wonder-image/lib` (design system JS)
- **Stato**: design approvato, pre-implementazione

## Obiettivo

Due feature sul componente backend `Wonder\Backend\Table\Table`:

1. **Pre-render della prima pagina** — la tabella mostra le prime righe gia renderizzate
   server-side, senza la prima chiamata AJAX all'endpoint DataTables.
2. **Ricerca cross-table** — la search bar puo cercare anche su colonne di tabelle
   correlate (relazione a un hop, via foreign key gia dichiarata nello schema del Model).

## Contesto attuale

- `Table::generate()` produce `<thead>`/`<tbody>` vuoti
  ([Table.php:656](../../../class/Backend/Table/Table.php)) e uno `<script>` che chiama
  `createDataTables(id, endpoint, JSON)` ([Table.php:670-714](../../../class/Backend/Table/Table.php)).
- La lib (`createDataTables` in `src/build/backend/js/list.js`) inizializza DataTables con
  `serverSide: true` + `ajax` POST verso `Path::apiDT`
  (`app/api/backend/list/table.php`). La prima pagina arriva sempre dopo un roundtrip.
- L'endpoint costruisce `$COLUMNS` (nome colonna + formatter `Field`) *inline*
  ([table.php:126-207](../../../app/api/backend/list/table.php)) e chiama
  `SSP::complex()` ([SSP.php:279](../../../class/Backend/Table/SSP.php)), che ritorna
  `['draw','recordsTotal','recordsFiltered','data']` con `data` array indicizzato per colonna.
- La ricerca: `filterSearch['fields']` (lista piatta di nomi colonna) -> base64 in
  `config.search_columns` -> endpoint -> `SSP::filter()` costruisce un unico
  `CONCAT_WS(' ', col1, col2, ...) LIKE '%termine%'` sulla **sola** tabella principale
  (`SELECT * FROM table`, nessun JOIN).
- Lo schema dei Model dichiara gia le FK: `Column::key('user_id')->int()->foreign('user')`.
  I Resource usano gia `->searchFields([...])` via `TableLayoutSchema`.

## Decisioni di design

- **Coordinamento app<->lib**: degradazione automatica. Il framework emette sempre sia le
  righe pre-renderizzate sia i conteggi. Una lib aggiornata usa `deferLoading`; una lib
  disallineata ignora i conteggi e fa il primo AJAX come oggi (sovrascrivendo le righe
  statiche). Nessuna rottura se le due repo non sono allineate.
- **Dichiarazione cross-table**: sintassi puntata `tabella_foreign.colonna` nei
  `searchFields`, risolta tramite il metadata `foreign()` dello schema del Model; piu un
  descrittore esplicito come fallback per la `Table` usata standalone (senza Model).
- **Profondita relazione**: solo un hop (FK diretta). Pivot / many-to-many fuori scope.

## Architettura

### 1. Componente condiviso: `ListProvider`

Nuova classe `Wonder\Backend\Table\ListProvider` che estrae la logica oggi inline
nell'endpoint. Responsabilita unica: da "config tabella + stato richiesta" a "risultato SSP".

```php
ListProvider::fetch(
    array $request,   // equivalente del $_POST odierno (start, length, search, order, config, ...)
    object $name,     // id, table, database, connection, field, schema, link, page, length
    object $text,
    object $user,
    object $page,
    ?Path  $path
): array              // ['draw','recordsTotal','recordsFiltered','data']
```

Internamente: costruisce `$COLUMNS` (db + formatter `Field`, inclusi i casi speciali
`position-up`/`position-down`/`menu`) e chiama `SSP::complex()`.

- **Endpoint** `app/api/backend/list/table.php`: diventa un wrapper sottile — assembla
  `$request` e gli oggetti dal `$_POST`, chiama `ListProvider::fetch()`, fa
  `echo json_encode(...)`. Comportamento di output identico a oggi.
- **`Table::generate()`**: chiama lo stesso `ListProvider::fetch()` per lo stato iniziale,
  derivando pagina / ordine / ricerca / filtri dall'URL (logica gia presente in
  [Table.php:686-702](../../../class/Backend/Table/Table.php)).

### 2. Feature A — Pre-render (`deferLoading`)

In `Table::generate()`, dopo il risultato del provider:

- Renderizza le righe della prima pagina nel `<tbody>`: un `<td>` per cella, in ordine di
  colonna (le celle sono gia HTML formattato da `Field`). `<thead>` resta vuoto (DataTables
  genera l'header dai `columns.title`, come oggi).
- Aggiunge a `config.default` il campo `deferLoading: [recordsFiltered, recordsTotal]`.

Lato lib (`src/build/backend/js/list.js`, funzione `createDataTables`): se
`config.default.deferLoading` e presente, lo passa all'init di DataTables. DataTables usa
le righe gia nel DOM per il primo draw e fa AJAX solo dal primo paging/sort/search in poi.
Va poi ricompilato il `dist`.

Allineamento stato iniziale: `displayStart`, `order` e `search` passati alla init devono
corrispondere allo stato con cui sono state pre-renderizzate le righe, altrimenti DOM e
stato DataTables divergono.

**Degradazione**: lib non aggiornata -> `deferLoading` ignorato -> AJAX iniziale come oggi.

### 3. Feature B — Ricerca cross-table

**Config (call-site)**: `searchFields` accetta voci puntate oltre alle stringhe attuali:

```php
->searchFields(['name', 'surname', 'user.email', 'user.username'])
```

**Risoluzione (framework-side, in `Table`)**: per `user.email`, cerca nello schema del Model
la colonna con `->foreign('user')` (es. `user_id`) e produce un descrittore, raggruppando le
colonne sulla stessa relazione:

```php
[
  'table'       => 'user',
  'local_key'   => 'user_id',
  'foreign_key' => 'id',
  'columns'     => ['email', 'username'],
]
```

Per la `Table` standalone (senza Model) si passa direttamente il descrittore. I descrittori
risolti viaggiano nel gia esistente `config.search_columns` (base64/JSON): **nessuna modifica
al contratto endpoint**, e SSP non deve conoscere i Model.

**`SSP::filter()`**: per ogni parola cercata combina in OR la CONCAT_WS della tabella
principale e una subquery per ogni relazione; AND tra le parole (logica attuale):

```sql
( CONCAT_WS(' ', `mainCol1`, ...) LIKE '%w%'
  OR `user_id` IN (SELECT `id` FROM `user`
                   WHERE CONCAT_WS(' ', `email`, `username`) LIKE '%w%') )
```

La `SELECT *` dalla tabella principale resta intatta -> i formatter `Field` non cambiano; la
stessa WHERE alimenta dati e count -> `recordsFiltered` coerente.

## Sicurezza

- I nomi tabella/colonna delle relazioni vengono validati con `sqlColumnExists` /
  `sqlTableInfo` prima di costruire la subquery; le voci non valide vengono scartate.
- Il valore di ricerca passa gia da `sanitize()` ([SSP.php:160](../../../class/Backend/Table/SSP.php)).
- Nota (preesistente, fuori scope): `query` / `query_filter` / `query_custom` sono base64
  dal client e interpolati in SQL. Superficie di injection gia presente, non introdotta da
  questo lavoro; da affrontare separatamente.

## Testing

- `ListProvider::fetch()`: input request -> output SSP, in isolamento. Copre il path
  condiviso da endpoint e pre-render (inclusi i campi speciali position/menu).
- `SSP::filter()`: voci miste (stringhe + descrittori), parola singola e multipla; verifica
  della WHERE generata e dello scarto delle relazioni non valide.
- Verifica manuale end-to-end:
  - prima pagina renderizzata senza chiamata AJAX iniziale (Network tab);
  - ricerca che matcha una colonna su tabella correlata;
  - fallback con lib non aggiornata (AJAX iniziale, nessuna rottura).

## File coinvolti

- `class/Backend/Table/ListProvider.php` (nuovo)
- `class/Backend/Table/Table.php`
- `class/Backend/Table/SSP.php`
- `app/api/backend/list/table.php`
- `class/App/ResourceSchema/TableLayoutSchema.php` (passaggio `searchFields` cross-table)
- lib `src/build/backend/js/list.js` (+ rebuild `dist`)

## Fuori scope

- Relazioni multi-hop e pivot / many-to-many nella ricerca.
- Bonifica dell'injection preesistente su `query`/`query_filter`/`query_custom`.
- JOIN nella query principale (scelta la strada subquery `IN`/`EXISTS`).
