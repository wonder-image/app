---
icon: table-columns
---

# TableColumn e tableLayoutSchema

## TableColumn

`TableColumn` (`class/App/ResourceSchema/TableColumn.php`) estende
`Wonder\Elements\Table\Column`. Si parte da `TableColumn::key('nome_colonna')`.

### Tipi di colonna

Impostano il tipo di resa (`setType`):

| Metodo | Resa |
|---|---|
| `text()` | testo semplice |
| `date()` / `datetime()` | data / data+ora |
| `phone()` | numero di telefono |
| `price()` | prezzo |
| `badge()` | badge colorato |
| `status()` | indicatore di stato |
| `user()` / `userAvatar()` / `userName()` | utente / avatar / nome |
| `icon()` | icona |
| `image()` | immagine/thumbnail |
| `button()` | cella con bottoni (usata con `actions()`) |

### Azioni

```php
TableColumn::key('actions')->button()->actions(['edit', 'delete']);
```

- `actions(array $actions)` — abilita più azioni in un colpo. Accetta una lista
  (`['edit', 'delete']`) o una mappa (`['edit' => true, 'delete' => false]`);
  nella mappa un valore array descrive una voce personalizzata.
- `action(string $action, bool|array $enabled = true)` — abilita/disabilita una
  singola azione, o la descrive con un array; un array vuoto la spegne.

#### Voci personalizzate

Una voce ad array arriva intera a `Backend\Table\Field::actionButton()`, come
nelle Table dirette, e compare nel menu azioni della riga (i tre puntini):

```php
TableColumn::key('actions')->button()->actions([
    'view',
    'movimenti' => [
        'label' => 'Movimenti',
        'href' => '/backend/magazzino/movimenti/?versione={id}',
    ],
    'type' => [
        'label' => ['load' => 'Visualizza carico', 'unload' => 'Visualizza scarico'],
        'href' => '/backend/magazzino/documenti/{document_id}/',
    ],
    'conferma' => [
        'label' => 'Conferma',
        'href' => '/backend/magazzino/documenti/{id}/conferma/',
        'filter' => ['row' => ['status' => 'draft']],
    ],
]);
```

- `label` è una stringa, oppure un array indicizzato dai valori della colonna
  che ha il nome della voce (qui `type`); senza un'etichetta per quel valore, o
  senza la colonna, la voce non compare.
- In `href`, `{colonna}` prende il valore della riga. L'attributo esce con
  `htmlspecialchars()` ma senza `rawurlencode()`, perché una colonna può
  contenere un indirizzo intero: i valori che finiscono in una query string
  vanno preparati prima.
- `target` (per esempio `_blank`) esce escapato.
- `filter.row` accetta un valore o una lista
  (`['status' => ['draft', 'confirmed']]`); se la riga non ha la colonna, la
  voce resta nascosta.
- Anche `modify` e `delete` accettano un array, per esempio solo con
  `filter.row`. `delete` rispetta il permesso di eliminare la riga; in sola
  lettura `delete` e `duplicate` spariscono, anche ad array.

### Badge booleani

Per le colonne booleane `active` / `visible` / `evidence` esistono helper
dedicati che sostituiscono il vecchio `->badge()->function('active', ...)`:

```php
TableColumn::key('active')->activeBadge()->size('little'),
TableColumn::key('visible')->visibleBadge(),
TableColumn::key('evidence')->evidenceBadge(),
```

Il badge è **statico**: il toggle resta nel menu azioni della riga
(`->actions(['visible', ...])`). Per forzare il toggle direttamente sul badge
(scelta esplicita, non il default): `->activeBadge(true)` oppure
`->badgeClickable()`.

Per badge booleani custom c'è la base generica:

```php
TableColumn::key('stato')->booleanBadge()
    ->badgeOn('Aperto', 'bi bi-unlock', 'success', 'Chiudi')
    ->badgeOff('Chiuso', 'bi bi-lock', 'danger', 'Apri')
    ->badgeVariant('automaticResize')   // default; anche: badge, tooltip, badgeTooltip, icon
    ->badgeClickable(),                 // opzionale, opt-in
```

`booleanBadge('altra_colonna')` legge il valore da una colonna diversa dalla
key. Il render passa da `Wonder\Backend\Table\Badge\BooleanBadge`
(`class/Backend/Table/Badge/BooleanBadge.php`), che è anche l'API da usare
per renderizzare questi badge fuori dagli schema. Il valore è "on" solo se
`'true'`/`true`.

### Link

```php
TableColumn::key('name')->text()->link('edit');
```

`link($link)` rende la cella cliccabile. Nota: `link('edit')` viene tradotto
internamente in `'modify'` (la route di modifica). Puoi passare anche altri
target (`'view'`, `'mailto'`, `'tel'`, …).

### Metodi ereditati dalla base `Column`

| Metodo | Cosa fa |
|---|---|
| `label($s)` | intestazione colonna |
| `class($s)` | classi CSS aggiuntive |
| `size($s)` | larghezza: `auto`, `little`, `medium`, `big` |
| `hiddenDevice($s)` | nasconde su `mobile`/`tablet`/`desktop` |
| `sortable($b)` | colonna ordinabile |
| `function($name, $parameter = 'id', $return = null)` | valore calcolato da una funzione |
| `callback($cb)` | callback di rendering |
| `link($link)` | cella linkata |

