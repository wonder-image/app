# Prerequisiti del core per i moduli gestionale ed ecommerce

- **Data:** 2026-09-16
- **Repo:** `wonder-image/app` (framework)
- **Stato:** design approvato (parte E rivista il 2026-09-17), in attesa di piano di implementazione
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
| Dati aziendali | una sola sede: quattro tabelle a riga unica (`society`, `society_address`, `society_legal_address`, `society_social`) salvate insieme dalla pagina legacy; orari sia in `society_timetable` sia nel JSON `society_address.timetable`; `infoSociety()` le unisce in un oggetto con campi calcolati, caricato a ogni richiesta in `$SOCIETY` (email, head, documenti legali, footer dei boilerplate) | `app/http/backend/config/corporate-data.php`, `app/function/info.php`, `app/service/lang.php` |
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
5. più sedi della società in "Dati aziendali", con orari e chiusure sul modello di
   Google;
6. transazioni, letture con lock e lock nominali;
7. il pulsante "Guida" nelle pagine del backend;
8. le classi fiscali mancanti.

## Non-obiettivi

- Nessuna logica del gestionale nel core (sedi, listini, fatture restano nel modulo).
- Nessun cambiamento per le tabelle sincronizzate CSS e SEO: non usano `keepIds()` né
  `localOnly()` e continuano a funzionare come oggi.
- Nessuna integrazione con le API di Google in questo lavoro: si salva il Place ID;
  cron, autocomplete ed embed sono funzionalità future (E7).
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

### E. Sedi della società in "Dati aziendali" (rivista il 2026-09-17)

"Dati aziendali" diventa la pagina delle sedi della società: i clienti chiedono spesso
di aggiungere indirizzi. Ogni sede ha i propri dati, una è predefinita e le altre
prendono dalla predefinita ciò che manca. Orari e chiusure ricalcano il modello di
Google, perché in futuro un cron li verificherà sulla scheda Google Business tramite
il Place ID.

#### E1. Tabelle

| Tabella | Gruppo | Colonne |
|---|---|---|
| `society_locations` | Sede | slug (unico), label (es. "Negozio di Milano"), is_default (una sola), visible, position, business_status (`operational`, `closed_temporarily`, `closed_permanently`, `future_opening`), opening_date |
| | Indirizzo | `AddressExtension::simple(linkKey: 'gmaps')`, google_place_id (uno per sede), google_synced_at |
| | Contatti | email, pec, tel, cel |
| | Dati aziendali e legali | name, legal_name, pi, cf, sdi, rea, share_capital |
| | Sede legale | `AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')` |
| | Link | site, instagram, facebook, tiktok, linkedin, whatsapp, youtube |
| `society_location_hours` | Orari regolari e secondari | society_location_id, hours_type, open_day, open_time, close_day, close_time, position |
| `society_location_special_hours` | Orari speciali e chiusure | society_location_id, start_date, end_date, closed, open_time, close_time, note, source (`manual`, `google`) |

- **Loghi:** restano unici per la società (`logos`), invariati.
- **Orari regolari e secondari** (come `regularHours.periods` della Business Profile
  API e `regularOpeningHours` della Places API):
  - `hours_type`: `regular`, oppure un tipo secondario di Google in minuscolo
    (`drive_through`, `happy_hour`, `delivery`, `takeout`, `kitchen`, `breakfast`,
    `lunch`, `dinner`, `brunch`, `pickup`, `access`, `senior_hours`,
    `online_service_hours`);
  - più fasce nello stesso giorno sono più righe (es. 9–13 e 15–19);
  - `close_day` può essere il giorno dopo; `24:00` indica la mezzanotte a fine
    giornata;
  - chiusura vuota = sempre aperto, come nella Places API;
  - giorni `Mon`…`Sun`, già usati dal core e da `prettyTimeTable()`; la conversione
    verso Google (0 = domenica nella Places API, `MONDAY`… nella Business Profile API)
    sta nella futura classe di sincronizzazione.
- **Orari speciali e chiusure** (come `specialHours.specialHourPeriods`):
  - `closed = true`: chiusura; per comodità vale anche su un intervallo di date (es.
    ferie dal 10 al 25 agosto), che la sincronizzazione con Google divide in giorni;
  - `closed = false` con orari: apertura straordinaria, con `end_date` vuota o al
    massimo il giorno dopo `start_date`, come in Google.
