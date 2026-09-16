# Prerequisiti del core per i moduli gestionale ed ecommerce

- **Data:** 2026-09-16
- **Repo:** `wonder-image/app` (framework)
- **Stato:** design approvato, in attesa di piano di implementazione
- **Origine:** spec di architettura di `wonder-image/gestionale` + `wonder-image/ecommerce`
  (`packages/gestionale/docs/superpowers/specs/2026-09-11-gestionale-ecommerce-architettura-design.md`,
  capitolo 10.2, lavori "prima del gestionale")

## Contesto

I moduli `wonder-image/gestionale` ed `wonder-image/ecommerce` si integrano in
`wonder-image/app` e hanno bisogno di alcune capacità generiche del framework. Tutto
ciò che segue sta nel core ed è riusabile da qualunque modulo o sito: **nessuna
logica specifica del gestionale**.

Il `.env` appartiene ai boilerplate (`new-site`, `immobili-site`, `rsvp-site`, il
futuro `ecommerce-site`): il framework legge le variabili, i boilerplate le
dichiarano nei propri `.env.example` e `forge start` le scrive nel `.env` locale.

### Stato del core verificato (2.2.0, 2026-09-16)

| Area | Cosa c'è oggi | Evidenza |
|---|---|---|
| Ambiente | nessuna nozione di ambiente: solo `APP_DEBUG`; il "locale" è il flag `forge update --local` | `class/App/Debug.php`, `class/Console/Commands/Update.php` |
| Sync | export senza `id` e `deleted`, senza ordine; import delle tabelle `multiRow` con `TRUNCATE` e reinserimento, quindi `id` rinumerati e righe cancellate che tornano visibili | `class/App/Support/TableSync.php` |
| Import in `forge update` | chiamato da un file di update prima della rigenerazione dei CSS | `app/build/update/css.php` |
| Moduli | `Registry::enabled()` restituisce i moduli nell'ordine della configurazione, senza ordinamento per dipendenze; il manifest espone `get('database.*')` e i `boot.files` | `class/App/Module/Registry.php`, `Manifest.php` |
| Dati aziendali | indirizzo e orari sono sezioni della pagina legacy che salva insieme società, sede legale, indirizzo, social e orari; gli orari finiscono sia in `society_timetable` sia nel JSON `society_address.timetable` | `app/http/backend/config/corporate-data.php`, `class/App/PageSchema/CorporateDataPageSchema.php` |
| Transazioni | nessun helper; `ConsentService` usa direttamente `begin_transaction`, `commit`, `rollback` e `FOR UPDATE`; le funzioni `sql*()` e i Model sembrano condividere la connessione di `Connection::Connect()` (cache per host, utente e database) | `class/Consent/Service/ConsentService.php`, `class/Sql/Connection.php`, `class/Sql/ConnectionPool.php` |
| Lock | `UpdateLock` con `GET_LOCK` e `RELEASE_LOCK` | `class/App/UpdateLock.php` |
| Pagine backend | `PageSchema` con titoli, sottotitoli e azioni dell'header, resi da `ResourcePagePresenter` tramite `PageActionNormalizer` | `class/App/ResourceSchema/PageSchema.php`, `class/Backend/Support/ResourcePagePresenter.php` |
| Valori fiscali | nessuna classe delle aliquote IVA né dell'esigibilità; `Natura` contiene ancora N2, N3 e N6, non più validi dal 2021 | `class/Plugin/Custom/Fattura/Valori/` |

## Obiettivo

Dare ai moduli del framework:

1. una nozione esplicita di ambiente;
2. una sincronizzazione tra locale e produzione che non rompe le chiavi esterne;
3. tabelle di configurazione modificabili solo in locale;
4. righe precaricate dichiarate dai moduli;
5. un modo per comandare indirizzo e orari della società da un modulo;
6. transazioni, letture con lock e lock nominali;
7. il pulsante "Guida" nelle pagine del backend;
8. le classi fiscali mancanti.

## Non-obiettivi

- Nessuna logica del gestionale nel core (sedi, listini, fatture restano nel modulo).
- Nessun cambiamento per le tabelle sincronizzate esistenti (CSS, SEO, società): non
  usano `keepIds()` né `localOnly()` e continuano a funzionare come oggi.
- Nessuna riscrittura della pagina "Dati aziendali" oltre all'estrazione del
  salvataggio e al blocco delle sezioni.