Dettagli su `size`, `hiddenDevice`, `function` in
[Opzioni colonna](opzioni-colonna.md).

## tableLayoutSchema

`TableLayoutSchema` (`class/App/ResourceSchema/TableLayoutSchema.php`) definisce
la cornice attorno alla tabella. Si costruisce con
`TableLayoutSchema::for(static::class)`.

### Metodi

| Metodo | Cosa fa |
|---|---|
| `title($enabled = true, $text = null)` | blocco titolo pagina |
| `hideTitle()` | nasconde il titolo |
| `results($enabled = true)` | riga conteggio risultati |
| `buttonAdd($enabled = true, $label = null)` | CTA "Aggiungi" in alto a destra |
| `hideButtonAdd()` | nasconde la CTA |
| `buttonCustom(Button\|Dropdown\|string $button)` | aggiunge un'azione accanto alla CTA "Aggiungi" |
| `buttonsCustom(array $buttons)` | aggiunge più azioni custom in ordine |
| `buttonCustomHtml(string $html)` | aggiunge HTML trusted accanto alla CTA |
| `clearButtonsCustom()` | rimuove le azioni custom già configurate |
| `filterSearch($enabled = true)` | casella di ricerca |
| `filterLimit($enabled = true)` | selettore "righe per pagina" |
| `filters($search = true, $limit = true)` | scorciatoia per i due sopra |
| `searchFields(array $fields)` | colonne interrogate dalla ricerca: nomi di colonna, `tabella.colonna` o descrittori di relazione, anche annidati |
| `select(string $sql)` | colonne calcolate in SQL, ognuna con il suo alias |
| `filterCustom($label, $column, $options, $input = 'select', $search = false, $columnType = null, $value = null)` | filtro su una colonna (`=`, `IN`, `LIKE` con `multiple`) |
| `filterRadio($label, $column, $options, $search = false, $value = null)` | filtro radio |
| `filterQuery($label, $key, $options, Closure $where, $input = 'select', $search = false, $value = null)` | filtro con una condizione propria |
| `cleanHeader()` | header pulito |
| `download($formats = true, $label = null)` | export (CSV/…) |
| `downloadColumns(array $columns)` | colonne incluse nell'export |
| `downloadFileName(string $filename)` | nome file export |

### Default

Costruita con `for(...)` la cornice parte con titolo attivo, risultati attivi,
bottone "Aggiungi" attivo, ricerca + limite attivi. Override solo ciò che
differisce.

```php
public static function tableLayoutSchema(): TableLayoutSchema
{
    return TableLayoutSchema::for(static::class)
        ->title('Lista '.static::pluralLabel())
        ->buttonAdd('Aggiungi '.static::label())
        ->filters()
        ->searchFields(['name', 'email'])
        ->download(['csv'])
        ->downloadColumns(['id', 'name', 'email']);
}
```

L'export della cornice si appoggia alla route `export` generata dalla Resource
(gated sul permesso `list`): vedi [Route e API](../risorse/route-e-api.md).

### Azioni custom nell'header

Usa i componenti `Button` e `Dropdown`: il renderer li porta automaticamente
alla dimensione `sm` e li mostra accanto a `buttonAdd`.

```php
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Dropdown;

return TableLayoutSchema::for(static::class)
    ->buttonAdd('Aggiungi elemento')
    ->buttonCustom(
        Button::post('/backend/sync/', 'Sincronizza')
            ->icon('bi bi-arrow-repeat')
            ->confirm('Avviare la sincronizzazione?')
    )
    ->buttonCustom(
        Button::to('/backend/import/', 'Importa')
            ->variant('secondary')
            ->icon('bi bi-upload')
    )
    ->buttonCustom(
        Dropdown::make('Altre azioni')
            ->align('end')
            ->item('Scarica esempio', '/backend/example/')
    );
```

Per azioni POST usa `Button::post()`; il componente genera e sanifica il form.
`buttonCustomHtml()` resta disponibile solo per markup non rappresentabile da un
Element. L'HTML viene emesso senza escaping: deve essere trusted e ogni valore
dinamico va sanificato prima di comporlo.

### Colonne calcolate in SQL (`select()`)

