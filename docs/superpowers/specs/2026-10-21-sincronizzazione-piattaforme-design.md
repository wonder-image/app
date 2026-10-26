# Sincronizzazione dei dati verso piattaforme esterne — design

Data: 2026-10-21 · Stato: bozza da approvare · Destinazione: issue su `wonder-image/app`

## Perché

Un sito Wonder spesso deve tenere allineate piattaforme esterne con i propri
dati: un cliente creato o modificato deve arrivare a Fatture in Cloud e a
Klaviyo; un prodotto deve arrivare a eBay, Amazon, TikTok Shop. Le piattaforme
cambiano da cliente a cliente (oggi Klaviyo, domani HubSpot).

Oggi l'unico aggancio è `Resource::afterStore/afterUpdate/afterDelete`
(`class/App/Resource.php`), chiamato solo da `ResourcePageController` e
`ResourceApiController`. Non vede:

- le scritture via `Model::create/update/delete` fatte dal codice (signup,
  area account, checkout, import, task): `customer-account` scrive i contatti
  così (`ContactAccount`, `AccountBilling`, `AccountAddresses`);
- le azioni di lista del backend che scrivono con `sqlModify()`
  (`app/http/api/backend/delete.php` è un soft-delete, `change-boolean.php`);
- listener aggiunti da sito o moduli: serve sottoclassare la Resource, e il
  `Contact` vive nel modulo `customer-account`.

In più è sincrono: una chiamata HTTP dentro il salvataggio lo rallenta e, se la
piattaforma è giù, la modifica si perde.

Obiettivo: integrare una nuova piattaforma deve voler dire scrivere solo il suo
connettore; collegarla a un sito deve voler dire poche righe di configurazione.

## Obiettivi

1. Infrastruttura di sincronizzazione **in uscita** nel core `wonder-image/app`.
2. Tre ruoli separati: **connettore** (conosce la piattaforma), **mappatura**
   (conosce il Model), **collegamento** (il sito sceglie cosa va dove).
3. Coda persistente con coalescenza, nuovi tentativi e stato visibile in backend.
4. Primo connettore reale: **Fatture in Cloud, anagrafica clienti**.
5. Riscrittura dell'integrazione Fatture in Cloud fuori da `Wonder\Plugin\*`.

## Non obiettivi (di questa issue)

- Eventi di comportamento (visualizzazione prodotto, ordine inviato, tracking).
- Sincronizzazione in entrata (modifiche fatte sulla piattaforma che tornano
  nel sito, webhook).
- Connettori Klaviyo, HubSpot, eBay, Amazon, TikTok Shop: issue successive, una
  per piattaforma. I marketplace sono un ordine di grandezza più complessi
  (categorie, attributi obbligatori, varianti, giacenze) e meritano un design
  proprio della forma `CatalogItem`.
- Documenti, fatture elettroniche, IVA, conti e metodi di pagamento di Fatture
  in Cloud: si riscrivono in una issue successiva.
- Intercettare ogni `sqlInsert/sqlModify` del codice legacy.
- Più aziende Fatture in Cloud per lo stesso sito.

## Anti-obiettivi (sarebbe un fallimento anche se funziona)

- Il salvataggio dell'utente rallenta o fallisce perché una piattaforma è lenta
  o giù.
- Un sito locale, di staging o di test scrive sull'anagrafica vera.
- Doppioni su Fatture in Cloud al primo collegamento di un sito che lo usava già.
- Il core dipende da un modulo (`customer-account`, ecommerce, gestionale).
- Token o dati personali nei log o nell'HTML (rispettare `LogRedactor`).
- Mappature da riscrivere quando si sostituisce una piattaforma con un'altra.

## Architettura

### 1. Forme neutre (core)

Oggetti valore immutabili, definiti dal core, che descrivono "cosa" si
sincronizza senza riferimento a un Model o a una piattaforma.