- **Google Place ID:** compilato a mano, con un link al Place ID Finder di Google. Se
  `gmaps` è vuoto, il link a Google Maps si costruisce dal Place ID senza chiave API:
  `https://www.google.com/maps/search/?api=1&query=<indirizzo>&query_place_id=<ID>`.

#### E2. Sincronizzazione e modifica

- **`society_locations`:** `SyncSchema::multiRow()->keepIds()`, perché altri moduli
  puntano agli `id` delle sedi. Nel core non è `localOnly()`: si modifica come oggi i
  dati aziendali. Un modulo può renderla modificabile solo in locale sostituendo la
  Resource con la propria (priorità dei moduli nel `ResourceRegistry`).
- **`society_location_hours` e `society_location_special_hours`:** non sincronizzate,
  sono dati di produzione. Si modificano nella pagina "Orari e chiusure" di ogni sede,
  da `admin` e `administrator`, sempre anche in produzione: le chiusure cambiano spesso
  e il futuro cron da Google scriverà qui.

#### E3. Dati presi dalla sede predefinita

- **Campo per campo:** contatti e link (es. telefono proprio, email della
  predefinita).
- **Gruppo intero:** dati aziendali e legali, sede legale, indirizzo, orari. Se il
  gruppo della sede è tutto vuoto si usa quello della predefinita, così non si
  mescolano dati di sedi diverse.
- **Orari speciali:** seguono gli orari regolari; una sede senza orari propri eredita
  dalla predefinita anche le chiusure.
- **Calcolo:** classe pura `SocietyLocationResolver`, testabile senza database.
- **Nel form:** i campi vuoti mostrano come suggerimento il valore ereditato.

#### E4. Lettura

- **`Wonder\App\Support\SocietyLocations`**, con dati già completati dall'eredità e
  calcolati una volta per richiesta:
  - `default(): object`, `find(int|string $idOrSlug): ?object`,
    `all(bool $onlyVisible = true): array`;
  - `hoursFor(object $location, DateTimeInterface $date): array`: orari effettivi di
    una data, con gli orari speciali che prevalgono su quelli regolari;
  - `isOpen(object $location, ?DateTimeInterface $at = null): bool`;
  - fuso orario del sito; calcolo degli orari in una classe pura `OpeningHours`.
- **`infoSociety(int|string|null $location = null)`:**
  - senza argomento: sede predefinita, come oggi;
  - con id o slug: quella sede; se non esiste, la predefinita;
  - stessi campi di oggi (`name`, `email`, `tel`, `cel`, `prettyAddress`,
    `prettyLegal`, `social`, `domain`, loghi…); `timetable`, `timeGroup` e
    `prettyTime` ricavati dagli orari regolari nel formato di oggi;
  - in più: `location` (id, slug, label, is_default), `google_place_id`, `hours`,
    `specialHours` (da oggi in avanti), `businessStatus`.
- **`infoSocietyLocations(): array`:** tutte le sedi visibili, già completate.
- **`$SOCIETY`** resta la sede predefinita.

#### E5. Pagine del backend

- **"Dati aziendali":** Resource con l'elenco delle sedi, stessa voce di menu e stesso
  percorso (`app/config/corporate-data`). Elenco con nome, città, badge "Predefinita"
  e visibilità; aggiunta, modifica ed eliminazione.
- **Scheda della sede:** riquadri Sede, Indirizzo con Place ID, Contatti, Dati
  aziendali e legali, Sede legale, Link; collegamento a "Orari e chiusure".
- **"Orari e chiusure"** di una sede: orari regolari, orari secondari, orari speciali e
  chiusure; accessibile ad `admin` e `administrator`.
- **Sede predefinita:** sempre una sola; impostarne una toglie il flag alle altre; non
  si può eliminare; la prima sede creata è predefinita.

#### E6. Siti esistenti