Per mostrare un valore che non sta nella tabella (una somma, un nome preso da
un'altra tabella) si dichiara la colonna calcolata con il suo alias e la si usa
come una colonna qualsiasi:

```php
public static function tableLayoutSchema(): TableLayoutSchema
{
    return TableLayoutSchema::for(static::class)
        ->select('(SELECT SUM(s.qty) FROM gst_stock s WHERE s.product_id = gst_product.id) AS qty_total');
}

public static function tableSchema(): array
{
    return [
        TableColumn::key('sku')->text(),
        TableColumn::key('qty_total')->text()->label('Giacenza'),
    ];
}
```

- `AS alias` è obbligatorio, e l'alias non deve avere il nome di una colonna
  della tabella.
- Il renderer mette davanti `` `tabella`.* ``: le colonne del record restano, e
  senza `select()` la lista resta quella di sempre.
- Gli alias si mostrano e si ordinano, ma il `WHERE` non li vede: niente
  ricerca, filtri, `query()` né conteggi. Il renderer li toglie dalla ricerca,
  sia da quella predefinita sia da `searchFields()`; se non resta niente, la
  casella di ricerca non compare.
- Per filtrare su un valore calcolato serve `filterQuery()` con una subquery.
- L'export non vede le colonne calcolate, che escono vuote: per esportarle
  serve in `downloadColumns()` una voce
  `['label' => …, 'value' => fn ($row) => …]`.
- È SQL dello sviluppatore, mai input dell'utente, e viaggia firmato come
  `query`. Una seconda chiamata sostituisce la prima; più colonne si separano
  con le virgole nella stessa stringa.

### Filtri con condizione (`filterQuery()`)

`filterCustom()` confronta una colonna con i valori scelti. Quando la condizione
è un'altra (sottocategorie comprese, una soglia, una subquery) si usa
`filterQuery()`: la closure riceve i valori scelti e restituisce l'SQL.

```php
$categories = [
    4 => ['name' => 'Maglie', 'child' => [7 => 'Polo']],
    5 => 'Pantaloni',
];

return TableLayoutSchema::for(static::class)
    ->filterQuery('Categoria', 'category', $categories, static function (array $ids): string {
        $ids = implode(', ', array_map('intval', $ids));

        return "`category_id` IN ($ids) OR `category_id` IN (SELECT `id` FROM `gst_category` WHERE `parent_id` IN ($ids))";
    }, 'tree');
```

- La closure riceve solo i valori presenti fra le opzioni, figli degli alberi
  compresi, come stringhe: vanno convertiti (`intval`) o escapati.
- Se non resta nessun valore (niente di scelto, o un GET manomesso), la closure
  non si chiama e il filtro non conta.
- `$key` dà il nome al parametro GET (`{tabella}__{key}`), non è una colonna.
- La closure gira al render; il frammento esce fra parentesi (un `OR` interno è
  quindi sicuro), si unisce agli altri filtri con `AND` e viaggia firmato.

### Ricerca

`searchFields()` dice dove cerca la casella di ricerca. Accetta tre forme:

- il nome di una colonna della tabella (`'sku'`);
- `tabella.colonna` (`'gst_brand.name'`): il renderer trova la chiave esterna
  nel Model e scarta la voce se non la trova;
- un descrittore di relazione: `table`, `local_key` (la colonna di questa
  tabella), `foreign_key` (la colonna di `table`, `id` se manca), `columns`
  (dove cercare in `table`) e `relations`, altri descrittori che partono da
  `table`.

```php
// movimento → versione → articolo
->searchFields([
    'code',
    [
        'table' => 'gst_product',
        'local_key' => 'product_id',
        'columns' => ['sku', 'ean', 'name'],
        'relations' => [
            [
                'table' => 'gst_product_model',
                'local_key' => 'product_model_id',
                'columns' => ['name'],
            ],
        ],
    ],
])
```

- Ogni parola cercata deve comparire in una colonna o in una tabella collegata.
  Le relazioni diventano `local_key IN (SELECT foreign_key FROM table WHERE …)`,
  una dentro l'altra.
- Il renderer controlla i descrittori sul database: `local_key` nella tabella
  del padre, `foreign_key` e `columns` in `table`. Colonne inesistenti e figli
  non validi spariscono; un descrittore senza colonne e senza figli validi è
  scartato.
- Gli alias di `select()` non si cercano mai.

## Errori comuni

- **Bottoni azione che non fanno nulla** → manca `->button()` prima di
  `->actions([...])`.
- **Ricerca che non filtra** → manca `searchFields([...])` nel layout.
- **Colonna calcolata vuota** → `function()` con `name` che non esiste o
  `parameter` sbagliato (default `'id'`).
- **Voce personalizzata che non compare** → `label` ad array senza un'etichetta
  per il valore della riga, o `filter.row` su una colonna che manca nella riga.
- **Colonna di `select()` vuota o lista che non si carica** → manca `AS alias`,
  o l'alias ha il nome di una colonna della tabella (ordinamento ambiguo).
- **Filtro o ricerca su un alias di `select()`** → il `WHERE` non vede gli
  alias: serve `filterQuery()` con una subquery.

## Checklist

- [ ] ogni colonna ha un tipo (`text()`, `badge()`, …)
- [ ] colonna `actions` con `->button()->actions([...])`
- [ ] `tableLayoutSchema()` con `searchFields` se la ricerca serve
- [ ] export configurato con `download()` + `downloadColumns()` se serve
- [ ] voci personalizzate del menu con `label`, `href` e, se serve, `filter.row`
- [ ] colonne calcolate in SQL con `select()`, ognuna con un alias suo
- [ ] filtri che non sono `colonna = valore` con `filterQuery()`
- [ ] ricerca nelle tabelle collegate con descrittori (anche annidati in `relations`)