`Profile` (prima forma, l'unica di questa issue):

- `email`, `firstName`, `lastName`, `companyName`, `phone`
- `address` (via, CAP, città, provincia, codice paese ISO-2)
- blocco fiscale facoltativo: `vatNumber`, `taxCode`, `sdiCode`, `pec`,
  tipo (`person` / `company` / `pa`)
- `extra` (array chiave → valore scalare) per campi propri di un sito che un
  connettore può leggere per nome

`CatalogItem` è previsto ma **non** si progetta qui.

Ogni connettore dichiara quali forme accetta. Klaviyo ignorerà il blocco
fiscale, Fatture in Cloud lo userà.

### 2. Connettori (core, estendibili)

Contratto `Connector`:

- `key()`: es. `fatture-in-cloud`
- `accepts()`: le forme gestite (es. `Profile`)
- `upsert(Shape $shape, ?string $externalId): string` → id esterno
- `delete(string $externalId): void`
- facoltativo `MatchesExisting::match(Shape $shape): ?string` per collegare un
  record già presente sulla piattaforma invece di crearne uno nuovo
- `available()`: falso se mancano le credenziali (il connettore non parte e non
  dà errore, come `GooglePlacesTask` senza `GCP_API_KEY`)

Eccezioni tipizzate: errore temporaneo (si riprova), limite di chiamate con
`retryAfter` (si riprova senza contare il tentativo), errore definitivo (es.
dati rifiutati: la riga va in errore subito), non trovato in delete (si
considera riuscito).

Registro dei connettori: quelli del core, più quelli dei moduli tramite un
contratto `ModuleConnectors` sul modello di `ModuleTasks`, più quelli del sito
da `custom/config/sync.php`. Nessuno è attivo finché il sito non lo collega.

Il client HTTP è iniettabile: i test non chiamano mai la piattaforma vera
(stesso schema di `Support/GooglePlaces`).

### 3. Mappature (chi possiede il Model)

La mappatura traduce una riga in una forma. Vive con il Model:
`customer-account` dichiara una volta "un `Contact` è un `Profile`" e legge
nome, email, telefono, P.IVA e l'indirizzo di fatturazione predefinito da
`ContactAddress`. Il sito può sostituirla.

- La mappatura è neutra: riceve solo la riga, non il connettore. Può
  restituire `null` per un record che non ha senso sincronizzare da nessuna
  parte.
- **Quali record vanno a quale connettore** si decide nel collegamento del
  sito (§4), con un filtro sulla riga (es. solo i contatti con
  `is_customer = true` verso Fatture in Cloud). Scelta di Claude.
- Se un connettore riceve una forma senza i dati minimi che gli servono, la
  rifiuta come "saltata": la riga si chiude senza errore.
- **Dipendenze**: un Model può dichiarare che le sue modifiche toccano un
  record "padre". `ContactAddress` tocca il `Contact` del suo `contact_id`, così
  cambiare l'indirizzo di fatturazione riallinea il profilo.

Questa parte richiede una modifica in `customer-account` (mappatura `Contact`
→ `Profile` e dipendenza `ContactAddress` → `Contact`), da fare nella stessa
iniziativa ma nel repository del modulo.

### 4. Collegamento (sito)

`custom/config/sync.php` dice quali Model vanno a quali connettori, per
esempio (forma indicativa, l'API esatta è del builder):

```php
return [
    Contact::class => [
        'fatture-in-cloud' => fn (array $row): bool => $row['is_customer'] === 'true',
    ],
];
```

Il filtro è facoltativo; senza filtro vanno tutti i record.

Le credenziali restano nel `.env` / `Credentials` come oggi
(`FATTURE_IN_CLOUD_TOKEN`, `FATTURE_IN_CLOUD_COMPANY_ID`).

### 5. Quando si attiva

- `Model::create()`, `Model::update()`, `Model::delete()`: dopo una scrittura
  riuscita, se il Model è collegato ad almeno un connettore, mettono in coda il
  record. Il controllo "è collegato?" dev'essere economico: nessuna query se il
  sito non ha collegamenti.
- Endpoint di lista del backend che scrivono con `sqlModify()`
  (`delete.php`, `change-boolean.php` e ogni altro in `app/http/api/backend/`
  che modifica una riga di una tabella con Resource): risolvono il Model con
  `ResourceRegistry::resolveByTable()` e mettono in coda.
- Chiamata esplicita `Sync::touch(Model::class, $id)` per il codice legacy che
  scrive a mano.
- Gli hook `Resource::after*` restano invariati.

### 6. Coda

Tabella dedicata (nome del builder, es. `sync_queue`), una riga per
(Model, id, connettore, ambiente), con stato, azione, tentativi, prossimo
tentativo, blocco di lavorazione, ultimo errore e date.

- **Coalescenza**: una nuova modifica sullo stesso record aggiorna la riga
  esistente (torna "da fare", tentativi a zero) invece di aggiungerne un'altra.
- **Stato al momento dell'invio**: il lavoratore rilegge il record com'è
  **adesso** (inclusi i soft-deleted, ignorando la condizione predefinita
  `deleted = false`), non uno snapshot. Se non esiste più o è `deleted = true`
  l'azione è "elimina", altrimenti "crea/aggiorna".
- **Nessun doppio invio**: il lavoratore prende la riga con un aggiornamento
  condizionato (claim) prima di chiamare la piattaforma, così richiesta e
  scheduler non la mandano due volte.
- Le righe completate si rimuovono; lo storico è in `external_references`
  (`synced_at`, `sync_error`) e nei log dello scheduler.

### 7. Id esterni e impronta

`external_references` (Model del core, già usato dal gestionale con il
provider `fatture-in-cloud`) tiene l'id esterno per entità, provider e
ambiente. Si aggiunge una colonna con l'**impronta** dell'ultima forma inviata:
se la nuova forma ha la stessa impronta non parte nessuna chiamata (una
modifica a un campo non mappato non costa nulla).

L'helper `ExternalReferences` oggi vive nel gestionale
(`gestionale/src/Support/Providers/ExternalReferences.php`): va portato nel
core e il gestionale lo userà da lì (adeguamento del gestionale come lavoro
separato), per non duplicarlo.

### 8. Invio

- **Subito dopo la risposta**: alla fine della richiesta, chiusa la risposta
  all'utente (`fastcgi_finish_request()` dove disponibile), si tentano le righe
  messe in coda da quella richiesta, con un tempo massimo complessivo breve.
  Non in CLI. Disattivabile da configurazione. Se il tentativo fallisce o non
  è possibile, ci pensa lo scheduler.
- **Scheduler**: un task del core (`wonder.sync`) ogni minuto svuota la coda
  entro un tempo massimo, riusando `NamedLock`. Attivo solo se esiste almeno un
  collegamento.
- **Nuovi tentativi** con attese crescenti; dopo N tentativi la riga passa in
  errore e ci resta finché qualcuno non la ritenta.

### 9. Eliminazione e ripristino

- Eliminazione sul sito (anche soft-delete dalla lista) → eliminazione sulla
  piattaforma. Riuscita → si rimuove il riferimento in `external_references`.
- "Non trovato" sulla piattaforma in eliminazione → riuscita.
- Se la piattaforma **rifiuta** l'eliminazione perché il record non si può
  eliminare (es. Fatture in Cloud con documenti collegati; codici di risposta
  da verificare) → sul sito l'eliminazione resta valida, sulla piattaforma il
  record resta: si rimuove il riferimento, si registra un avviso nei log della
  sincronizzazione e la riga si chiude **senza errore**. Decisione dell'utente.
- Un errore temporaneo in eliminazione (timeout, piattaforma giù) segue invece
  i normali nuovi tentativi.
- Vale sia per i record creati dal sito sia per quelli collegati al primo
  aggancio.
- Ripristino di un record → crea/aggiorna di nuovo (passando dal collegamento
  con i record esistenti).

### 10. Ambienti

La sincronizzazione è **sempre spenta** finché il sito non la accende
esplicitamente (es. `SYNC_ENABLED=true`), **anche in produzione**. Decisione
dell'utente. Motivo: `Wonder\App\Environment` conosce solo `local` e
`production` e vale `production` quando `APP_ENV` manca o non è valido, quindi
uno staging con il `.env` copiato risulterebbe produzione.

Spenta significa: nessuna riga in coda, nessun invio, nessun errore. La colonna
`environment` di `external_references` resta `live` in produzione e `test`
altrimenti, come oggi.

### 11. Backend (sezione Dev)

- Elenco della coda con filtri per stato e connettore, errore leggibile,
  azione di riga "Riprova" (POST con conferma, CSRF).
- "Sincronizza tutto" per ogni collegamento (POST con conferma): mette in coda
  tutti i record non eliminati a blocchi. Stessa operazione da console
  (`php forge sync:all [Model] [connettore]`).
- Una voce `HomeStatus` (`sync`) con le righe in errore, protetta dai permessi
  della Resource a cui rimanda.
- Solo classi Bootstrap, nessun CSS proprio (regole esistenti del backend).

## Connettore Fatture in Cloud (primo caso)

- Spazio dei nomi nuovo: `Wonder\Integrations\FattureInCloud` (client API con
  token, `company_id`, mappatura degli errori dell'SDK verso le eccezioni
  tipizzate) e il connettore `fatture-in-cloud` per i **clienti**
  (anagrafica `entities/clients`). SDK: `fattureincloud/fattureincloud-php-sdk`
  già richiesto in `composer.json`.
- `Profile` → cliente FIC: nome = ragione sociale oppure nome e cognome, tipo,
  P.IVA, codice fiscale, codice SDI, PEC, email, telefono, indirizzo con paese.
- **Collegamento senza doppioni**: senza id esterno, prima di creare cerca un
  cliente esistente per P.IVA, poi codice fiscale, poi email. Un solo risultato
  → collega. Più risultati → errore leggibile, non sceglie a caso.
- **Aggiornamento parziale**: un campo vuoto sul sito non sovrascrive un campo
  pieno su Fatture in Cloud (es. SDI o PEC inseriti dal commercialista); un
  campo pieno sul sito vince sempre. Decisione dell'utente. In pratica il
  connettore legge il cliente FIC e invia solo i campi valorizzati della
  forma; l'impronta si calcola sulla forma, non sul cliente FIC.
- Eliminazione → `deleteClient`; se FIC la rifiuta vale la regola del §9 (il
  cliente resta su FIC, il riferimento si stacca, nessun errore).
- Le classi `Wonder\Plugin\FattureInCloud\*` restano per una release come
  `@deprecated` (nessun pacchetto in `Developer/packages` le usa, ma qualche
  sito potrebbe). Il resto (documenti, fatture elettroniche, IVA, pagamenti) si
  riscrive in una issue successiva.

## Test

Unitari, senza rete, con un connettore finto: coalescenza, rilettura dello
stato, impronta invariata = nessuna chiamata, eliminazione/ripristino,
dipendenza padre, tentativi e passaggio in errore, claim senza doppio invio,
ambiente non di produzione spento. Per Fatture in Cloud: traduzione
`Profile` → cliente e ordine del collegamento (P.IVA, CF, email) con un client
finto. Ricordare che `tests/` è in `.gitignore`: i nuovi file vanno aggiunti
con `git add -f`.

## Documentazione obbligatoria nello stesso lavoro

Cambio architetturale: aggiornare `docs/app/*` (nuova pagina sulla
sincronizzazione, rimando da `concetti/risorse` e `piattaforma/cron-job.md`),
`AGENTS.md`, la skill `wi-app` e il `CHANGELOG.md` (sezione Unreleased).

## Lasciato al builder

Nomi esatti di classi, tabella e chiavi di configurazione; forma dell'API di
collegamento; valori di backoff, numero massimo di tentativi, dimensione dei
blocchi e tempi massimi; come esporre il registro dei connettori; struttura
della pagina Dev.

## Decisioni dell'utente

- Solo sincronizzazione di dati in uscita; tutto nel core, niente modulo per
  piattaforma.
- Primo connettore: Fatture in Cloud, clienti; riscrittura fuori da
  `Wonder\Plugin\*`.
- Eliminazione sul sito → eliminazione sulla piattaforma se possibile;
  altrimenti il record resta sulla piattaforma senza errore.
- Sincronizzazione sempre spenta finché il sito non la accende, anche in
  produzione.
- Aggiornamento parziale: i campi vuoti non sovrascrivono quelli pieni.

## Scelte fatte da Claude (da confermare)

- Spazio dei nomi `Wonder\Integrations\*` per le integrazioni del core.
- Filtro "quali record a quale connettore" nel collegamento del sito, non
  nella mappatura.
- Ripristino = nuova creazione/aggiornamento.
- Più corrispondenze nel collegamento = errore, non scelta automatica.
- Portare `ExternalReferences` dal gestionale al core.
- Nessuna sincronizzazione delle scritture legacy oltre agli endpoint di lista
  e a `Sync::touch()`.