- **Migrazione:** passo di `UpdateRunner` eseguito una sola volta, dopo le tabelle e
  prima dell'import del sync: se `society_locations` è vuota, crea la sede predefinita
  con `id = 1` ("Sede principale", slug `sede-principale`) da `society`,
  `society_address`, `society_legal_address` e `society_social`, e i suoi orari
  regolari da `society_timetable` (o dal JSON `society_address.timetable`).
- **Vecchie tabelle:** restano per una versione, senza essere più scritte né
  sincronizzate (i loro Model perdono `syncSchema()`).
- **Vecchia pagina:** handler e vista legacy di `corporate-data` sostituiti dalla
  Resource.

#### E7. Funzionalità future

- Cron che verifica i cambi di orario sulla scheda Google tramite il Place ID. Con la
  Places API gli orari speciali sono visibili solo per i prossimi 7 giorni
  (`currentOpeningHours`); l'elenco completo richiede la Business Profile API con
  l'accesso del proprietario. Aggiornare o solo avvisare si decide nella sua spec.
- Place ID compilato dall'autocomplete dell'indirizzo (anche in `wonder-image/lib`).
- Embed della mappa generato in automatico.

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
- Dati aziendali: la migrazione crea la sede predefinita dai dati esistenti;
  `infoSociety()` senza argomenti e `$SOCIETY` restituiscono gli stessi campi di oggi,
  quindi footer, email e documenti legali dei siti non cambiano.
- Rilascio come versione minore di `wonder-image/app`.

## Validazione

**Test senza database** (`tests/`, con `tests/harness.php`):

- `Environment`: default `production`, valori non validi, `local`.
- `SyncSchema`: combinazioni di `keepIds()`, `localOnly()` ed `exclude()`.
- `SyncImportPlan`: inserimenti, aggiornamenti, righe da segnare come cancellate,
  righe cancellate nel file, file vuoto.
- `ModuleDependencySorter`: ordine per dipendenze, moduli indipendenti, cicli.
- `PageSchema::docs()` e azione "Guida": pagine, URL non ammessi.
- `SocietyLocationResolver`: eredità campo per campo (contatti, link) e per gruppo
  (dati legali, sede legale, indirizzo, orari con le chiusure).
- `OpeningHours`: fasce multiple, chiusura il giorno dopo, `24:00`, sempre aperto,
  chiusure su intervallo, aperture straordinarie, orari secondari.
- Link a Google Maps dal Place ID.
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
- Migrazione dei dati aziendali su un database con le vecchie tabelle compilate: sede
  predefinita con `id = 1`, orari convertiti, `infoSociety()` con gli stessi campi di
  prima; al secondo avvio nessuna modifica.
- "Dati aziendali" con più sedi: una sola predefinita, predefinita non eliminabile,
  `infoSociety('<slug>')` con eredità; "Orari e chiusure" modificabile da
  `administrator`.

Ogni file PHP toccato passa `php -l`; `composer dump-autoload` dopo le nuove classi.

## Documentazione

Aggiornamenti in `docs/app/`, nello stesso lavoro:

| Pagina | Contenuto |
|---|---|
| `piattaforma/multi-ambiente.md` | `APP_ENV`, `keepIds()`, `localOnly()`, import e defaults nei passi di `forge update` |
| `piattaforma/installazione-e-deploy.md` | passi di `UpdateRunner` e differenze tra locale e produzione |
| `concetti/moduli/manifest.md` e `contratto.md` | `database.defaults`, `ModuleDefaults`, `DefaultRows` |
| `concetti/dati-aziendali.md` (nuova, aggiunta a `SUMMARY.md`) | sedi, eredità dalla predefinita, orari e chiusure, `infoSociety()`, `SocietyLocations`, Place ID, migrazione |
| `concetti/risorse/database.md` | `Transaction`, letture `ForUpdate`, `NamedLock` |
| `concetti/risorse/resource.md` | `PageSchema::docs()`, `Resource::isReadonly()` |
| `servizi/fatturapa-valori.md` (nuova, aggiunta a `SUMMARY.md`) | classi di `Custom\Fattura\Valori`, con `AliquoteIva`, `Natura::valide()`, `EsigibilitaIva` |

## Fuori dal core

- `APP_ENV` negli `.env.example` di `new-site`, `immobili-site` e `rsvp-site`.
- Uso delle nuove API nel gestionale (sotto-progetto G1).
