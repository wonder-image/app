# Tracciati di import ed export

Import ed export parlano la stessa lingua, `Wonder\App\Dataset`: una **fonte**
(`Source`) ha un id, un'etichetta, delle **colonne** (`Column`) e dice per
quali azioni si può usare. Un export legge le righe dalla fonte; un
caricamento le scrive. Le colonne, l'ordine e le intestazioni scelte
dall'amministratore si salvano in Set-up → **Tracciati file**, valide per
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
        ->importLayout('catalog', 'Importa catalogo');                  // bottone Importa
}
```

`downloadColumns()` è un alias deprecato di `fileColumns()`.

## Il bottone della lista

Sopra la lista, `Esporta` è un menu con i formati di `download()`. Se per la
Resource ci sono tracciati salvati, sotto l'intestazione «Tracciati» compare
una voce per tracciato e formato, che scarica con `?layout=ID`: colonne,
ordine e intestazioni del tracciato. Senza `?layout` l'export usa sempre le
colonne del codice; un tracciato di un'altra fonte, o senza colonne valide,
viene ignorato. Con `downloadUsing()` il file lo
scrive il sito e i tracciati non compaiono.

`importLayout($layout, $label)` aggiunge `Importa`, verso il caricamento con
quel tracciato; compare solo se il tracciato esiste e l'utente può usarlo.
Con entrambi i bottoni stanno in un `ButtonGroup` «Esporta e importa».

## Tracciati file

Set-up → Tracciati file (`ImportLayoutResource`, solo admin) elenca tutte le
fonti del registro: per ognuna si salvano le colonne in ordine e con
l'intestazione voluta. `Profile::for($source, $id)` dà le colonne di un
tracciato salvato (null se non è di quella fonte); il caricamento propone il
predefinito, l'export lo usa solo quando lo si sceglie dal menu.

## Test

```bash
php tests/App/Dataset/DatasetTest.php
```
