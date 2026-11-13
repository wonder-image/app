# Segnali di riga

Un pallino nella prima colonna della lista segnala le righe da sistemare.
Il motivo compare nel tooltip. Il pallino è piccolo (circa metà del testo) e semitrasparente
per non pesare sulla lista: solo classi Bootstrap (`p-1 rounded-circle
bg-{livello} opacity-50`).

- **Giallo** (`RowSignal::warning`): manca un dato, per esempio l'email o i
  dati di fatturazione.
- **Rosso** (`RowSignal::danger`): un dato da verificare, per esempio un
  doppione.

Se su una riga ci sono segnali dei due colori, il pallino è rosso e nel
tooltip i motivi rossi vengono prima. Una riga senza segnali ha la cella
vuota.

Le classi sono `Wonder\Backend\Table\RowSignal` e
`Wonder\Backend\Table\RowSignals`. Il meccanismo è opt-in: una lista senza
segnali non cambia.

## Il segnale

```php
RowSignal::warning(
    'missing_email',                                      // codice: [a-z0-9_]
    "COALESCE(TRIM({table}.email), '') = ''",             // condizione SQL
    __t('contacts.signals.missing_email')                 // motivo nel tooltip
);
```

- **Condizione.** È SQL scritto dallo sviluppatore, mai input dell'utente.
  `{table}` diventa la tabella della lista. Le sottoquery vanno bene, per
  esempio `NOT EXISTS (SELECT 1 FROM points p WHERE p.owner_id = {table}.id)`.
- **Niente `AS` nella condizione.** Il renderer delle liste legge ogni `AS`
  come alias di colonna. Nelle sottoquery scrivi `FROM points p`, non
  `FROM points AS p`.
- **Accensione condizionata.** `->when(fn () => Brands::enabled())` tiene il
  segnale solo quando la closure risponde `true`. Restituisce una copia: il
  segnale originale non cambia.

## Nella Resource

La Resource dichiara i segnali predefiniti e li passa a
`RowSignals::resolve()`, insieme al nome della lista:

```php
public static function rowSignals(): array
{
    return RowSignals::resolve('companies', [
        RowSignal::warning('missing_contact', '...', __t('...')),
        RowSignal::warning('missing_brand', '...', __t('...'))->when(fn () => Brands::enabled()),
    ]);
}

public static function tableSchema(): array
{
    return [
        RowSignals::column(fn () => static::rowSignals()),   // prima colonna
        TableColumn::key('name')->text(),
        // ...
    ];
}

public static function tableLayoutSchema(): TableLayoutSchema
{
    $signals = static::rowSignals();
    $table = static::modelClass()::$table;

    return (new TableLayoutSchema)
        // Il select dei segnali va nello stesso select() delle altre
        // colonne calcolate: una seconda chiamata lo sostituirebbe.
        ->select(implode(",\n", ["{$table}.*", RowSignals::selectSql($signals, $table)]))
        ->filterQuery(__t('...da_completare'), RowSignals::whereAny($signals, $table));
}
```

### Il filtro

Per il filtro «Da completare» usa `RowSignals::whereAny($signals, $table)`,
non l'alias `row_signals`: l'alias del select non si può usare nel `WHERE`.
Il terzo argomento limita il filtro a un livello:

```php
RowSignals::whereAny($signals, $table, RowSignal::DANGER)
```

### La colonna

`RowSignals::column()` crea una colonna stretta e senza intestazione, che
resta fuori dall'export (`exportable(false)`). I segnali si leggono una sola
volta per ogni disegno della lista, non una volta per riga.

### La scheda

Il pallino legge i codici dalla riga. Nella scheda di un record puoi usare lo
stesso SQL limitato a un id. `fromRow()` restituisce i segnali accesi:

```php
$row = $sql->Select($table, "{$table}.id = {$id}", 1, null, null, RowSignals::selectSql($signals, $table))->row;
$on = RowSignals::fromRow($signals, (array) $row);
```

## Cambiare i segnali dal sito o da un modulo

Puoi cambiare i segnali da `custom/function/function.php` o dall'avvio di un
modulo:

```php
use Wonder\Backend\Table\RowSignal;
use Wonder\Backend\Table\RowSignals;

RowSignals::add('companies', RowSignal::warning('missing_logo', "COALESCE({table}.logo, '') = ''", 'Manca il logo'));
RowSignals::remove('companies', 'missing_brand');
```

- **Le chiavi sono le liste, non le tabelle.** Due liste sulla stessa tabella
  hanno ciascuna il proprio insieme di segnali, per esempio clienti e
  fornitori.
- **Le modifiche si applicano quando la lista si disegna.** L'ordine di
  caricamento non conta: puoi chiamare `remove()` anche prima che il modulo
  dichiari il segnale.
- **`add()` con un codice già esistente lo sostituisce**, per esempio per
  cambiarne la condizione o il testo.

## Errori comuni

- **Il pallino non compare** → il select dei segnali è finito in una seconda
  chiamata a `select()`, che ha sostituito la prima. Uniscili in un'unica
  chiamata.
- **Errore SQL su un alias** → la condizione contiene `AS`.
- **Il filtro conta righe diverse dai pallini** → filtro e colonna usano liste
  di segnali diverse. Passa a tutti e due lo stesso `rowSignals()`.
