# Tracciati di import ed export

Import ed export parlano la stessa lingua, `Wonder\App\Dataset`: una **fonte**
(`Source`) ha un id, un'etichetta, delle **colonne** (`Column`) e dice per
quali azioni si può usare. Un export legge le righe dalla fonte; un
caricamento le scrive. Le colonne, l'ordine e le intestazioni scelte
dall'amministratore si salvano in Dev → File → **Tracciati file**, valide per
entrambi.

| Contratto | Cosa aggiunge | Esempi |
| --- | --- | --- |
| `Source` | `id()`, `label()`, `columns()`, `allows(?object $user, string $action)` | |
| `Exportable` | `rows(): iterable` | `ResourceSource`, feed di un modulo |
| `Importable` | il motore di caricamento | `Import\Layout` |

`allows()` riceve `'export'` o `'import'`. Una Resource serve solo all'export;
un `Import\Layout` solo all'import e mai senza utente (decide `permits()`).

## Colonne

```php
use Wonder\App\Dataset\{Column, Type};

Column::make('sku', 'Codice', ['SKU', 'Cod. articolo'])  // chiave, intestazione, altri nomi
    ->required()                   // import: obbligatoria
    ->field('Codice articolo')     // import: dove finisce, per il resoconto
    ->value('code')                // il campo della riga da leggere
    ->type(Type::Money)            // come si scrive e si rilegge
    ->sensitive();                 // dato personale
```

- `value()` è il **nome di un campo** quando è una stringa, oppure una
  closure che riceve la riga: `->value(fn (array $row) => $row['name'].' '.$row['surname'])`.
  Una stringa non chiama mai una funzione globale (`'date'` legge il campo
  `date`). Senza `value()` si legge il campo con il nome della chiave.
- `type()` accetta l'enum o il suo valore (`'money'`). Senza tipo il valore
  passa così com'è.
- `withHeader($header)` cambia l'intestazione e tiene la vecchia tra gli altri
  nomi, così un file con l'intestazione del codice si abbina ancora.
- `sensitive()` segna un dato personale: un tracciato verso l'esterno lo
  esclude se non è scelto apposta.

`Wonder\App\Import\Column` resta come sottoclasse deprecata: i tracciati di
caricamento esistenti funzionano senza modifiche.

### Tipi

| `Type` | Esce come | Rilegge |
| --- | --- | --- |
| `Text` | testo | testo, spazi tolti |
| `Number` | `3.1`, senza zeri inutili | `1.234,56` e `1,234.56` |
| `Money` | `12.50`, due decimali col punto | come `Number` |
| `Boolean` | `true` / `false` | `sì`, `si`, `yes`, `vero`, `x`, `1` / `no`, `falso`, `0` |
| `Date` | `aaaa-mm-gg` | anche `gg/mm/aaaa`; date impossibili → null |
| `DateTime` | `aaaa-mm-gg hh:mm:ss` | anche `gg/mm/aaaa hh:mm` |
| `List` | `a\|b` (anche da JSON) | `a \| b` → `['a', 'b']` |

Una cella vuota si rilegge `null` (una lista vuota per `List`). Quello che
esce si rilegge uguale.

## Le fonti

`Sources` raccoglie le fonti, in quest'ordine (l'ultima vince sullo stesso id):

1. ogni Resource con `download()` attivo e senza `downloadUsing()`, come
   `ResourceSource` (id = slug della Resource);
2. i tracciati di caricamento (`import.layouts`, vedi
   [Caricamento da file](importazione.md));
3. la config `dataset.sources` dei moduli;
4. `Sources::set()` del sito.

```php
// config del modulo
'dataset' => ['sources' => ['google-merchant' => GoogleMerchantFeed::class]],

// sito
Sources::set('google-merchant', MyFeed::class); // sostituisce
Sources::set('orders', false);                  // toglie
```

`Sources::all()`, `find($id)` e `allowed($user, 'export')` leggono il
registro. Una fonte che non si costruisce va nel log e si salta.

## Una Resource come fonte

`ResourceSource` prende le colonne da `fileColumns()` della cornice della
lista o, senza, da `Model::tableSchema()`. Due colonne sullo stesso campo
ricevono chiavi distinte (`sku`, `sku_2`). Le righe sono quelle della
condizione e dell'ordine di `querySchema()`, ristrette dalla
[visibilità per riga](risorse/visibilita-per-riga.md); `fileRows()` le
sostituisce.

```php
public static function tableLayoutSchema(): TableLayoutSchema
{
    return TableLayoutSchema::for(static::class)
        ->download(['csv', 'xlsx'])
        ->fileColumns([
            Column::make('sku', 'Codice'),
            Column::make('price', 'Prezzo')->type(Type::Money),
            'name',                                                     // solo il campo
            ['label' => 'Cliente', 'value' => fn (array $row) => $row['name'].' '.$row['surname']],
        ])
        ->fileRows(fn ($condition) => Product::find($condition))        // facoltativo
        ->importLayout('catalog', 'Importa catalogo');                  // scheda «Importa» del bottone «File»
}
```