- Nessun supporto a transazioni distribuite su più database.

## Design

### A. Ambiente dell'applicazione

- Variabile `APP_ENV` con valori `local` o `production`; se manca o non è valida vale
  `production`, così nel dubbio le pagine restano in sola lettura.
- Classe `Wonder\App\Environment`: `current(): string`, `isLocal(): bool`,
  `isProduction(): bool`; valore letto una volta per richiesta, con `reset()` per i
  test.
- `forge start` (`LocalEnvironmentCommand`) scrive `APP_ENV=local` nel `.env` locale.
- **Fuori dal core:** `APP_ENV` negli `.env.example` dei boilerplate.

### B. Sync con `id` stabili

- **`SyncSchema::multiRow()->keepIds()`:**
  - l'export include `id` e `deleted`;
  - l'import inserisce o aggiorna per `id`, senza `TRUNCATE`;
  - le righe assenti dal file vengono segnate `deleted = 'true'`, mai eliminate;
  - le righe presenti nel file mantengono il proprio valore di `deleted`.
- **Tabelle senza `keepIds()`:** comportamento attuale invariato.
- **Export ordinato per `id`** per tutte le tabelle, così il file ha differenze
  stabili in git.
- **`SyncImportPlan`:** classe pura che, date le righe del file e gli `id` presenti nel
  database, calcola inserimenti, aggiornamenti e righe da segnare come cancellate;
  `TableSync` esegue il piano. Testabile senza database.
- **Opzioni componibili:** `keepIds()` e `localOnly()` restituiscono nuove istanze
  immutabili, come l'attuale `exclude()`, e si combinano tra loro e con `exclude()`.

### C. Tabelle modificabili solo in locale

- **`SyncSchema::...->localOnly()`** dichiara una tabella che si modifica solo in
  locale. Le tabelle del core non la usano.
- **Fuori dal locale**, per le Resource il cui Model dichiara `localOnly()`:
  - `ResourceRouteRegistrar` non registra le route che modificano: backend `create`,
    `store`, `update`, `delete`; API `store`, `update`, `destroy`;
  - le pagine `list`, `edit` e `view` restano registrate ma in sola lettura: campi
    disabilitati, nessun pulsante di salvataggio, aggiunta o eliminazione, avviso "Si
    modifica in locale e si pubblica con il deploy";
  - il controllo sta in un unico metodo della base, `Resource::isReadonly(): bool`,
    sovrascrivibile.
- **Export dopo il salvataggio:** la base `Resource` chiama `TableSync::autoExport()`
  dopo store, update e delete di ogni Model sincronizzato; `SYNC_AUTO_EXPORT` mantiene
  il significato attuale. Le Resource CSS che oggi lo chiamano a mano non lo
  chiameranno due volte.

### D. Righe precaricate dei moduli in `forge update`

- **Manifest:** `"database": { "models": "src/Models", "defaults": "<classe>" }`.
- **Contratto** `Wonder\App\Module\Contracts\ModuleDefaults`:
  `public static function seed(DefaultRows $rows): void`.
- **Helper** `Wonder\App\Support\DefaultRows`, con la regola unica per tutti i moduli:
  - `ensure(string $modelClass, string $keyColumn, array $rows): int` inserisce solo le
    righe il cui valore di `$keyColumn` non esiste, contando anche le righe
    cancellate; non modifica mai le righe esistenti;
  - `ensureSingleton(string $modelClass, array $values): int` crea la riga `id = 1` se
    manca;
  - le righe passano da `prepare()` del Model; entrambi restituiscono il numero di
    righe inserite; `total()` somma gli inserimenti della sessione.
- **Ordine dei moduli:** `ModuleDependencySorter`, classe pura che ordina i manifest
  abilitati per dipendenze (le dipendenze prima), con errore leggibile sui cicli.
- **Passi espliciti in `UpdateRunner`**, come metodi della classe e non come file
  `build/`:
  1. tabelle dai Model (come oggi);
  2. righe legacy di `build/row`, se presenti (come oggi);
  3. **import di `shared/sync-data.json`**, spostato da `app/build/update/css.php`, che
     da quel momento rigenera solo i CSS;
  4. file di `build/update`, se presenti (come oggi);
  5. **solo con `APP_ENV=local`:** `Defaults` dei moduli abilitati, in ordine di
     dipendenza;
  6. **se il passo 5 ha inserito righe:** scrittura di `shared/sync-data.json`, anche
     con `SYNC_AUTO_EXPORT` spento;
  7. file di `build/cli` con `--local` (come oggi).
