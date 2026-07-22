# Colonne tabella: formatter con nome

`TableColumn::formatter('nome')` associa alla colonna un formatter registrato,
che riceve **l'intera riga** (non solo il valore della colonna) e restituisce
la stringa HTML della cella:

```php
TableColumn::key('prezzo')->formatter('immobili.prezzo'),
```

## Registrazione

Il formatter si registra sul registry con un nome, oppure si passa come
closure inline direttamente sulla colonna (vedi «Forma inline» più sotto):

```php
use Wonder\Backend\Table\ColumnFormatterRegistry;

ColumnFormatterRegistry::register('immobili.prezzo', static fn (array $row): string =>
    '€ ' . number_format((int) ($row['prezzo'] ?? 0), 0, ',', '.'));
```

`ColumnFormatterRegistry` è la whitelist: un nome non registrato rende la
cella vuota (`''`), il formatter **non** viene mai eseguito.

## Vincolo: nomi, non closure sul filo

`TableColumn::formatter()` accetta un **nome** (stringa) oppure una closure
inline — mai la closure direttamente sul filo del giro AJAX di DataTables
(ordinamento, ricerca, paginazione): quel POST porta solo stringhe e deve
poter essere risolto lato server a ogni richiesta. Una closure inline viene
quindi risolta server-side in una chiave derivata (vedi "Forma inline" sotto)
prima di raggiungere il client; solo quella chiave viaggia sul filo. In
alternativa puoi registrare la closure una volta sul registry (in boot) e
riferirla per nome nello schema della colonna.

## Forma inline

```php
TableColumn::key('prezzo')->formatter(
    fn (array $row): string => '€ ' . number_format((int) ($row['prezzo'] ?? 0), 0, ',', '.')
),
```

Passare una closure direttamente a `->formatter()` è supportato: non serve
registrarla esplicitamente su `ColumnFormatterRegistry::register()`. La
closure **non viaggia mai sul filo** — il rendering della tabella (in
`ResourceTableRenderer::legacyColumnFormat()`) la sostituisce con la chiave
derivata `{slug}.{colonna}` (slug della resource + nome della colonna), e sul
client/nel POST del giro AJAX viaggia solo quella stringa.

`ColumnFormatterRegistry` acquisisce la closure con uno scan lazy delle
resource (`registerFromResource()`, la stessa logica di
`ColumnFunctionRegistry::fromResources` per le `function`): alla prima
richiesta che tocca il registry, ogni `tableSchema()` viene ispezionato e le
colonne con un `formatter` che è una `\Closure` vengono registrate sotto
`{slug}.{colonna}`. Da quel momento la chiave derivata è risolvibile come
qualunque altro formatter registrato per nome.

## Colonne immagine: il formatter fornisce il `src`

Su una colonna di tipo `image` (`TableColumn::key('image')->image()`) il
formatter **non** possiede l'intera cella: fornisce la **sorgente**
dell'immagine (URL/`src`) e il framework la avvolge nel medesimo `<img>` del
tipo image nativo (con `htmlspecialchars` sul `src`). È il modo per rendere una
copertina calcolata a runtime — per riga, magari da un'altra tabella — senza
doverla denormalizzare come colonna:

```php
use Wonder\Plugin\Immobili\Services\ImmobilePresenter;

TableColumn::key('image')->image()->formatter(
    static fn (array $row): string => (new ImmobilePresenter())->coverImage($row)
),
```

Il formatter riceve l'intera riga e ritorna una stringa: se vuota, la cella
resta vuota (nessun `<img>` rotto). Vale sia per la forma inline (closure) sia
per un formatter registrato per nome. Per ogni altro tipo di colonna il
formatter possiede invece l'intera cella (vedi «Escape»).

## Escape

Il formatter possiede l'intera cella: l'HTML restituito viene emesso **raw**,
come già avviene per `function`/`badge`. Il formatter è responsabile del
proprio escaping (`htmlspecialchars` sui dati non fidati) — nessun
href-wrap o formattazione aggiuntiva viene applicata automaticamente. Fa
eccezione il tipo `image`: lì il formatter fornisce solo il `src` e
l'escaping/`<img>` sono a carico del framework (vedi sopra).

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