`downloadColumns()` è un alias deprecato di `fileColumns()`.

## Il bottone della lista

Sopra la lista c'è un solo bottone «File», che apre una finestra con fino a
tre schede: Esporta, Importa e Feed prodotti. Se la Resource ne ha una sola,
la finestra non mostra la barra delle schede.

La scheda **Esporta** ha il titolo di `download(label:)` (di base «Esporta») e
un form GET verso la rotta `backend.resource.{slug}.export.file`, con
`?format=` e, se serve, `?layout=`. Il formato è scelto con i radio (fino a
quattro formati, oltre con un select); il select «Tracciato» compare solo se
per la Resource ci sono tracciati salvati e almeno un formato li accetta, e
scarica colonne, ordine e intestazioni del tracciato. Senza `?layout`
l'export usa sempre le colonne del codice; un tracciato di un'altra fonte, o
senza colonne valide, viene ignorato. La finestra propone l'ultima scelta
(formato e tracciato) fatta in sessione per quella lista, se esiste ancora
(`ResourceDownloadController::lastChoice()`); il percorso con il formato nel
mezzo (`export`) resta valido per i link già in giro.

Con `downloadUsing()` il file lo scrive il gestore e i tracciati compaiono
solo se la Resource dice di quale fonte sono, con `downloadLayouts()`:

```php
->download(['xlsx' => 'Excel', 'csv' => 'CSV', 'feed' => 'Feed'])
->downloadUsing(fn (string $format, ?int $layout = null) => MyExport::send($format, $layout))
->downloadLayouts('catalog', ['xlsx', 'csv'])   // fonte, formati che li accettano
```

Il select elenca i tracciati della fonte `catalog` e, sotto il formato,
dice per quali formati valgono («I tracciati valgono per Excel e CSV»); il
gestore riceve l'id come secondo argomento (null senza tracciato o per un
formato escluso) e ricava le colonne con `Profile::for($fonte, $id)`, che
ignora un id di un'altra fonte. Senza `downloadLayouts()` la fonte è lo slug
della Resource e valgono tutti i formati.

`feeds([...])` aggiunge la scheda **Feed prodotti**, per i file a indirizzo
fisso (Catalogo Meta, Google Merchant Center) che si rigenerano da soli e si
incollano una volta nella piattaforma. Hanno un formato deciso dalla
piattaforma, quindi non sono tracciati: il loro formato esce dai radio di
Esporta (e con lui il testo d'aiuto, se era l'unico a non accettare i
tracciati) e un'ultima scelta salvata su quel formato ricade sul primo.

```php
->download(['xlsx' => 'Excel', 'csv' => 'CSV', 'meta' => 'Catalogo Meta'])
->feeds([[
    'format' => 'meta',                 // uno dei formati di download()
    'label' => 'Catalogo Meta',
    'url' => 'https://negozio.it/shared/feeds/catalogo-meta.csv',
    'updated_at' => filemtime($file),   // null: «Non ancora generato»
    'every' => 'Ogni 6 ore',            // facoltativo, accanto alla data
]])
```

Per ogni feed la scheda mostra il nome, l'indirizzo in un campo di sola
lettura con «Copia» (`copyText()` della lib), quando è stato aggiornato e
«Scarica ora», che passa dalla stessa rotta di export con `?format=`. Un feed
il cui formato non è tra quelli di `download()` (per esempio perché il
gestore non lo concede all'utente) non compare; senza feed la scheda non c'è.
Accendere un feed e sceglierne la frequenza resta compito del modulo che lo
scrive.

`importLayout($layout, $label)` aggiunge la scheda **Importa**: un form POST
verso il caricamento con la scelta del tracciato salvato, il file e il link
al modello; compare solo se il tracciato esiste e l'utente può usarlo.

Il bottone «File» non va reso dentro un `<form>`, per esempio in un
`backendTable()` incorporato in una scheda del form di una Resource: il
`<form>` della sua finestra verrebbe scartato già dal parser HTML e il
bottone invierebbe il form esterno. `data-wi-modal-detach` non lo evita: lo
script sposta la finestra solo dopo il parse, quando il `<form>` interno non
c'è più.

## Tracciati file

Dev → File → Tracciati file (`ImportLayoutResource`, solo admin, accanto a Download) elenca tutte le
fonti del registro: per ognuna si salvano le colonne in ordine e con
l'intestazione voluta. `Profile::for($source, $id)` dà le colonne di un
tracciato salvato (null se non è di quella fonte); il caricamento propone il
predefinito, l'export lo usa solo quando lo si sceglie nella scheda Esporta.

## Test

```bash
php tests/App/Dataset/DatasetTest.php
```
