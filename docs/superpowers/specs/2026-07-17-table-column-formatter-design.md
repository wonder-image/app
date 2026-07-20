# Table column formatter — design

**Data:** 2026-07-17
**Repo primario:** `wonder-image/app` (framework). Primo consumatore: modulo `wonder-image/immobili`.
**Stato:** proposta di design, in attesa di review.

## Motivazione

Nel backend, le colonne tabella hanno bisogno di trasformare il valore grezzo in
output formattato che dipende da **più campi della riga**: superficie → `48 mq`,
prezzo → `€ 255.000` (arrotondato, senza decimali) con `/mese` se in affitto,
miniatura → `<img>` della foto copertina, nome → titolo + sottotitolo + link alla
modifica. Oggi l'unico strumento è `TableColumn::function('nomeGlobale', [colonne])`,
che richiede una funzione globale, enumerare le colonne a mano, e un `return`
sub-property per gli oggetti. La classe `Column` espone anche `callback(callable)`,
ma è **codice morto**: non è cablato in nessun punto del rendering.

Obiettivo: una API `TableColumn::formatter('nome')` più pulita, che riceve
**l'intera riga** e possiede l'intero contenuto della cella.

## Vincolo tecnico non negoziabile

Il formato delle colonne della lista fa il giro **client → server**. La lista è
renderizzata via AJAX: `app/http/api/backend/list-table.php` →
`ListProvider::buildColumns($_POST['fields'])`. I descrittori di formato (con
`function`/`badge`/`href`) arrivano dal POST di DataTables.

**Conseguenza:** un formatter **non può essere una closure** (non è serializzabile
nel POST). Deve essere un **nome (stringa)** risolto lato server da un registry.
Il registry funge anche da **whitelist di sicurezza**: un nome non registrato non
viene mai invocato — senza whitelist un client autenticato potrebbe far eseguire
codice arbitrario. È lo stesso modello di `ColumnFunctionRegistry`.

Questo è il motivo per cui `callback(callable)` non poteva funzionare e va rimosso.

## Design del framework (`wonder-image/app`)

### 1. `ColumnFormatterRegistry` (nuovo)
`class/Backend/Table/ColumnFormatterRegistry.php`

```php
final class ColumnFormatterRegistry
{
    /** @var array<string, callable> */
    private static array $formatters = [];

    public static function register(string $name, callable $formatter): void;
    public static function has(string $name): bool;
    /** Invoca il formatter registrato; '' se il nome non è registrato. */
    public static function call(string $name, array $row): string;
    public static function reset(): void; // per i test
}
```

- Chiave = nome namespaced (es. `immobili.prezzo`). Il registry È la whitelist.
- La callable ha firma `fn(array $row): string` e riceve l'intera riga
  associativa. Restituisce l'HTML finale della cella.

### 2. `Column::formatter(string $name)`
`class/Elements/Table/Column.php` (ereditato da `TableColumn`).

```php
public function formatter(string $name): self
{
    return $this->schema('formatter', trim($name));
}
```

Rimuovere il metodo morto `Column::callback()`.

### 3. Passthrough in `ResourceTableRenderer::legacyColumnFormat()`
`class/Backend/Support/ResourceTableRenderer.php` — aggiungere, accanto a
`function`/`badge`/`link`:

```php
if (isset($config['formatter']) && is_string($config['formatter']) && $config['formatter'] !== '') {
    $format['formatter'] = $config['formatter'];
}
```

Così il nome del formatter entra in `$format`, viaggia nel giro DataTables, e
torna disponibile in `Field`.

### 4. Dispatch in `Field::setValue()`
`class/Backend/Table/Field.php` — dopo il ramo `badge`, prima del ramo `function`:

```php
if (isset($format['formatter']) && is_string($format['formatter'])) {
    if (ColumnFormatterRegistry::has($format['formatter'])) {
        return ColumnFormatterRegistry::call($format['formatter'], $this->row);
    }
    return ''; // nome non registrato → cella vuota, mai esecuzione arbitraria
}
```

**Precedenza:** `badge` > `formatter` > `function` > valore semplice. Il formatter
**possiede l'intera cella**: niente formattazione-tipo aggiuntiva, niente
href-wrap automatico (chi vuole un link lo costruisce nel formatter, es. con
`__r(...)`). Modello mentale: "formatter = controlli tu il contenuto della cella".

### 5. Sicurezza / escaping
L'HTML restituito è emesso raw (come già per `function`/`badge`). Il formatter è
codice sviluppatore, registrato server-side: è responsabile dell'escape dei dati
riga che inserisce. Documentare nella docstring del metodo e in `docs/app`.