- **Risultato:** `stats` di `UpdateRunner` riporta anche `sync_import`, `defaults` e
  `sync_export`.
- **In produzione** i passi 5 e 6 non partono mai.

### E. Indirizzo e orari della società comandati da un modulo

- **Servizio** `Wonder\App\Support\CorporateData`, unico punto di salvataggio:
  - `saveAddress(array $values): object` scrive `society_address` (riga `id = 1`);
  - `saveTimetable(array $rows): object` sincronizza le righe di `society_timetable` e
    scrive il JSON `society_address.timetable` nello stesso formato di oggi
    (`{giorno: [{from, to}]}`);
  - dopo il salvataggio chiama `TableSync::autoExport()`.
- **La pagina "Dati aziendali"** usa il servizio per indirizzo e orari, senza cambiare
  l'aspetto.
- **Blocco delle sezioni:**
  `CorporateData::lock(string|array $sections, string $notice, ?string $url = null, ?string $linkLabel = null): void`.
  - Sezioni: `company`, `legal`, `legal_address`, `address`, `social`, `timetable`.
  - Un modulo lo chiama da un file di boot (`boot.files` del manifest).
  - La pagina mostra le sezioni bloccate con campi disabilitati, avviso e link.
  - Il salvataggio ignora i valori inviati per le sezioni bloccate: il blocco vale
    lato server, non solo nell'interfaccia.
  - `CorporateData::lockedSections(): array` e `CorporateData::resetLocks()` per la
    pagina e per i test.
- **Telefono ed email** restano nei dati della società (`society`) e non si bloccano
  con l'indirizzo.

### F. Transazioni, letture con lock e lock nominali

- **`Wonder\Sql\Transaction::run(callable $callback, ?string $database = null): mixed`:**
  - apre la transazione sulla connessione di `Connection::Connect($database)`, esegue
    il callback, conferma e ne restituisce il valore;
  - su qualunque `Throwable` annulla e rilancia;
  - annidamento con un contatore di profondità e `SAVEPOINT wi_sp_<n>`: l'errore di un
    livello interno torna al proprio punto di ripristino e rilancia;
  - `Transaction::active(?string $database = null): bool`.
- **Funzione legacy:** `sqlTransaction(callable $callback, string $database = 'main')`.
- **Letture con lock:**
  - `Query::SelectForUpdate(...)`, con la stessa firma di `Select()`, aggiunge
    `FOR UPDATE`;
  - `sqlSelectForUpdate(...)`;
  - `Model::findForUpdate(...)` e `Model::findByIdForUpdate(int|string $id)`;
  - tutti lanciano `RuntimeException` se nessuna transazione è attiva su quella
    connessione.
- **Lock nominali:** `Wonder\App\Support\NamedLock::run(string $name, callable $callback, int $timeout = 0): mixed`,
  su `GET_LOCK` e `RELEASE_LOCK` come `UpdateLock`; se il lock non si ottiene
  restituisce `NamedLock::NOT_ACQUIRED` senza eseguire il callback; il rilascio avviene
  anche in caso di eccezione.
- **Connessione condivisa:** un test d'integrazione verifica che `sqlInsert()` e
  `Model::create()` dentro `Transaction::run()` vengano annullati insieme. Se non
  condividono la connessione, la risoluzione della connessione si allinea a
  `Connection::Connect()` come parte di questo lavoro.

### G. Pulsante "Guida" nelle pagine del backend

- **`PageSchema::docs(string $url, string|array|null $pages = null): self`**; con
  `null` vale per `list`, `create`, `edit` e `view`.
- **`ResourcePagePresenter`** aggiunge in fondo alle azioni dell'header il pulsante
  "Guida": icona `bi bi-question-circle`, classe `btn-outline-secondary`, apertura in
  una nuova scheda con `rel="noopener"`; etichetta nei `lang/` del core.
- **URL:** il core riceve l'indirizzo completo (ogni modulo lo compone dalla propria
  configurazione); sono ammessi solo indirizzi `http(s)` o relativi, gli altri vengono
  ignorati.

