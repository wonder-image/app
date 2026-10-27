# Visibilità per riga — design

**Data:** 2026-10-27
**Repo:** app (framework). Il gestionale ne è il primo consumatore (gestionale#77) ma non viene toccato da questa modifica.
**Tipo:** architetturale

## Contesto

Issue: wonder-image/app#151. Oggi i permessi del core lavorano per tabella: `permissionSchema()` dice se un ruolo può leggere, modificare o eliminare le righe di una Resource, ma non quali righe. Il gestionale ha bisogno degli agenti di vendita (gestionale#77): un agente deve vedere solo i clienti, i preventivi e gli ordini che gli sono assegnati, mentre amministrazione e titolare vedono tutto.

Scrivere un filtro a mano in ogni lista, pagina, endpoint e tool MCP sarebbe fragile: basta dimenticarne uno perché le righe degli altri escano. Serve una sola regola, dichiarata una volta, che il core applica da solo in ogni punto da cui legge le righe di quella tabella.

Il vincolo più importante: **un'area senza regola si comporta esattamente come oggi.** Nessuna query, nessun SQL generato e nessuna risposta cambiano per una tabella senza regola. Unica eccezione voluta: un id inesistente nelle pagine e nelle API generate passa da 500 a 404 (vedi decisione 7).

**Decisioni:**

1. **La regola è per tabella e la scrive il codice.** Riceve l'utente corrente e restituisce una condizione SQL: un array `colonna => valore` oppure una stringa. `null` significa nessuna restrizione per quell'utente. La regola sceglie in base all'utente; per l'agente del gestionale: `in_array('agent', $u->authority) ? ['agent_id' => $u->id] : null`.
2. **Due modi di dichiararla, con lo stesso effetto.**
   - Dalla Resource: `public static function rowVisibility(object $user): string|array|null`. Per default restituisce `null`.
   - Dall'area, cioè dal sito o dal modulo, senza toccare la Resource: `RowVisibility::rule($table, $callable, $seeAll)` nella configurazione (per esempio `custom/config/config.php` o il `boot()` del modulo).

   Le regole della stessa tabella si combinano in AND: una dichiarazione restringe, non allarga mai.
3. **I ruoli che vedono tutto.** Per default sono `admin` e `administrator`, e la regola non viene nemmeno chiamata. Una Resource li cambia con `rowVisibilitySeeAll(): array`, una regola d'area con il terzo argomento di `rule()`.
4. **Utente sconosciuto vuol dire nessuna restrizione.** CLI, scheduler, `forge` e `@system` senza sessione non hanno un utente (`RowVisibility::currentUser()` restituisce `null`) e leggono tutto, come oggi. Le richieste web con sessione hanno sempre un utente; senza sessione l'utente è anonimo (`id` 0, nessuna authority) e decide la regola.
5. **Una riga nascosta non esiste.** Aprirla, modificarla, eliminarla o lanciare un'azione su di essa per id risponde 404, mai 403: un 403 confermerebbe che l'id esiste.
6. **La regola non può parlare di `deleted`.** `Model::find()` aggiunge il filtro di soft delete solo se la condizione non nomina `deleted`: una regola che lo nominasse farebbe ricomparire le righe eliminate. `RowVisibility` lancia `LogicException` se la condizione stringa contiene la parola `deleted`; le chiavi array `deleted` sono vietate allo stesso modo.
7. **Record mancante nelle pagine e API generate: 404 invece di 500.** Oggi `ResourcePageController` e `ResourceApiController` lanciano `RuntimeException` (500) quando l'id non esiste. Con la regola, una riga nascosta e una riga mancante devono rispondere allo stesso modo, e la risposta corretta per entrambe è 404. Il cambio riguarda anche le aree senza regola: è voluto e va nel CHANGELOG sotto "Changed".
8. **Punti d'ingresso per il codice del consumatore.** Ciò che il core non legge da solo (tool MCP, widget della dashboard, opzioni costruite a mano) usa `RowVisibility::current()` o `RowVisibility::for($user)`, che restituiscono uno `RowVisibilityScope` con `find()`, `findById()`, `count()` e `condition()`.

## Architettura

### Unità

Namespace `Wonder\App\Visibility\`.

- **`RowVisibility`** (statica, finale). È il registro delle regole e il punto unico di applicazione.
  - `rule(string $table, callable $rule, ?array $seeAll = null)`, `reset()`, `hasRule($table)`.
  - `conditionFor(string $table, ?object $user): string|array|null`. Raccoglie le regole d'area e quelle delle Resource della tabella, salta quelle per cui l'utente ha un ruolo "vede tutto" e combina il resto in AND.
  - `apply(string $table, string|array|null $condition, ?object $user): string|array|null`. Senza regola applicabile restituisce `$condition` **identica** (stesso tipo e stesso valore). Con una regola:
    - due array senza chiavi in comune diventano un array unico;
    - un array con valori semplici resta array;
    - in tutti gli altri casi la regola diventa una stringa `(condizione) AND (regola)`.

    Gli array diventano SQL con `Query::bind()` e `Query::escapeIdentifier()`: `IN (...)` per le liste, `IS NULL` per `null`, `1 = 0` per una lista vuota.
  - `visible(string $table, int $id, ?object $user): bool`. Senza regola applicabile restituisce `true` senza fare query.
  - `currentUser()`, `sessionUser()`, `as($user, $callback)`, `current()` e `for($user)`.
- **`RowVisibilityScope`** lega le letture a un utente: `find(Model::class, ...)`, `findById(Model::class, $id)`, `count(Model::class, $condition)`, `condition($table, $condition)`.
- **`Resource::rowVisibility()`** e **`Resource::rowVisibilitySeeAll()`**: hook con default neutro. `RowVisibility` li chiama su ogni Resource **risolta** dal `ResourceRegistry` (quindi con la precedenza sito > modulo > core) la cui `modelTable()` è la tabella; `null` vuol dire nessuna regola. La mappa tabella → Resource viene calcolata una volta per richiesta.

### Utente corrente

- **Pagine backend e `api/backend/*`:** la variabile globale `$USER` (`authorizeUser('backend', ...)`, impostata da `app/service/auth.php` per ogni route `guarded()`), letta con `LegacyGlobals::get('USER')`.
- **API generate delle Resource:** con un bearer vero l'utente del token (`Endpoint::user()`, nuovo getter). Con il token di pagina o l'accesso di sessione (`isPageToken()`), che autenticano come `@system`, vale l'utente della sessione (`RowVisibility::sessionUser()`), oppure l'anonimo.
- **`RowVisibility::as($user, fn () => ...)`** imposta un utente per la durata della callback (test, comandi che agiscono per conto di qualcuno).

### Punti di applicazione

| Punto | Chi applica la regola |
|---|---|
| Liste backend (`ListProvider::fetch`: dati, ricerca, filtri, conteggi filtrato e totale) | il core: la regola entra sia in `$custom->query` (conteggio iniziale) sia in `query_all` (il `whereAll` di SSP, che vale per dati, filtrato e totale) |
| Export della lista (`ResourceDownloadController::loadRows`) | il core |
| Pagine record (vista, modifica, salvataggio) | il core, con 404 tramite `ResourceRows` condiviso dai due controller |
| Pagina singleton | il core: se la riga esiste ma è nascosta, 404 (non "crea nuovo") |
| Azioni per id in `api/backend/*` (`delete`, `change/boolean`, `active`, `visible`, `move` e la sua riga vicina, `file/delete`, `file/move`, `delete-icon`) | il core: `RowVisibility::visible()` dopo `TableAuthorization`, poi 404 |
| API generate (`index`, `show`, `update`, `destroy`, upsert di `store`) | il core |
| MCP | punto d'ingresso: `RowVisibility::for(infoUser($caller->userId))`. I tool del gestionale leggono con `Model::find` diretto e vanno adattati nel gestionale |
| Totali della dashboard | punto d'ingresso: `RowVisibility::current()->count(...)`. `HomeWidget::render()` non riceve l'utente e i widget del gestionale leggono da soli |
| Campi relazione | punto d'ingresso: il core non ha un caricatore di opzioni da Model (le opzioni sono array costruiti dal consumatore), quindi chi le costruisce usa `RowVisibility::current()->find(...)` |

`ResourceRows` (`Wonder\App\Visibility\ResourceRows`) raccoglie la condizione per id oggi duplicata in `ResourcePageController::resourceConditionForId()` e `ResourceApiController::resourceConditionForId()`, con la regola applicata. Le eccezioni restano per area: `NotFoundHttpException` nel backend, `EndpointException(…, 404)` nelle API.

### Errori e sicurezza

- La regola è SQL scritto dal codice, come `$order` e le chiavi delle condizioni array. I valori degli array passano da `Query::bind()`; una stringa viene inserita così com'è, tra parentesi.
- Il nome della tabella passa da `Query::escapeIdentifier()` nelle query generate da `visible()`.
- Una regola che lancia un'eccezione interrompe la richiesta (500): non si ripiega mai su "vedi tutto".

## Test

`tests/Visibility/RowVisibilityTest.php`, senza database:

- una Resource di test con la regola attiva e una senza;
- senza regola: `apply()` restituisce la condizione identica e l'SQL di `SSP::complexQueries()` è identico byte per byte a quello di oggi;
- con regola: la condizione compare in `data`, `filtered` e `total` di SSP e nella condizione per id delle pagine e delle API;
- `admin` e `administrator` saltano la regola, un ruolo "vede tutto" personalizzato pure;
- combinazione di array e stringhe, `IN`, `IS NULL`, lista vuota;
- il divieto di `deleted`;
- `visible()` e le eccezioni 404 di backend e API;
- l'utente delle API: con il token di pagina vale la sessione, con un bearer vale l'utente del token.

Poi tutta la suite `tests/`.

## Documentazione

- `docs/app/concetti/risorse/visibilita-per-riga.md` (nuova) e link da `docs/app/concetti/risorse/README.md` / `SUMMARY.md`;
- `AGENTS.md`;
- skill `wi-app` (`references/permissions-and-users.md`) nel repository `wonder-image/skills`;
- CHANGELOG, sezione "Unreleased".

## Fuori scope

- **Scrittura fuori dal proprio perimetro.** Un agente può salvare una riga visibile cambiando `agent_id` verso un altro utente, o crearne una già assegnata ad altri. La regola filtra le letture, non valida i valori scritti: il gestionale deve impedirlo in `formSchema()` (campo nascosto o forzato) o nella validazione. Possibile seguito nel core: verificare che la riga resti visibile dopo il salvataggio.
- **Permesso di creare.** Resta quello di `permissionSchema()`.
- **Righe figlie dei Repeater.** Appartengono a un genitore visibile e vengono lette per `parent_id`.
- **Ricerca sulle relazioni di SSP.** Una ricerca può trovare la riga visibile tramite il testo di una riga collegata nascosta. Non mostra la riga nascosta, ma conferma che contiene quel testo.
- **`/api/export`** (`sqlExport`): è solo per `admin`, che vede comunque tutto.
- **Adozione nel gestionale:** tool MCP, widget della dashboard, opzioni dei clienti e dichiarazione della regola per gli agenti (gestionale#77).
- **`QuickCreate`**: agisce come `@system` e crea soltanto.