### 6. Registrazione lato modulo/sito
Nessuna nuova interfaccia: si usa il meccanismo **`boot.files`** già esistente nei
manifest dei moduli (`Manifest::bootFiles()` → `Registry::bootFiles()` →
`app/config/config.php` li fa `require` al bootstrap, ad ogni request incluso
l'AJAX). Un modulo dichiara un boot file nel `module.json` che chiama
`ColumnFormatterRegistry::register(...)`. I siti registrano da `custom/`.

## Uso lato modulo `immobili` (il deliverable)

### Boot file di registrazione
Nuovo `config/formatters.php`, dichiarato in `module.json`:
```json
"boot": { "files": ["config/formatters.php"] }
```
Registra tre formatter, delegando la logica pura a helper globali già
autoloadati (`src/helpers.php`):

- **`immobili.superficie`** → `immobiliFormatSuperficie((int)$row['superficie'])`
  ⇒ `"48 mq"` (vuoto se 0/mancante).
- **`immobili.prezzo`** → arrotondato senza decimali, separatore migliaia, prefisso
  `€ `. Vendita (`contratto_id !== 'A'`) usa `prezzo`; affitto (`contratto_id === 'A'`)
  usa `prezzo_affitto` e appende `/mese`. Entrambi sono colonne **già esistenti**
  (0 decimali): nessuna nuova colonna DB, solo rielaborazione. Se
  `trattativa_riservata` / `trattativa_riservata_affitto` è attivo (o prezzo ≤ 0)
  ⇒ `Trattativa riservata`. Verificare in impl, sui dati reali, che
  `prezzo_affitto` sia effettivamente popolato per gli affitti; in caso contrario
  fare fallback su `prezzo`, sempre senza creare colonne.
- **`immobili.nome`** (whole-row + link + miniatura, dimostra il vantaggio su
  `->function()`): la **miniatura è ripiegata in questa cella** (niente colonna
  virtuale `foto`, che romperebbe l'SSP). Layout `d-flex`: a sinistra la foto
  copertina, a destra titolo + sottotitolo. Titolo = `nome` (riferimento) in
  grassetto con link alla modifica; sottotitolo =
  `{tipologia_nome} | {ucwords(strada)} {indirizzo}, {comune_nome}`. Markup:
  `<a href="{edit}" class="d-flex ..."><img .../><span><span class="fw-semibold d-block">{nome}</span><span class="d-block text-muted small">{sub}</span></span></a>`,
  con `{edit} = __r('backend.resource.immobili.edit', ['id' => $row['id']])`. La
  copertina la risolve `ImmobilePresenter::coverImage(array $row)` (riusa il
  metodo privato `images()` che già costruisce l'URL webp/resized). In impl
  verificare la semantica di `strada`/`indirizzo`/`civico` sui dati per evitare
  duplicazioni; escape dei campi riga inseriti.

> **Accesso ai campi riga:** `SSP::complex()` esegue `SELECT *`, quindi ogni
> formatter riceve l'intera riga della tabella — anche i campi non mostrati come
> colonna (`tipologia_nome`, `strada`, `indirizzo`). I formatter restano difensivi
> (`?? ''`) perché il render iniziale non-AJAX potrebbe non passare da `SELECT *`.

### `ImmobileResource::tableSchema()` finale
```php
TableColumn::key('evidence')->evidenceBadge(true)->badgeVariant('badgeIcon')->size('little'),
TableColumn::key('nome')->formatter('immobili.nome')->size('big'),
TableColumn::key('comune_nome')->text()->size('medium'),
TableColumn::key('prezzo')->formatter('immobili.prezzo'),
TableColumn::key('superficie')->formatter('immobili.superficie')->size('little'),
TableColumn::key('creation')->datetime()->sortable(),
TableColumn::key('sold')->booleanBadge('sold')
    ->badgeOff('In vendita', 'bi bi-tag', 'primary')
    ->badgeOn('Venduto', 'bi bi-check2-circle', 'dark')->size('little'),
TableColumn::key('visible')->visibleBadge(true)->size('little'),
TableColumn::key('actions')->button()->actions(['edit', 'visible', 'evidence', 'delete']),
```
Note: tutte le colonne sono reali (nessuna colonna virtuale). `nome` usa il
formatter ma resta la colonna DB `nome` (ordinabile/ricercabile se serve).
`badgeIcon` esiste già in `BooleanBadge::render()` — nessuna modifica al framework
per la stella.

## Consegna al sito per il test

Il sito `immobili-site` installa `wonder-image/app` da git (`dev-main`, cioè il
branch `main` del framework), **non** come symlink (solo `wonder-image/immobili` è
un path repo symlinkato). **Consegna scelta:** commit sul branch `main` di
`packages/app` + push su origin, poi `composer update wonder-image/app` nel sito.
Il push è outward-facing: va confermato dall'utente al momento. La feature va
sviluppata su un branch dedicato e mergiata in `main` prima del push (il branch
correntemente attivo, `fix/skills-all-flag-agent-dir`, non va toccato).

## Testing / verifica

- Unit: `ColumnFormatterRegistry` (register/has/call, nome sconosciuto ⇒ `''`).
- Unit puri: `immobiliFormatSuperficie`, `immobiliFormatPrezzo` (vendita, affitto
  `/mese`, arrotondamento, ≤ 0) in `tests/` del modulo.
- `php -l` su ogni file toccato; `composer dump-autoload` dove servono classi nuove.
- End-to-end nel sito: caricare la lista immobili, verificare stella `badgeIcon`,
  miniatura, `48 mq`, prezzo arrotondato / `/mese`, nome con sottotitolo e link,
  e che l'aggiornamento AJAX (ordinamento/ricerca/pagina) mantenga i formatter.

## Fuori scope

- Nessuna closure diretta come formatter (vincolo AJAX).
- Nessun refactor del meccanismo `->function()` esistente (resta per retro-compat).
- Nessun cambiamento al tema/lib CSS: si riusano classi Bootstrap/lib esistenti.
```
