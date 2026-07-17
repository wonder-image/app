# Colonne tabella: formatter con nome

`TableColumn::formatter('nome')` associa alla colonna un formatter registrato,
che riceve **l'intera riga** (non solo il valore della colonna) e restituisce
la stringa HTML della cella:

```php
TableColumn::key('prezzo')->formatter('immobili.prezzo'),
```

## Registrazione

Il formatter si registra sul registry, non si passa come closure inline sulla
colonna:

```php
use Wonder\Backend\Table\ColumnFormatterRegistry;

ColumnFormatterRegistry::register('immobili.prezzo', static fn (array $row): string =>
    '€ ' . number_format((int) ($row['prezzo'] ?? 0), 0, ',', '.'));
```

`ColumnFormatterRegistry` è la whitelist: un nome non registrato rende la
cella vuota (`''`), il formatter **non** viene mai eseguito.

## Vincolo: nomi, non closure

`TableColumn::formatter()` accetta solo un **nome** (stringa), mai una
closure diretta. Il nome viaggia nel POST del giro AJAX di DataTables
(ordinamento, ricerca, paginazione) e deve poter essere risolto lato server a
ogni richiesta: una closure non è serializzabile su quel giro. Registra la
closure una volta sul registry (in boot) e riferiscila per nome nello schema
della colonna.

## Escape

Il formatter possiede l'intera cella: l'HTML restituito viene emesso **raw**,
come già avviene per `function`/`badge`. Il formatter è responsabile del
proprio escaping (`htmlspecialchars` sui dati non fidati) — nessun
href-wrap o formattazione aggiuntiva viene applicata automaticamente.

## Precedenza

`badge` > `formatter` > `function` > valore semplice. Se la colonna ha sia
`badge` che `formatter`, vince il badge.

## Dove registrare: `boot.files`

I moduli registrano i propri formatter usando il meccanismo `boot.files` già
esistente in `module.json`, tipicamente in un file dedicato:

```json
{
    "boot": { "files": ["config/formatters.php"] }
}
```

```php
// config/formatters.php
use Wonder\Backend\Table\ColumnFormatterRegistry;

ColumnFormatterRegistry::register('immobili.prezzo', static fn (array $row): string => /* ... */);
```

## Limiti

Le colonne speciali `action_button` e `position_arrow_up`/`position_arrow_down`
non passano da `Field::setValue()`: un `formatter` associato a queste non ha
effetto. Usa `formatter` solo su colonne di valore.

## Migrazione (BC)

Il metodo `Column::callback(callable)`, mai cablato nel rendering (era di fatto
un no-op: la sua chiave di schema non veniva letta da nessuna parte), è stato
**rimosso**. Chi lo chiamava su una colonna tabella non otteneva alcun effetto;
sostituiscilo con `formatter('nome')` + `ColumnFormatterRegistry::register(...)`.
Tecnicamente è un cambiamento non retro-compatibile della libreria: un sito che
invocava `->callback()` andrà aggiornato (raggio d'impatto atteso: nullo).