### H. Classi fiscali

In `Wonder\Plugin\Custom\Fattura\Valori`, nello stile delle classi esistenti
(costante `Valori`):

- **`AliquoteIva`:** `22.00` aliquota ordinaria, `10.00` aliquota ridotta, `5.00`
  aliquota ridotta, `4.00` aliquota minima.
- **`Natura::VALIDE`** (codici validi dal 2021) e **`Natura::valide(): array`** (codice
  → descrizione); la costante `Valori` resta invariata per compatibilità.
- **`EsigibilitaIva`:** `I` esigibilità immediata, `D` esigibilità differita, `S`
  scissione dei pagamenti.

## Compatibilità

- Tutte le modifiche sono aggiuntive: siti senza `APP_ENV` valgono `production`, ma
  nessuna tabella esistente dichiara `localOnly()`, quindi nulla diventa in sola
  lettura.
- L'import del sync resta nello stesso punto del flusso (dopo le righe legacy, prima
  dei file di update e dei CSS); cambia solo chi lo chiama.
- L'export ordinato per `id` produce una differenza nel file alla prima esportazione,
  senza cambiare i dati.
- Il manifest accetta la nuova chiave `database.defaults`; i moduli che non la
  dichiarano non cambiano.
- Rilascio come versione minore di `wonder-image/app`.

## Validazione

**Test senza database** (`tests/`, con `tests/harness.php`):

- `Environment`: default `production`, valori non validi, `local`.
- `SyncSchema`: combinazioni di `keepIds()`, `localOnly()` ed `exclude()`.
- `SyncImportPlan`: inserimenti, aggiornamenti, righe da segnare come cancellate,
  righe cancellate nel file, file vuoto.
- `ModuleDependencySorter`: ordine per dipendenze, moduli indipendenti, cicli.
- `PageSchema::docs()` e azione "Guida": pagine, URL non ammessi.
- `CorporateData`: sezioni bloccate e filtro dei valori inviati.
- Classi fiscali: formato dei codici, `Natura::VALIDE` contenuta in `Natura::Valori`.

**Verifica da un sito** (`boilerplates/new-site` con database locale):

- `php forge update --local` con un modulo di prova che dichiara `database.defaults`:
  righe inserite e `shared/sync-data.json` scritto; al secondo avvio nessun
  inserimento.
- Export e import con `keepIds()`: `id` invariati, righe mancanti segnate come
  cancellate, tabelle CSS invariate.
- Resource `localOnly()` con `APP_ENV=production`: route di modifica assenti, pagine in
  sola lettura con avviso; con `APP_ENV=local` modificabili.
- `Transaction::run()`: `sqlInsert()` e `Model::create()` annullati insieme; letture
  `ForUpdate` fuori transazione rifiutate; `NamedLock` non eseguito due volte in
  parallelo.
- Pagina "Dati aziendali" con indirizzo e orari bloccati: campi disabilitati, valori
  inviati ignorati, sezioni libere salvate normalmente.

Ogni file PHP toccato passa `php -l`; `composer dump-autoload` dopo le nuove classi.

## Documentazione

Aggiornamenti in `docs/app/`, nello stesso lavoro:

| Pagina | Contenuto |
|---|---|
| `piattaforma/multi-ambiente.md` | `APP_ENV`, `keepIds()`, `localOnly()`, import e defaults nei passi di `forge update` |
| `piattaforma/installazione-e-deploy.md` | passi di `UpdateRunner` e differenze tra locale e produzione |
| `concetti/moduli/manifest.md` e `contratto.md` | `database.defaults`, `ModuleDefaults`, `DefaultRows`, blocco dei dati aziendali dai file di boot |
| `concetti/risorse/database.md` | `Transaction`, letture `ForUpdate`, `NamedLock` |
| `concetti/risorse/resource.md` | `PageSchema::docs()`, `Resource::isReadonly()` |
| `servizi/fatturapa-valori.md` (nuova, aggiunta a `SUMMARY.md`) | classi di `Custom\Fattura\Valori`, con `AliquoteIva`, `Natura::valide()`, `EsigibilitaIva` |

## Fuori dal core

- `APP_ENV` negli `.env.example` di `new-site`, `immobili-site` e `rsvp-site`.
- Uso delle nuove API nel gestionale (sotto-progetto G1).
