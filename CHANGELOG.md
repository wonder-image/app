# Changelog

## Unreleased

### Aggiunto
- **Provision: FORCE_WWW e APP_TRUST_PROXY nelle GitHub Variables.**
  `php forge provision` ora chiede se forzare www e che proxy o CDN c'è
  davanti al sito, e salva `FORCE_WWW` e `APP_TRUST_PROXY` (`true`/`false`)
  nelle variabili della repo insieme ad `APP_DOMAIN` e `ASSETS_VERSION`.
  Default: `FORCE_WWW` è `true` per un dominio principale e `false` per un
  sottodominio (`shop.esempio.it`: il certificato non copre
  `www.shop.esempio.it`); `APP_TRUST_PROXY` è `false` e si attiva solo
  indicando un proxy (`cloudflare`, `altro`). Una variabile già impostata
  sulla repo diventa il default della domanda; senza terminale interattivo
  si usano i default senza chiedere. Il messaggio di sync ora elenca le
  variabili davvero scritte. Coperto da
  `tests/Console/ProvisionWwwProxyTest.php`.
- **Cloudflare: `php forge cloudflare:security` e `APP_PROXY`.** Nuovo
  comando che allinea la zona Cloudflare del sito: SSL Full (strict) solo
  dopo aver verificato il certificato dell'origine, Always Use HTTPS,
  Automatic HTTPS Rewrites, TLS minimo 1.2, TLS 1.3, Security Level
  medium, Browser Integrity Check, Page Shield, regola WAF «wonder-image»
  (skip di Security Level e BIC su `/api/` tranne `/api/frontend/`) e un
  rate limit su `/api/`. Bot Fight Mode è spento di default e in CI viene
  solo segnalato. Avvisa per HSTS, DNSSEC e record @/www non proxati.
  Tocca solo le proprie regole (per `ref`), `--dry-run` valida senza
  applicare, i default si cambiano in `custom/config/cloudflare.php`.
  Provision ora chiede il proxy come `nessuno` / `cloudflare` / `altro`
  (default `cloudflare` con nameserver Cloudflare), salva `APP_PROXY` nelle
  GitHub Variables e con Cloudflare salva token, `CLOUDFLARE_ZONE_ID` e
  `CLOUDFLARE_ACCOUNT_ID` in Bitwarden e lancia il comando. Prima di
  salvare il token controlla i workflow in `.github/workflows/`: se uno
  scrive il `.env` senza escludere `CLOUDFLARE_*` (un `deploy.yml` vecchio
  lo porterebbe in produzione) avvisa e non lo salva. Vedi
  `docs/app/piattaforma/cloudflare.md`. Coperto da
  `tests/Console/CloudflareSecurityTest.php`.

### Corretto
- **CheckGroup (tema Wonder): stato d'errore sul contenitore.** Con `error()`
  il renderer Wonder di `CheckGroup` (e quindi di `CheckTree`) non metteva
  `input-error` sul contenitore né mostrava il messaggio, quindi la regola
  della lib (2.1.2-alpha.32) `.wi-input-container.checkbox.input-error
  .wi-checkbox:not(:checked)` non si applicava. Ora il contenitore è
  `wi-input-container checkbox compiled … input-error` e sotto la lista (o le
  pillole) esce lo `<span class='alert-error'>` degli altri input. Senza
  errore il markup è identico a prima. Le pillole (`pills()`) si bordano di
  rosso dalla lib successiva alla 2.1.2-alpha.32 (wonder-image/lib#16); fino
  ad allora c'è solo il messaggio. Coperto da
  `tests/Themes/WonderCheckGroupTest.php`.
- **Popup: pagine obbligatorie anche via API e QuickCreate.** Il controllo
  `popup.pages_required` di `PopupResource::mutateRequestValues()` (2.4.0-beta.6)
  valeva solo per il context `backend`. Ora vale in ogni context: creazione
  senza pagine e modifica che manda `pages` vuoto rispondono 422 con lo stesso
  messaggio (`InvalidArgumentException`, codice 422; l'API lo restituisce in
  `response`, QuickCreate lo mostra nel modal). Perché il 422 sia risolvibile,
  `apiSchema()` dichiara i campi scrivibili di `store` e `update`: `name`,
  `title`, `bg_color`, `tx_color`, `url`, `url_label`, `pages`, `view`,
  `visible` (`images` no: l'upload resta nel form backend). Prima lo store API
  scartava tutto il payload e salvava una riga senza nome né pagine. Chi ha
  l'authority dell'API popup (`avvisi`) ora può scrivere questi campi.
  `pages` come stringa non JSON (`pages=home` form-urlencoded, `"0"`) diventa
  `[valore]` prima del salvataggio: prima passava il controllo ma il formato
  `json` di prepare la salvava come `[]`. `visible` accetta solo `'true'` e
  `'false'` (come il select del form e il toggle della lista; i booleani JSON
  diventano le stringhe), altrimenti 422 con `popup.visible_invalid`. Coperto
  da `tests/App/Resources/PopupResourceTest.php`.

## 2.4.0-beta.6 — 2026-10-26

### Breaking
- **Schemi `resource:*` di `Wonder\App\Table` calcolati al primo accesso.**
  `app/config/app/table.php` non chiama più `prepareSchema()` per ogni Resource
  a ogni richiesta (costruiva l'intero `formSchema()` con le opzioni dal DB:
  186 query per pagina, anche in frontend). Registra un resolver con il nuovo
  `Table::key(...)->setSchemaResolver(callable)`; `schema()`, `columns()`,
  `hasColumn()`, `prepare()` e `prepareFor()` lo risolvono e lo memorizzano.
  Gli schemi delle tabelle legacy e dei Model restano calcolati subito. Chi
  legge `Table::$list['resource:…']` direttamente trova la chiave vuota (`[]`)
  finché qualcuno non la risolve: va migrato a `Table::key(...)->schema()`.
  `array_key_exists(..., Table::$list)` vede ancora la chiave; il nuovo
  `Table::has()` fa lo stesso controllo, solo su `$list`, senza calcolare lo
  schema: dopo un'assegnazione intera di `$list` le chiavi lazy sparite non
  risultano più registrate. Nel core sono
  migrati backend `index.php`/`list.php`, `backendPageTableSchema()`, `ListProvider`,
  `ListTableRequest`, `TableAuthorization`, `file-delete`, `file-move`,
  `delete-icon`, `Repeater`, `ResourcePageController` e
  `ResourceApiController`; `file-delete`, `file-move` e `delete-icon`
  controllano i permessi prima di calcolare lo schema. Lo schema ora si calcola
  nel contesto della richiesta, al primo accesso (dopo auth, lingua, boot dei
  moduli e `custom/config/config.php`), non più dentro `app/config/config.php`
  prima di tutto questo. Un'eccezione in `formSchema()` era e resta inghiottita
  da `safeFormFieldsByKey()`; quella che si sposta dal boot al primo accesso è
  un'eccezione in `modelClass()::dataFields()`/`prepareFormatFromField()`. Un
  resolver che rilegge la propria chiave (anche A → B → A) lancia una
  `LogicException` invece di memorizzare uno schema incompleto.
- **Backend: rimossi l'helper PHP `modal()` e il markup `#modal`.** Il file
  `app/function/backend/modal.php` non c'è più e `header.php` del backend non
  stampa più la modale Bootstrap `#modal`. I siti devono avere
  **wonder-image (lib) >= 2.1.2-alpha.31**: le versioni precedenti (alpha.30 e
  prima) eliminano le righe ripetibili leggendo `#modal button.send`, che ora
  manca (TypeError in `inputRow.js`, la riga non si toglie); file e `modal()` JS
  del backend usano già `wi.confirm` dalla alpha.23. Il vincolo
  `extra.wonder.lib` (e `package.json`) è `^2.1.2-alpha.31`: `php forge update`
  si ferma se la lib installata è più vecchia; aggiornarla con
  `npm install 'wonder-image@^2.1.2-alpha.31'` (il tag `latest` su npm non è
  l'ultima alpha) e rilanciare. Le voci Elimina della tabella (`Field`) e i
  bottoni legacy `delete()` / `removeAuthorization()` chiedono conferma con
  `data-wi-confirm*` (Annulla resta «Chiudi») e poi chiamano `ajaxRequest`
  sullo stesso URL, invece di `onclick="modal(...)"`. Un sito che stampava a
  mano `<?=modal()?>` o chiamava la funzione PHP `modal()` va migrato su `Button::post()->confirm()` / `data-wi-confirm` o `wi.confirm()`:
  nei pacchetti e nei siti verificati non ce n'è nessuno. La `modal()` JS della
  lib resta come alias `@deprecated` di `wi.confirm`.
- **Rimossi gli helper `wiCard()`, `wiCardLink()`, `wiCardStats()`,
  `wiCardAccordion()`, `wiAccordion()` e i componenti view
  `frontend.overlay.modal` e `frontend.ui.button`.** Nei repo locali non hanno
  chiamanti. Un sito che li usa ancora va migrato: `Card` (in un layout
  Resource con `ResourceFormLayoutRenderer::renderLayout()`), `MetricCard`,
  `Accordion`, `Modal::make()->frontend()` e `Button::make()`. Un override di
  `frontend.overlay.popup` va riallineato al nuovo markup (vedi «Cambiato»)
  e deve ripetere il controllo su `$GLOBALS['WI_POPUP_RENDERED']`, altrimenti
  il popup esce due volte; in alternativa si toglie la riga del popup dal
  `body-start.php` del sito.
- Markup del popup cambiato (`<h2>`, footer nel dialogo, `btn-icon-right`,
  `wi-show`): il CSS di un sito che lo stila va controllato. L'etichetta del
  bottone ora è escapata: un `url_label` con HTML appare come testo.
- Il flag `WI_POPUP_RENDERED` non si azzera: uno script CLI che rende più
  pagine nello stesso processo deve fare `unset($GLOBALS['WI_POPUP_RENDERED'])`.
- Il popup frontend e le card server-side richiedono `wonder-image` lib
  `2.1.2-alpha.24` o successiva; il vincolo del core è `^2.1.2-alpha.31`.
- **`$_SESSION['system_cache']['country']` non si riempie più all'avvio della
  sessione (`D-I7`).** `config/app/session.php` non chiama `IPInfo` (fino a tre
  provider HTTP a ogni sessione nuova, bot inclusi): il paese lo calcola
  `Wonder\Plugin\GeoPlugin\Geo::country()` alla prima chiamata e lo salva in
  quella chiave. Prima della prima chiamata la chiave non c'è: una lettura diretta
  dà `null` (con `?? null`) o un warning di chiave mancante. Migrazione:
  sostituire la lettura con `Geo::country()`, che restituisce sempre un codice
  (`IT` se il paese non si trova). Nei siti e nei pacchetti locali non ci sono
  lettori diretti; nel core li usavano `LanguageRedirector` e `Address`, già
  migrati. `redirectByCountry($_SESSION['system_cache']['country'] ?? null)`
  continua a funzionare: con `null` il paese si calcola dentro.
- **`Wonder\Api\Call` ha un timeout di default di 10 secondi (`D-I10`).** Prima
  non ne aveva (attesa illimitata di cURL). Una chiamata che può durare di più
  deve impostarlo con `->timeout($secondi)` (è il totale, connessione inclusa).
  Alla scadenza `call()` restituisce `success => false` con `errno` 28;
  `result()` restituisce la stringa d'errore di cURL (non `null`), quindi chi la
  passa a `json_decode()` ottiene `null`; `curlJson()` e `wiApiJson()`
  restituiscono `null`. Vale anche per `curl()` e `wiApi()`. PayPal (token,
  ordine, capture, info ordine) e Nexi (`CallPost`, `CallGet`) impostano 30 s,
  così una capture lenta non si interrompe dopo che il pagamento è avvenuto; la
  creazione rapida del backend chiama lo store con 60 s (`curlJson()` accetta
  ora un `$timeout` opzionale in coda). `IPInfo` (massimo 3 s) e reCAPTCHA
  (massimo 5 s) hanno già i loro timeout più corti.

### Aggiunto
- **Contatore delle query per richiesta (`QueryCounter`, D-I11).** Solo con
  Debug attivo: a fine richiesta il log riceve una riga `[SQL] {uri} queries=N
  top=...` con le forme di query più ripetute (`QUERY_COUNTER_TOP`, default 10).
  Fuori Debug `note()` esce subito. Conta solo le query che passano da
  `Wonder\Sql\Query`.
- **Memo per richiesta (`RequestMemo`, D-I2).** `sqlTableExists()` memoizza
  solo le tabelle trovate; `infoUser()` per id memoizza la riga letta (nuova
  `forgetInfoUser()`), mentre l'oggetto restituito si ricostruisce a ogni
  chiamata; `head.php` legge `css_font` e `analytics` una volta;
  `Asset::version()` fa uno stat per percorso. Insert, Update, Delete e
  Truncate di `Query` svuotano lo scope della tabella, `Transaction` svuota
  tutto su rollback, `CreateTable` dopo il DDL. Vedi `docs/performance.md`.
- **`Geo::country()`** (`Wonder\Plugin\GeoPlugin\Geo`): paese del visitatore
  calcolato al primo uso e salvato in `$_SESSION['system_cache']['country']`;
  senza sessione resta in memoria per la richiesta.
- **`Alert::inline()`.** Nel tema Bootstrap l'Alert esce come avviso di pagina
  visibile, `<div class="alert alert-{tipo}" role="alert">`, invece del `.toast`
  nascosto. Il tipo segue la mappa dei livelli (`error` → `danger`). Il titolo
  (`<strong class="alert-heading d-block">`) c'è solo con `title()`, la chiusura
  (`alert-dismissible`, `btn-close` con `data-bs-dismiss="alert"`) solo con
  `dismissible()` esplicito, l'id solo con `id()`. Classi e attributi aggiuntivi
  restano sul tag; il messaggio è escapato con gli a capo come nel toast. Nel tema
  Wonder `inline()` è ignorato: `wi-alert` è sempre fisso a lato dello schermo.
  Senza `inline()` l'output è identico a prima (bloccato da
  `tests/Themes/AlertInlineTest.php`). Gli avvisi fissi delle viste backend
  (sola lettura di resource e scheduler, errori e `formNotices()` del layout
  form, errori del file di configurazione, errore della lista) passano a
  `Alert::make(...)->inline()->addClass('mb-0')`: in più hanno `role="alert"`,
  e gli a capo escono come `<br />`. Restano a mano il catalogo componenti
  (link e `<code>`) e `QuickCreateModal` (HTML in JS).

### Cambiato
- **`IPInfo` non interroga i provider per IP privati, locali o riservati**
  (`IPInfo::isPublicIp()`): restano i dati di default (`IT`). Il risultato era
  già quello, ma costava fino a tre chiamate HTTP. Gli IPv4 mappati
  (`::ffff:203.0.113.9`, server dual-stack) si trattano come IPv4. Un provider
  che non restituisce un codice paese di due lettere (anche `""`) non vale:
  si prova il successivo, poi `IT`.
- **`LanguageRedirector::redirectByCountry()` non calcola il paese per i bot**,
  che vanno comunque sulla lingua default.
- **`<wi-card>` sostituito da `Card` in `class/` e `app/` (`B-CORE-I4`).** Le
  pagine backend (account, utenti, log email, errori SQL, media, Stripe
  Connect, form e lista Resource), i layout `backend.form` e `backend.show`,
  `Table` con `card` attivo e `ResourcePagePresenter::listFallback()` rendono
  la card dal server con `ResourceFormLayoutRenderer::renderLayout()`: stesso
  markup che `createCard()` della lib costruiva nel browser
  (`div.col-12 > div.card.border > div.card-body.row.g-3`). Le pagine non
  dipendono più dal JS per mostrare le card.
- **Popup frontend su `Modal::make()->frontend()` (`B-CORE-I5`).** Il
  componente `frontend.overlay.popup` esce già aperto con `wi-show`, senza lo
  `<script>` che chiamava `modal()` al `load`. Titolo in `<h2>` invece che in
  `<div>`, footer dentro il dialogo, bottone con `btn-icon-right` invece di
  `btn-icon`, dialogo largo al massimo 760px come le altre modal Wonder. Un popup con un
  `code` non valido come selettore non si stampa (prima `modal()` falliva nel
  browser); i codici generati `pop_xxxxxxx` lo sono sempre. Il popup si
  stampa al massimo una volta per richiesta: il `body-start.php` dello scaffold
  lo include già e il core lo rimette in `body-end`, e con due modal dallo
  stesso id quella in primo piano non si chiudeva.
- **Renderer Alert su `ReadsAlertSchema` (`B-CORE-I8`).** I renderer
  Bootstrap e Wonder leggono lo schema dallo stesso Concern; output identico,
  coperto da snapshot in `tests/Themes/AlertSnapshotTest.php`.
- **Scheduler in sola lettura non va più in Fatal (`B-CORE-I8`).** La pagina
  `backend/scheduler/schedules.php` passava una stringa a
  `Card->render('bootstrap')`; ora usa `ResourceFormLayoutRenderer::renderLayout()`.

### Rimosso
- **Componenti `frontend.overlay.modal` e `frontend.ui.button`
  (`B-CORE-I5`).** L'unico chiamante era il popup; nessun override nei siti
  locali. Al loro posto `Modal::make()->frontend()` e `Button::make()`.
- **Helper `wiCard()`, `wiCardLink()`, `wiCardStats()`, `wiCardAccordion()` e
  `wiAccordion()` (`B-CORE-I3`).** Nessun chiamante in app, nei moduli e nei
  siti locali. Al loro posto: `Card` (dentro un layout Resource, via
  `ResourceFormLayoutRenderer::renderLayout()`), `MetricCard` e `Accordion`.
  `wiPreIfArray()` resta in `app/function/backend/components/accordion.php`.
  Le pagine backend `log/auth-users/show`, `log/consent/show` e
  `config/legal-documents/show` usano `Card` per il riquadro dei JSON.

### Corretto
- **Elimina dalle liste del backend non risponde più sempre 422.** Le azioni
  della tabella (`Field::ajaxRequest()`, `move`) inviano `database=` con il nome
  reale del database (`Table::$database`, da `SELECT DATABASE()`), mentre
  `ApiRequest::selectDatabase()` accettava solo gli alias di `$DB->database`
  (`main`, …): `api/backend/delete`, `change/boolean`, `move` e `authority`
  uscivano con «Parametri non validi.». Ora il nome reale di un database
  configurato si risolve nel suo alias; nomi non configurati e
  `information_schema` restano 422 (anche se un alias mappa lo schema di
  sistema). Con MySQL `lower_case_table_names=1` il nome restituito da
  `SELECT DATABASE()` può differire nelle maiuscole da quello configurato:
  il confronto è esatto, quindi la richiesta resta rifiutata (fail-closed).
- **`CheckGroup` obbligatorio: basta una casella, non tutte.** Con
  `required()` i renderer Bootstrap e Wonder mettevano `required` su ogni
  `<input>` del gruppo, quindi il browser bloccava l'invio finché non erano
  tutte spuntate: nel backend non si poteva creare un popup senza spuntare
  tutte le pagine. Ora `required` resta sul gruppo, come classe
  `wi-{type}-required` sul contenitore (come `CheckTree` e `DynamicCheck`),
  che la lib backend controlla come «almeno una». Prima il salvataggio era
  bloccato anche dal `check()` della lib del backend, non solo dal browser.
  Il form del backend non passa dal validator del `dataSchema`, e un JSON
  vuoto (`"[]"`) supera `required()`: per le pagine del popup il controllo
  lato server è in `PopupResource::mutateRequestValues` (voce sotto). Nel
  tema Wonder frontend la lib non legge ancora
  `.wi-checkbox-required`/`.wi-radio-required`: lì un gruppo obbligatorio non
  ha controllo lato client (follow-up nella lib). Coperto da
  `tests/Themes/CheckGroupRequiredTest.php`.
- **Popup: le pagine sono obbligatorie anche lato server.** Il gruppo
  `pages` era obbligatorio solo nel browser: un POST del form backend senza
  pagine, con l'array vuoto del form (`pages[]` = `''`), con `"[]"` o
  `"null"` salvava un popup che non compare su nessuna pagina. Ora
  `PopupResource::mutateRequestValues()` lo rifiuta con
  `InvalidArgumentException` (codice 422) e il messaggio
  `popup.pages_required` (`resources/lang/{it,en}/popup.json`), mostrato come
  avviso del form. In creazione il controllo vale sempre, in modifica solo se
  `pages` arriva. Vale solo per il context `backend`: l'API del popup non
  dichiara campi scrivibili (`apiSchema()` ha solo `index`), quindi lo store
  API (usato anche da QuickCreate) riceve sempre un payload vuoto e resta
  com'era (esteso ad API e QuickCreate in Unreleased, «Popup: pagine
  obbligatorie anche via API e QuickCreate»). Nessuna migrazione: i popup già salvati senza pagine restano, si
  possono ancora mostrare/nascondere e riordinare dalla lista, e alla
  prossima modifica dal form chiedono una pagina. Coperto da
  `tests/App/Resources/PopupResourceTest.php`.
- **Cambiare l'email di un utente azzera `email_verified`.** Nel ramo di
  modifica di `user()` (form utenti del backend, account backend, chiamanti dei
  siti) un'email diversa da quella salvata, senza contare maiuscole e spazi,
  scrive `email_verified = 0` ed `email_verified_at = NULL` nella stessa UPDATE,
  anche se il POST porta `email_verified`. Prima il flag restava `true` e la
  nuova casella risultava verificata senza che nessuno l'avesse confermata. Nella
  stessa modifica si revocano i link di verifica ancora aperti, spediti alla
  vecchia email. Il cambio email del cliente (`AccountEmail::confirm()`) rimarca
  la casella come verificata dopo il salvataggio, come prima.

## 2.4.0-beta.5 — 2026-10-26

### Aggiunto
- **Text e RichText nel tema Wonder (`B-CORE-I1`).** Nuovo renderer
  `Themes\Wonder\Components\Text` sulle utility della lib (`fw-700`,
  `text-small`, `tx-secondary`, `tx-<colore>`, `a-l`/`a-c`/`a-r`, `subtitle`);
  `italic()` non aggiunge classi. Senza `columnSpan()` il testo esce senza
  `<div>` intorno. `AccountModal::confirm()` usa `Text` al posto di un oggetto
  anonimo: il markup resta `<p>…</p>`.
- **`Elements\Frontend\EmptyState` e `Elements\Frontend\Pagination`
  (`B-CORE-I2`)**, solo tema Wonder, sulle classi `.wi-empty-state*` e
  `.wi-pagination*` della lib. `Pagination::make($page, $pages)->url(...)` per
  qualunque lista, `Pagination::account($pagination, $baseUrl)` per i conti di
  `AccountPagination`. Nuove chiavi `components.pagination.{label,previous,next}`.
  - `url($base)` accetta anche l'URL corrente: toglie il `page` già presente,
    conserva gli altri parametri e rimette il `#frammento` in coda. Senza
    `url()` il link è `?page=N`, anche per la prima pagina.
  - `make()` con una pagina sola (o zero) non stampa nulla, a differenza del
    vecchio `pagination()` che mostrava un "1" attivo: `hidden(false)` la mostra.
    `Pagination::account()` resta com'era (nascosta solo senza righe).
  - Un `aria-label` dato con `attr()` vince su `label()` e non si ripete.
  - `EmptyState::titleTag('h3')` sceglie il livello del titolo (`h2` di
    default, da `h2` a `h6`).
- **`Badge::inline()`.** Nel tema Bootstrap il badge esce senza il
  `<div class="col-span-*">` della colonna: solo `<span>` (o `<a>` con href),
  da concatenare a testo, celle di tabella e helper delle Resource
  (`->inline()->render('bootstrap')`). Variant, outline, pill, icona,
  attributi ed escaping restano uguali; con `inline()` `columnSpan()` è
  ignorato, in qualunque ordine. Nel tema Wonder il badge era già in linea e
  l'HTML non cambia. Senza `inline()` l'output è identico a prima (bloccato da
  `tests/Themes/BadgeInlineTest.php`). Doc: catalogo `badge` e
  `docs/app/concetti/componenti/README.md`.
- **`POST /api/csrf/` (rotta `csrf`, area `api`).** Restituisce `{ "token": "…" }`, il
  token CSRF della sessione corrente, per i form AJAX rimasti aperti oltre la
  sessione: dopo un `403` (o un `401` del token di pagina, da rinnovare poi con
  `/api/token/page/`) il form prende il token nuovo e il secondo invio
  passa senza ricaricare la pagina. Solo POST same-origin (`SameOrigin`, se no
  `403`), con sessione attiva, `Cache-Control: no-store`; non chiede il vecchio token e non
  dà accesso ad altro. Test: `tests/Api/CsrfRenewTest.php`.

### Cambiato
- Il componente `frontend.account.pagination` è un wrapper di
  `Pagination::account()`: stessi parametri e stesso markup, a parte gli spazi
  tra i tag.
- **Token della pagina scaduto: niente più `419` «CSRF token invalid» sul
  frontend.** I form di `AuthController` (login, registrazione, completamento,
  recupero, restore) tornano con l'alert `929` e i campi scritti, senza
  password né token; logout, accesso Google e fine impersonificazione fanno
  redirect con lo stesso avviso (`FlashMessage`). Nel pannello account
  `invalidCsrf()` resta `never` ma passa da `renderCsrfFailure()`, nuovo
  punto di estensione `protected` per i moduli. Helper nuovi:
  `Csrf::ALERT`, `Csrf::oldInput()`, `Csrf::failureMessage()`,
  `Csrf::flashFailure()`. `CsrfGuard` e la creazione rapida del backend
  restano `403` ma con il testo tradotto della notifica `929`, senza «CSRF»
  né «token».
- **Pannello account: i `$values` dei modal passano da `Csrf::oldInput()`.**
  `personalModals()` e i form del core ricevono `_csrf`, `csrf_token`,
  `logout_token`, `current_password` e `password*` con la chiave ma vuoti
  (prima `_csrf`, `current_password` e `password` mancavano, gli altri
  arrivavano con il valore scritto).

### Deprecato
- `pagination()` e `paginationButton()` di `app/function/frontend/plugin/pagination.php`:
  usa `Elements\Frontend\Pagination`.

## 2.4.0-beta.4 — 2026-10-21

### Breaking
- **Sql: chiavi duplicate in `Query::bind()` (`C-SQL-I8`).** `':a'` e `'a'`
  nello stesso array di parametri lanciano `RuntimeException`; prima vinceva
  in silenzio l'ultima. Nessun chiamante fuori dal core usa `bind()`. Il
  contratto degli argomenti grezzi di `Query` (chiavi delle condizioni,
  `$attributes`, `$order`, `$column`, `JoinTable()`) è documentato in
  `docs/app/piattaforma/sicurezza.md`.
- **Sql: `ORDER BY` validato (`C-SQL-I6`).** `Query::Select()` e
  `SelectForUpdate()` passano ordine e direzione da `Query::orderClause()`:
  una direzione diversa da `ASC`/`DESC` (per esempio `'DESC, id'`, prima
  accodata così com'era) ora lancia `RuntimeException`; maiuscole, minuscole
  e spazi attorno sono normalizzati, null o vuota = nessuna. Lanciano anche
  una direzione senza `$order` (prima SQL non valido) e un `$order` con `;`,
  `--`, `#`, `/*` fuori dalle stringhe, con stringhe/backtick non chiusi o
  con una `\` dentro una stringa (l'apice si raddoppia: `'it''s'`), tutto
  prima della query. Le sottoquery `(SELECT ...)` restano ammesse: l'ordine
  va sempre scelto dal codice da una whitelist, mai preso dall'input. I chiamanti verificati (core,
  gestionale, ecommerce, siti) usano già solo `ASC`, `DESC` o null.
- **Upload: `gif` e `svg` esclusi di default (`C-UPL-2`).** Un campo file
  senza `extensions` accetta la lista del tipo dell'input: `image` =
  `png`, `jpg`, `jpeg`, `webp`; `video` = `mp4`; `gallery` = l'unione. Con un
  tipo ignoto sono negati `php`, `php3`-`php8`, `phtml`, `phar`, `pht`,
  `phps`, `html`, `htm`, `xhtml`, `xht`, `shtml`, `mht`, `js`, `mjs`, `svg`,
  `svgz`, `xml`, `xsl`, `xslt`, `cgi`, `pl`, `py`, `sh`, `htaccess` e i file
  senza estensione (ALERT 921); `svgz` è rifiutato anche se dichiarato, come
  ogni estensione che non è `[a-z0-9]+` (`logo.svg `, `logo.svg.`). Le
  Resource Media Immagini e Icone accettano `png`, `jpg`, `jpeg`, `webp`:
  una gif o un svg caricati lì ora sono rifiutati. `finfo` rifiuta un file il
  cui contenuto non corrisponde all'estensione (ALERT 927). Le `extensions`
  del modello non sono più scavalcate dal tipo dell'input. Per tornare ad
  ammettere gif o svg in un campo vanno dichiarati in `extensions`; un svg
  ammesso passa da `SvgSanitizer` e, se non è valido o la radice non è
  `<svg>` nel namespace SVG, è rifiutato con ALERT 931.
- **CSV neutralizzato di default (`C-UPL-4`).** `arrayToCsv()`,
  `sqlExport()` e il download CSV delle Resource mettono un apice davanti
  alle celle che iniziano con `=`, `+`, `-`, `@`, tabulazione o CR, tranne i
  valori numerici (`-5`, `+39`): `+39 333 1234567` esce come
  `'+39 333 1234567`. Opt-out: `arrayToCsv(..., false)`,
  `sqlExport(..., false)`, `->downloadFormulaSafe(false)`. Excel e
  `downloadAsText()` non cambiano.
- **`X-Forwarded-*` solo con `APP_TRUST_PROXY`.** `UrlParser::isHttpsRequest()`
  e `requestScheme()` ignorano `X-Forwarded-Proto`, `-Scheme` e `-Ssl` se il
  flag è spento; `UrlParser::requestIp()` segue `ClientIp::resolve()`
  (`REMOTE_ADDR`, oppure l'ultima voce di `X-Forwarded-For` con il flag). Dietro
  un proxy che termina HTTPS senza `APP_TRUST_PROXY=true` le API rispondono
  `403 Richiesta non sicura` e la lista IP degli utenti API confronta l'IP del
  proxy: va attivato il flag. Prima la prima voce di `X-Forwarded-For`, scelta
  dal client, aggirava la lista IP.
  Senza il flag anche `__url()`, `currentBase()` e i redirect assoluti escono in
  `http://` dietro quel proxy.
- **dataLayer: consenso negato di default e `user.id` come hash
  (`C-ERR-I5`).** Prima del loader GTM il layout frontend emette
  `gtag('consent', 'default', …)` con `ad_storage`, `analytics_storage`,
  `ad_user_data` e `ad_personalization` a `denied` e `wait_for_update: 500`:
  i tag Google aspettano fino a 500 ms l'aggiornamento della CMP (Iubenda in
  GTM). Il core manda in `user.id` solo un HMAC dell'id con `APP_KEY`
  (`DataLayer::userId()`), non più l'id numerico: le variabili e i tag GTM che
  lo usano ricevono un valore diverso. Con il modulo ecommerce richiede la
  versione con il fix `fix/2026-10-2d-datalayer` (ecommerce non manda più
  `user`); le versioni precedenti rimandano l'id in chiaro dopo il core.
- **Meta Pixel non più emesso dal core.** Il layout frontend (`head.php`) non
  stampa più lo script `fbq` né il `<noscript>` di `facebook.com/tr`, anche con
  `pixel_facebook` compilato e attivo in `Dev > API e servizi > Analitica`:
  partiva senza attendere il consenso. Il Pixel va configurato come tag GTM
  bloccato dal consenso di Iubenda (docs GTM, «Meta Pixel in GTM»). Il campo
  resta nel backend con un testo di aiuto e `$ANALYTICS->pixel` è ancora
  calcolato per gli override dei siti; la rimozione del campo è in Fase 3.
- **Logout backend in POST.** `/backend/account/logout/` esce solo in `POST`
  con `_csrf` (o `X-WI-CSRF`); in `GET` mostra una pagina di conferma con il
  bottone «Esci» e non chiude più la sessione. Nel menu del core «Esci» è un
  form (`sidebarOffcanvas()` accetta `'method' => 'post'`). I link `<a>` a
  `/backend/account/logout/` in moduli e siti ora portano alla conferma: per
  uscire in un clic vanno sostituiti con un form `POST` e `Csrf::field()`.
- **«Collega webhook» Stripe in POST.** `GET /api/service/stripe/connect/`
  mostra solo una pagina di conferma; il collegamento avviene in `POST` con
  `_csrf` nel corpo (o `X-WI-CSRF`), altrimenti `403`. Il
  token non è più nell'URL dei bottoni delle credenziali: i link salvati con
  `?_csrf=` aprono la conferma e non collegano più nulla da soli. La rotta
  (stessa URL) è ora in `area('backend')` con il layout `backend.auth`, senza
  header CORS, e chiede il login admin (`guarded()->permit(['admin'])`): prima
  bastava una sessione qualsiasi. `HEAD` e gli altri metodi ricevono `404` dal
  router, che non mappa `HEAD` su `GET`. La `POST` si chiama
  `service.stripe.connect.submit`.
- **Rinnovo del token di pagina con CSRF.** `POST /api/token/page/` richiede
  anche il token CSRF di sessione (`X-WI-CSRF` o `_csrf`), altrimenti `403`.
- **API: 500 generico con `request_id` (`C-ERR-I2`).** Sui 500 di
  `RouteDispatcher`, `Wonder\Api\Handler`, API delle Resource e
  `api/app/update`, `response` è `Errore interno.` (`UpdateRunner failed` per
  l'update) se il debug è spento. `success` e `status` restano; si aggiungono
  `request_id` e l'header `X-Request-Id`. I client esterni che leggevano il
  messaggio dell'eccezione dai 500 ricevono il testo generico. `api/app/update`
  non restituisce più `file` e `line`: finiscono in `error_log` con il
  `request_id`. I 4xx non cambiano.
- **Debug e `display_errors` (`C-ERR-I4`).** `Debug::enabled()` segue
  `APP_DEBUG` se presente, altrimenti `APP_ENV=local`. Le richieste da
  localhost o da reti private non accendono più il debug. `display_errors`
  segue il debug (fuori dalla CLI), `log_errors` è sempre attivo,
  `X-Powered-By` viene tolto.
- **Header di sicurezza e `Cache-Control` (`C-ERR-I1`).** Le risposte PHP
  hanno `nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `frame-ancestors 'self'`, `Referrer-Policy` e `Permissions-Policy`. HSTS è
  opt-in con `APP_HSTS=true`. Backend e pagine con utente autenticato hanno
  `Cache-Control: no-store, private`. Forge aggiunge a `.htaccess` il blocco
  `WONDER SECURITY` (header per gli statici, deny di `.env`, log, dump,
  manifest e cartelle interne; `vendor/` è negata tranne gli asset statici di
  `vendor/*/*/resources/assets/` e il generatore xml-sitemaps) e toglie il
  vecchio blocco `Header always set` con `X-XSS-Protection` se è identico a
  quello generato. Se il blocco è stato modificato va tolto a mano: `forge
  update` stampa un avviso per ogni `Header always set` fuori dai blocchi
  `WONDER`. Un sito che serve altri file da `/vendor/` riceve 403, come
  chi serve `*.zip`, `*.tar`, `*.tar.gz` o `*.tgz` fuori da `assets/upload/`.
  Deny e cartelle negate valgono senza distinzione di maiuscole.
- **Upload senza esecuzione di script (`C-UPL-3`).** Forge scrive il blocco
  `WONDER UPLOAD` in `assets/upload/.htaccess`.
- **Errori SQL senza query (`C-SQL-I3`).** `Sql\Utility\Error` lancia
  `Sql\Utility\QueryException` (estende `Exception`). Il messaggio non
  contiene più la query, disponibile in `query()` e nel contesto del log.
- **Log in `storage/logs` con redazione (`C-ERR-I3`).** `Logger` scrive solo
  in `ROOT/storage/logs/` (cartelle `0750`, file `0640`), senza ripiegare sulla
  cartella corrente; `db-connections.log` è ora lì. Gli URI nei log mascherano
  `token`, `t`, `key`, `password`, `email`, `_csrf`. In `auth_log`, `value` è
  mascherato e `verification_sent_link` non viene più scritto. I log esistenti
  non vengono spostati. `php forge build` e `Command::ensureDir()` creano le
  cartelle con `0755` (`storage/logs` con `0750`) invece di `0777`; le cartelle
  esistenti non cambiano permessi.
- **Validazioni backend.** `ApiRequest::selectDatabase()` accetta solo `main`
  o un database configurato (altrimenti 422). `delete-icon` accetta solo
  tabelle registrate con colonna `icon`, richiede il permesso di modifica e
  cancella solo file dentro la cartella di upload.
- **Password: argon2id/bcrypt, niente `sanitize()` (`C-PWD-I1`).**
  `hashPassword()` usa `password_hash()` con `PASSWORD_ARGON2ID` (bcrypt se
  argon2id non è disponibile). `checkPassword()` verifica sia i nuovi hash sia
  i phpass `$P$`/`$H$` (confronto con `hash_equals`); al login riuscito
  `authenticateUser()` reidrata la password con `hashPassword()` quando
  `passwordNeedsRehash()` è vero e scrive `password_rehash` in `auth_log`.
  Nessun reset forzato. La password non passa più da `sanitize()`, né al login
  né in account backend: `checkUserPassword()` prova il valore grezzo e, solo
  per un hash phpass, ripiega sul valore sanificato (password storiche con
  apostrofi o spazi). **Ripiego transitorio, rimosso nella beta successiva.**
  `Wonder\Password::CheckPassword()` confronta con `hash_equals`. In account
  backend una password nuova vuota o di soli spazi è rifiutata con alert `977`.
- **reCAPTCHA: fail-closed in produzione sull'auth, 401/403 errore di
  configurazione, salto in locale (`C-PWD-I3`).** Nuovo
  `RecaptchaGuard::failClosed(?bool $value = null)` (senza argomento: solo in
  produzione): con Google irraggiungibile (eccezione, rete, 429, 5xx) la
  verifica dà `617` con log `ERROR` invece del salto. Lo usano login,
  registrazione, completamento della registrazione, recupero e reimpostazione
  della password del frontend (`AuthController`: `login`, `signup-request`,
  `signup-completion`, `password-recovery`, `password-restore`); form
  contatti, checkout e gli altri `Resource::verifyRecaptcha()` restano
  fail-open. Un 401/403 di Google è ora `617` con `reason: config` (prima:
  salto); l'engine li classifica `available: true` con il nuovo campo
  `config`. Con `APP_ENV=local` la guardia salta sempre, prima di leggere il
  token, con una riga `INFO` `reason: local_env`.
  **Deprecata `G_RECAPTCHA_SECRET_KEY`:** nessuna verifica la usa (il server
  usa `GCP_PROJECT_ID` + `GCP_API_KEY` di reCAPTCHA Enterprise); resta letta
  dalle credenziali per compatibilità e verrà rimossa.
- **Auth: nessuna enumerazione degli account in pagina (`C-PWD-I5`).**
  `authenticateUserLogin()` mappa anche `924` (registrazione senza password,
  email da verificare senza `sent_link`) su `925`. Il login frontend di un
  account solo federato non mostra più «accedi con Google»
  (`use_federated_login_*`): risponde `925`, conta il tentativo e spedisce la
  mail `auth.email.federated_*`. La registrazione con un'email già registrata
  (`906`) non mostra più `email_exists`: reindirizza a `email.sent` come il
  caso nuovo e spedisce `auth.email.existing_*`, con l'evento
  `signup_existing` nel nuovo bucket `signup` di `LoginThrottle` (3 in 15 min
  per email, 10 in 60 min per IP), separato da `recovery`: la mail non
  consuma i tentativi di recupero password dell'utente. I siti che leggono `906`/`924` dal login o
  l'errore `email.exists` della registrazione vanno aggiornati.
  Resta un residuo fino al blocco 2d (coda mail): se l'invio sincrono della
  verifica fallisce, il caso nuovo risponde `908` mentre quello esistente
  reindirizza comunque, e l'utente creato ritenta nel ramo `906`.
- **Ricordami opt-in, frontend e backend (`C-PWD-I7`).** Nuovo
  `AuthProfile::rememberMe()` (`always|optional|never`, default `optional`) e
  `AuthProfile::remember(array $input)`. Con `optional` il login frontend
  mostra la checkbox `remember` non spuntata: il cookie di 30 giorni si crea
  solo se è spuntata (prima: sempre). Vale anche per Google dal login, anche
  dopo il completamento della registrazione. Il completamento con password e
  Google dalla registrazione ricordano solo con `always`. Il login backend ha
  la stessa checkbox (`AccountPageSchema`, campo `remember`) e passa
  `RememberMe::requested($_POST)`. Nuovo `RememberMe::requested()`. I default
  `$REMEMBER = true` di `authenticateUserLogin()`/`authenticateUser()` non
  cambiano: chi li chiama senza argomento continua a ricordare.
- **API backend di sessione: token CSRF obbligatorio (`C-CSRF-I2`).** Il gruppo
  `/api/backend/*` dichiara `->csrf()`: `list/table`, `change/boolean`,
  `active`, `visible`, `delete`, `authority`, `move`, `file/delete`,
  `file/move`, `delete-icon`, `oauth/revoke`, `editorjs/file`,
  `editorjs/image` rispondono `403` JSON («Token CSRF non valido.») a un POST
  senza `_csrf` o header `X-WI-CSRF` validi, prima dell'handler.
  `/api/backend/alert/` (solo Bearer) resta fuori. Nuovi `->csrf()` su
  `RouteGroup`/`RouteDefinition` e `Wonder\Http\CsrfGuard`: opt-in, la verifica
  non diventa centrale. `X-WI-CSRF` è ammesso in `Access-Control-Allow-Headers`.
  **Vincolo di rilascio:** richiede sui siti la lib con `C-CSRF-I1`
  (`wonder-image/lib` PR #12, header `X-WI-CSRF` da `ajaxRequest`, `fetch`,
  `ApiClient`, DataTables ed Editor.js); con una lib precedente le azioni
  dell'elenco backend, gli upload Editor.js e la revoca OAuth ricevono `403`.
- **Pagine backend: token CSRF obbligatorio sui POST che scrivono
  (`C-CSRF-I3`).** Store, update, eliminazione da pagina e pagina-form delle
  Resource, utenti (`UserManagementPageController`), account (profilo e
  password), uploader massivo, file di configurazione, `POST /backend/` e i
  form legacy che includono `app/html/backend/index.php` (`upload`/`upload-add`)
  verificano `_csrf`/`X-WI-CSRF`: senza token valido non scrivono e mostrano
  l'alert `929` («Richiesta non valida», nuovo codice in `notifications.json`).
  La creazione rapida risponde `403` JSON e il modal manda ora l'header
  `X-WI-CSRF`. Aggiunto `_csrf` ai form che non lo avevano (account, utenti,
  file di configurazione, uploader massivo). Nei form legacy il token lo emette
  il core: `submit()` e `submitAdd()` aggiungono `_csrf` quando la pagina
  include `app/html/backend/index.php`. Con il buffer di output esce una volta
  per form; senza buffer (il caso delle pagine legacy reali) esce una volta per
  richiesta, quindi copre un solo form legacy per pagina. Se il form ha già
  `Csrf::field()` può uscire un secondo `_csrf` con lo stesso valore: è
  innocuo. Le pagine con un solo form legacy non cambiano nulla; con più form
  legacy nella stessa pagina, o con form costruiti come stringa, il sito stampa
  `Csrf::field()` in ogni form.
- **Cookie: `SameSite=Lax`, `Secure` su HTTPS, `HttpOnly` (`C-CSRF-I4`).**
  `Wonder\Http\Cookie` emette ogni cookie con `SameSite=Lax`, `HttpOnly` (si
  spegne solo con `httpOnly: false`) e `Secure` quando la richiesta è HTTPS.
  Lo stesso vale per il cookie di sessione (`ini.php` usa
  `Cookie::sessionOptions()`: prima non aveva né `Secure` né `HttpOnly` salvo
  `php.ini`), per `visitor_id` (ora via `Cookie::set()`, `HttpOnly` e path `/`
  invece della cartella della richiesta) e per i cookie remember-me. Il core
  non ha un concetto di proxy fidato: `X-Forwarded-Proto: https` conta solo con
  il nuovo flag `.env` `APP_TRUST_PROXY=true` (default spento); senza flag
  dietro un proxy che termina HTTPS i cookie escono senza `Secure`. Un JS di
  sito che leggeva `visitor_id` non lo vede più. Un ritorno da pagamento con
  POST cross-site non porta il cookie di sessione: Nexi HPP e PayPal del core
  tornano in GET, POST-back di siti NON VERIFICATO.
- **API delle Resource: permessi vuoti negano.** Un'azione senza authority in
  `permissionSchema()->api(...)` / `->apiCrud(...)` risponde `403` («Azione API
  non consentita per questa risorsa.») prima di `Endpoint`, e
  `ResourceRouteRegistrar` non registra la route (`api.resource.<slug>.<azione>
  non registrata` nel log, una volta per processo). Prima `[]` significava
  «qualsiasi authority». Il default di `PermissionSchema` è vuoto: le Resource
  (anche dei moduli) che non dichiarano permessi API perdono le route API
  finché non li dichiarano.
  `Endpoint::resolveAuth([])` non cambia: MCP, task, update e alert restano
  come sono.
- **Resource singleton del core: `app-config-analytics`, `app-config-seo`,
  `app-media-logos`.** `index`, `store`, `destroy` non più registrate (prima
  aperte a qualsiasi bearer API); `show`/`update` restano, solo `admin`. Le tre
  `apiSchema()` dichiarano ora `->only(['show', 'update'])`, come
  `SingletonResource`.
- **API delle Resource: `apiSchema()->guard()` ora è applicato.** Se la Resource
  lo dichiara (default `api_internal_user`), l'utente del bearer deve avere
  anche quell'authority (`403` altrimenti). Prima il campo non era mai letto;
  senza `guard()` non si richiede nulla in più.
- **API delle Resource: token di pagina solo su opt-in.** `API_TOKEN` (`typ:
  page`) è rifiutato con `401` sulle route `api.resource.*` salvo
  `apiSchema()->allowPageToken()`. Le Resource chiamate dalla lib nel browser
  (per esempio un form pubblico con `store`) devono dichiararlo.
- **Mutazioni dall'elenco backend autorizzate per tabella.** `change/boolean`,
  `active`, `visible`, `move`, `file/delete`, `file/move` (permesso `update`) e
  `delete` (permesso `delete`) passano da `TableAuthorization`: tabella con
  Resource → `permissionSchema()->backend(...)`; tabella legacy senza Resource
  → solo `admin`. Prima bastava essere un utente backend attivo. Rifiuto `403`
  «Operazione non consentita.».
- **`api.backend.authority`**: `permit(['admin'])`, solo tabella `user`, mai la
  propria riga.
- **`file/delete` e `file/move` validano i parametri.** Tabella risolta su
  `Table::$list`, colonna con input di tipo file (`format.file`), `folder`
  normalizzato con `UploadDir::normalize` sotto `upload/`; altrimenti `422`
  «Parametri non validi.» senza SQL. Indice del file inesistente → `404`.
- **Token di pagina: 1 ora e legato alla sessione.** `API_PAGE_TOKEN_TTL`
  passa da `43200` a `3600`. Con una sessione PHP attiva (anche anonima) il
  token porta `sid`, HMAC dell'id di sessione, e `BearerVerifier` lo rifiuta
  (`401`) se la richiesta non ha quella sessione: non vale più copiato fuori dal
  browser, né dopo `session_regenerate_id()` (login/logout) finché la pagina non
  viene ricaricata o il token rinnovato. `BearerVerifier::verify()` ha un
  parametro finale `?string $sessionId`. Compatibilità: i token senza `sid`
  emessi prima del rilascio valgono fino alla loro `exp` naturale, poi `401`.
  Il ramo di compatibilità per i token senza `sid` sarà rimosso nella beta
  successiva.

### Added
- **`Stripe::clientConfig()` e `Stripe::httpClient()` (`C-CHK-I4`).** I
  client del plugin (`Stripe`, `PaymentIntent`, `Connect`) hanno
  `max_network_retries` 2 e la versione `2025-08-27.basil`; il `CurlClient`
  condiviso dell'SDK passa a timeout 20 s e connessione 5 s (erano 80/30) e i
  retry globali di `\Stripe\Stripe` salgono a 2, così le POST v1 senza
  chiave ricevono l'`Idempotency-Key` automatica. Vale per tutto il processo,
  anche per i `StripeClient` dei moduli creati dopo; uno senza
  `clientConfig()` eredita i 2 retry solo se nasce dopo la prima chiamata a
  `httpClient()`, quindi dipende dall'ordine di creazione. Un client HTTP
  finto installato resta com'è.
- **`Connect::addEvents()` e `Connect::events()` (`C-CHK-I3`).** I moduli
  registrano al bootstrap gli eventi Stripe che gestiscono; «Collega webhook»
  registra `Connect::EVENTS` più quelli aggiunti, senza doppioni.
  `Connect::webhook($url, ?array $events = null)` accetta un elenco
  esplicito. Il default del core resta di quattro eventi. Doc:
  `docs/app/servizi/configurazione/pagamenti.md`.
- **`Wonder\App\Security\SvgSanitizer::sanitize()`.** Pulisce un SVG via DOM
  (`LIBXML_NONET`, niente entità, solo namespace SVG) e restituisce `null` se
  non è valido;
  `uploadFiles()` lo applica a ogni svg ammesso da `extensions`. Il blocco
  `WONDER UPLOAD` di `assets/upload/.htaccess` serve ogni file con `nosniff`
  e `.svg`/`.svgz` anche con `Content-Security-Policy "sandbox"` (al prossimo
  `forge update`). Il sanitizer toglie anche i commenti XML.
- **`UploadValidator::checkFormat()`** (whitelist, deny-list e `finfo` per
  `uploadFiles()`), `SpreadsheetText::formulaSafe()`/`formulaSafeMatrix()` e
  `TableLayoutSchema::downloadFormulaSafe()`.
- `Wonder\Http\SecurityHeaders`, `Wonder\Http\RequestId`,
  `Wonder\App\LogRedactor`, `Wonder\Sql\Utility\QueryException`,
  `Logger::append()` e `Logger::root()`. Doc:
  `docs/app/piattaforma/sicurezza.md`.
- **Token di verifica email salvati come hash (`C-PWD-I9`).**
  `consent_confirmation_tokens` ha la nuova colonna `token_hash` (64, nullable,
  unica); `token` diventa nullable, nessuna colonna rimossa.
  `generateUserVerificationToken()` salva solo lo sha256 e restituisce il token
  in chiaro al chiamante; `confirmUserVerificationToken()` cerca per hash e
  ripiega su `token` in chiaro per le righe già emesse (**ripiego rimosso nella
  beta successiva**). La conferma senza `mysqli` rilegge la riga prima di
  scrivere; `ConsentConfirmationTokenRepository` salva l'hash e
  `markConfirmed()` torna `bool` (conferma solo se non già usato né revocato).
  Richiede `php forge update` per la colonna.
- **Nuovo utente backend senza password in chiaro (`C-PWD-I8`).** Il form
  utenti non contiene più l'input nascosto con la password generata e la
  welcome mail non riporta credenziali: l'utente è creato senza password e la
  mail porta un link `password_set` valido 72 ore
  (`UserManagementPageController::WELCOME_TOKEN_TTL`). Mittente
  `$SOCIETY->email` (ripiego `noreply@wonderimage.it`, prima
  `info@wonderimage.it`). Esito in `auth_log` (`welcome_mail`, `mail_failed`,
  `token_failed`); senza mail l'utente usa il recupero password.
  `Random::generate()` (e quindi `code()`) usa `random_int()`. La cache JWKS di
  `OidcIdTokenVerifier` passa da `sys_get_temp_dir()` a
  `ROOT/storage/cache/` (nuovo parametro opzionale `cacheDir`).
- **Impersonificazione legata alla sessione e con scadenza (`C-PWD-I6`).**
  `Impersonation::issue()` salva lo sha256 dell'ID sessione dell'attore nel
  token; `start()` da un'altra sessione fallisce con audit `start_denied`.
  Nuovo parametro opzionale del costruttore `maxDurationSeconds` (default
  1800): dopo `expires_at` `current()` torna `null`, ripristina l'attore e
  registra `expired`. La scadenza è applicata a ogni richiesta dal bootstrap
  di sessione (`Impersonation::enforceExpiry()`), non solo dall'overlay
  frontend. Firma pubblica invariata.
- **Login e password del backend: CSRF, risposta uniforme, mittente
  (`C-PWD-I4`).** I form di login, recupero, restore e imposta password del
  backend emettono `_csrf` e gli handler lo verificano prima di tutto il resto:
  senza token valido alert `929` (`ResourcePageController::CSRF_ALERT`), nessun
  login, nessuna mail, nessun token consumato. Il recupero risponde sempre con
  il redirect `?alert=601`, anche con utente inesistente (evento
  `recovery_unknown`, senza lo username in chiaro), mail non partita o errore
  nell'emissione del token (`password_recovery` con `reason=token_failed`). Le mail
  di recupero, restore e set partono da `$SOCIETY->email` (ripiego
  `noreply@wonderimage.it`). reCAPTCHA sul login backend arriva in un intervento
  successivo.
- **Throttle di login e recupero password (`C-PWD-I2`).** Nuovo
  `Wonder\Auth\LoginThrottle`: conteggio su `auth_log` in finestra scorrevole.
  Login 5/15 min per email o username e 20/15 min per IP; recupero 3/15 min
  per identificatore e 10/60 min per IP; x10 con `Environment::isLocal()`.
  Bloccato: alert `930` («Troppi tentativi», nuovo in `notifications.json`;
  il piano indicava `926`, già usato), `Retry-After`, `429` su `/oauth/login`,
  evento `login_throttled`/`recovery_throttled`. Il recupero frontend oltre il
  limite risponde «inviata» senza mail e ora scrive `recovery_request`.
  `login_failed` e i log del recupero salvano `meta.identifier`. Indici
  `auth_log(ip, creation)` e `(event, creation)` (non distruttivi).
  `Retry-After` indica i secondi che mancano perché il tentativo più vecchio
  esca dalla finestra. L'IP del throttle e di `auth_log.ip` viene da
  `Wonder\Http\ClientIp::resolve()`: `REMOTE_ADDR`, oppure con
  `APP_TRUST_PROXY=true` l'ultima voce di `X-Forwarded-For` (quella del proxy).
- `POST /api/token/page/` (rotta `token.page`, area `api`): rinnovo del token di pagina
  per la sessione corrente, `{ token, expires_in }`. Senza guard, solo
  same-origin e con sessione attiva (`403` altrimenti, `405` se non POST),
  `Cache-Control: no-store`. La lib non lo usa ancora.
- `Wonder\Http\SameOrigin::check()`: `Sec-Fetch-Site: same-origin` oppure
  `Origin` con lo stesso host e porta della richiesta.
- `ApiTokens::sessionBinding()` / `currentSessionId()`; `ApiTokens::page()`
  accetta `$sessionId`.
- `ApiSchema::allowPageToken()` / `allowsPageToken()` e `declaredGuard()`;
  `Wonder\Api\Support\ResourceApiAccess` (permessi, guard e token di pagina
  delle route Resource); `Endpoint::userAuthorities()`.
- `Wonder\Backend\Table\TableAuthorization`: autorizzazione delle mutazioni
  su tabella, colonna file e cartella di upload.
- `Query::bind($sql, $params)` e l'helper `sqlBind()`: legano valori a SQL
  scritto a mano con segnaposto `?` o `:nome`, escapati sulla connessione;
  segnaposto senza valore o valori in più lanciano `RuntimeException`. Le firme
  degli helper `sql*` non cambiano.
- `Support\UploadValidator` e `Support\UploadDir`: controllo di un upload
  (whitelist estensioni, 10 MB, MIME reale) e sottocartella sicura dentro
  `upload/`. Nuovi alert 927 (tipo di file non coerente) e 928 (elaborazione
  dell'immagine non riuscita, PNG, JPG o WEBP) in tutte le lingue.
- Test `tests/App/RegistryPrecedenceTest.php` sulla precedenza per tabella:
  `ResourceRegistry::resolveByTable()` (non-fallback prima della fallback in
  entrambi gli ordini, solo fallback, due non-fallback) e `ModelRegistry::all()`
  (vince il FQCN alfabeticamente ultimo, qualunque sia la cartella). Docblock
  con la regola reale nei due registry e nota in `introduzione/architettura.md`.
  Nessun cambio di comportamento.
- `AuthValidator::localPhone($phone, $prefix)`: il numero da mostrare dopo il
  prefisso, tolto da un cellulare canonico (`+39333…` → `333…`) solo se inizia
  con quel prefisso. `AuthValidator::defaultPrefix()` dà il prefisso proposto
  (`Prettify\Phone::$defaultCountryCode`, `+39`). Li usano «Dati personali»
  (`AccountController`) e il completamento della registrazione (`AuthProfile`),
  senza cambiare il comportamento.
- Comando `php forge db:backup` (`Support\DatabaseBackup`): dump gzip del
  database in `storage/backup/<Ymd-His>-<8 hex>.sql.gz` (data e ora UTC, suffisso
  casuale), con `mysqldump` (credenziali in
  un file di opzioni temporaneo `0600`, mai sulla riga di comando) o, se manca,
  con un dump PHP via mysqli. Se `mysqldump` fallisce (exit ≠ 0, stderr, output
  vuoto o, con i commenti attivi, senza il marcatore finale `-- Dump completed`;
  con `skip-comments`/`compact` anche da `my.cnf` il marcatore non si controlla)
  riprova con il dump PHP e avvisa (in `forge update` nel campo
  `backup_warning`); fallisce solo se falliscono entrambi, senza lasciare file
  parziali.
  Con MariaDB (client `mysqldump --version` o server `SELECT VERSION()`) omette
  `--set-gtid-purged=OFF`. `--keep=N` (default 5) ruota i vecchi backup in
  ordine di data del nome, `--path=` cambia cartella. Nella cartella scrive, se
  mancano, `.htaccess` con `Require all denied` e un `index.html` vuoto; senza,
  il backup non parte. Su nginx serve una regola del server per `storage/`.
  Il dump PHP non gestisce colonne generate, binari con charset multibyte e
  fuso dei `TIMESTAMP`: è un ripiego.
- `php forge update` crea un backup prima di rinomine e migrazioni quando il
  database ha già tabelle; se il backup fallisce l'update si ferma. L'opzione
  `--no-backup` (o `'backup' => false` in `UpdateRunner::execute()`) lo salta;
  `/api/app/update/` accetta lo stesso parametro `backup` (default `true`;
  `false`, `0`, `"false"`, `"no"`, `"off"` lo disattivano), letto da
  `UpdateRunner::apiOptions()` in entrambi i percorsi di autenticazione.
- Campo «Sincronizzazione orari da Google» nelle sedi (`google_sync_hours`,
  default No): con Sì il task `wonder.google-places` sostituisce gli orari
  della sede con quelli di Google, con No restano gli orari manuali e la
  scheda non mostra differenze. Serve `php forge update` per la colonna.
- Element `StatusCard`, `LinkCard` e `LinkListCard` (tema Bootstrap): card di
  stato con icona e link, scorciatoia verso una pagina, elenco di link
  raggruppati. Li usa la home del backend; schede nel catalogo.
- Dato sintetico sui link della home del backend: `LinkListCard::link()` accetta
  badge e riga secondaria (`$badge`, `$detail`), una Resource li fornisce con
  `NavigationSchema::homeSummary($class)` e il contratto
  `Backend\Contracts\HomeLinkSummary`, calcolato solo da `HomeNavigation` e mai
  dalla sidebar.
- `NavigationSchema::homeShortcut($icon = null)`: la voce di una Resource dentro
  una sezione compare anche in "Accesso rapido" della home backend
  (`HomeNavigation::shortcuts()`), con l'icona data o quella della Resource.
- `Resource::allows($authorities, $action)`: dice se le autorità date possono
  eseguire un'azione della Resource, con la stessa regola delle route.
- Voci di stato della home estendibili: contratto
  `Backend\Contracts\HomeStatusItem`, chiave `backend.home_status` nella
  configurazione dei moduli (aggiunge, sostituisce o toglie con `false`) e
  `HomeStatus::set()` per il sito.
- `Iframe::googlePlace($placeId, $mapType)` e `Iframe::fromLocation($sede)`:
  mappa Google (Embed API, chiave `GCP_CLIENT_API_KEY`) di un Place ID o, in
  mancanza, delle coordinate, in vista `roadmap` o `satellite`
  (`Support\GoogleMapsEmbed`, che restituisce `''` quando non è utilizzabile).
- Attività sempre attive: `Task::make(...)->alwaysOn()` o
  `Contracts\AlwaysEnabledTask`; la sincronizzazione le riattiva e il backend
  non le sospende.
- Voci POST di `Dropdown`: l'opzione `hidden` (`['nome' => valore]`) aggiunge
  campi nascosti al form della voce.
- `pageSchema()->actions()` accetta anche gli Element `Button` e `Dropdown`,
  resi nella testata con attributi e conferma `data-wi-confirm*`.
- `TableLayoutSchema::downloadAsText()`: l'export della lista scrive ogni
  cella come testo (Excel non trasforma telefoni, CAP e codici in numeri) e
  il CSV mette un apice davanti alle celle che iniziano con `=`, `+`, `-`,
  `@` (`Backend\Support\SpreadsheetText`). `arrayToXlsx()` accetta il terzo
  argomento `$asText`.
- Auth frontend: `AuthProfile::phoneEnabled()` mostra il cellulare nel
  completamento anche facoltativo (obbligatorio resta `phoneRequired()`);
  `validationTexts()` restituisce le righe già tradotte dell'alert di pagina.
- `AddressExtension::prefix()` e `fieldKey()`; `AccountAddressForm` e
  `AccountAddressValidation` lavorano anche con i campi prefissati
  (`shipping_street`), `layout($fields, $address)` li ordina come quelli
  senza prefisso.
- Migrazioni dei moduli: un modulo dichiara in `module.json`
  `"database": {"migrations": [...]}` classi `Module\Contracts\ModuleMigration`
  che `forge update` esegue prima di creare o allineare le tabelle. Per le
  rinomine `Support\LegacyTableRenames` fa il piano e le applica in una sola
  `RENAME TABLE`; un conflitto ferma l'update senza toccare dati.
- Pannello account: `AccountPageExtension`, interfaccia opzionale delle
  estensioni (`BaseAccountExtension` la implementa vuota), con
  `personalFormFields()`, `personalModals()`, `savePersonalForm()`,
  `pageErrors()` e `afterEmailConfirmed()`: un modulo aggiunge campi, modal,
  form e controlli alle pagine del core senza sostituirne il controller.
- `docs/ai/DESIGN.md`: ogni rigenerazione di `root.css` (salvataggio di colori, font e
  impostazioni CSS dal backend, `forge import`, `forge update`) scrive anche
  `docs/ai/DESIGN.md`, la proiezione in Markdown di palette, tipografia, spaziatura,
  bottoni e classi utility pensata per gli agenti AI (`CssTokens::designMd()`). Solo
  con `Debug::enabled()`: `APP_DEBUG=true`, oppure richiesta HTTP da host locale
  quando `APP_DEBUG` non è attivo (da CLI conta solo `APP_DEBUG`). In produzione,
  con `APP_DEBUG=false` e richieste non da loopback, il file non viene scritto. È
  generato e non si modifica a mano; va gitignorato nel sito (`/docs/ai/DESIGN.md`,
  da aggiungere a mano nei siti creati prima di questa voce). Un errore di
  scrittura finisce in `storage/logs/error.log` senza bloccare il CSS.
- `Frontend\Support\SiteSchema`: JSON-LD `Organization` (da `$SOCIETY`) e
  `WebSite` in `<head>` su ogni pagina, con `@id` stabili
  (`/#organization`, `/#website`) che le entità delle pagine possono
  riferire.
- `Frontend\Support\RobotsTxt`: `php forge update` genera robots.txt con
  `Disallow` di base, sitemap del sito e quelle che i moduli dichiarano in
  `module.json` (`"seo": {"robotsDisallow": [...], "sitemaps": [...]}`). Il
  marcatore in testa al file lo lascia rigenerare; chi lo toglie lo
  personalizza a mano.
- `TableLayoutSchema::downloadUsing(callable $handler)`: l'«Esporta» della
  tabella (stesso bottone, stessi formati, rotta `export`) affida il file a un
  gestore proprio, che riceve il formato già validato e lo invia. Serve quando
  le righe non sono una per record. Senza la chiamata non cambia niente.
- `Dropdown`: `icon()` mette un'icona prima dell'etichetta del bottone che apre
  il menu, `caret(false)` toglie la freccia (`dropdown-toggle` in Bootstrap, lo
  chevron in Wonder) e `toggleAttributes()` dà attributi al bottone (`style`,
  `data-*`; `type`, `data-bs-toggle`, `aria-expanded` e `disabled` restano del
  tema). `text()` prende le opzioni `class` e `html` (markup fidato, per un link
  dentro la frase). Senza queste chiamate l'HTML non cambia.
- reCAPTCHA v3 (punteggio) in background con la casella v2 come riserva:
  `G_RECAPTCHA_SCORE_SITE_KEY` (chiave v3) e `G_RECAPTCHA_BADGE` (`false` nasconde il
  badge e mette la riga d'attribuzione di Google) si leggono con lo stesso cascade delle
  altre chiavi (`Credentials::recaptcha()`, ambiente, riga di security, default).
  `RecaptchaGuard` sceglie la chiave dal campo `g-recaptcha-type` (`score` o `checkbox`);
  sotto `minScore()` con la chiave a casella configurata lancia il `618` e il form mostra
  la casella (`RecaptchaChallenge`, in sessione), senza chiave a casella il `617`; se
  Google non risponde la verifica si salta (fail-open) con una riga di log. `617` e `618`
  escono HTTP 422 con il codice nel corpo e il login gestisce la sfida. Vedi
  `docs/app/servizi/recaptcha.md`.
  **Aggiornamento.** Richiede la lib `>= 2.1.2-alpha.27` (`extra.wonder.lib` e
  `package.json` alzati a `^2.1.2-alpha.27`). Ordine di rilascio: lib, poi app, poi
  ecommerce. Non impostare `G_RECAPTCHA_SCORE_SITE_KEY` su un sito prima che abbia la lib
  `>= 2.1.2-alpha.27` e il modulo ecommerce aggiornato: con la lib vecchia login,
  registrazione e check-out rispondono `617`; con l'ecommerce vecchio il `618` non apre la
  casella fino al ricaricamento della pagina.
  Un token oltre 8192 byte o con caratteri fuori da ASCII stampabile dà `617` senza
  chiamare Google: il client non può forzare il fail-open con un corpo che Google rifiuta.
  In Dev → API e servizi → Credenziali due righe, «Google reCAPTCHA v2» (Chiave Sito)
  e «Google reCAPTCHA v3» (Chiave Sito e Badge); la verifica è Enterprise, quindi il campo
  della chiave segreta non c'è più (la colonna resta). La cella `wi-recaptcha-cell` intorno
  al campo esce dal flusso quando è vuota (sola v3): niente spazio doppio nella griglia.
- Sedi e Google (#71): task `wonder.google-places` (spento, ogni notte alle
  03:30) che legge da Places API (New) orari regolari, secondari e speciali,
  stato, voto medio e al massimo cinque recensioni di ogni sede con Place ID
  (`GCP_API_KEY`). Sostituisce gli orari solo nelle sedi con la
  sincronizzazione attiva (vedi sopra); lo stato non si applica mai. Nuove tabelle
  `society_location_google` e `society_location_reviews`; `SocietyLocations`
  aggiunge `google` e `reviews` a ogni sede. Differenze ed errori compaiono
  sopra la scheda della sede.
- `Resource::formNotices()`: avvisi sopra il form backend, fuori dal `<form>`
  (`FORM_NOTICES`).
- `GoogleReviews`: recensioni Google di una sede nei temi Wonder e Bootstrap,
  con le attribuzioni richieste da Google; scheda nel catalogo dei componenti.
- `AddressExtension::withNameSearch()`: l'autocompletamento cerca anche le attività
  per nome (`data-wi-place-names`, lib 2.1.2-alpha.26), così place ID e link puntano
  alla scheda Google. Acceso sull'indirizzo delle sedi (`SocietyLocation`), non sulla
  sede legale.
- `CheckGroup` nel tema Wonder: `pills()` rende le voci come pillole in linea
  (`wi-check-pill`, CSS una volta per pagina da `PageAssets` con i token
  `--input-*`) e il segno dell'opzione (`color`, `icon`, `image`) esce davanti
  al nome, in pillole e in lista come nel backend. Le voci della lista stanno
  in un contesto posizionato che contiene i float, così non si sovrappongono.
- I renderer Wonder di `InputNumber`, `InputPrice` e `InputPercentige` accendono
  da soli AutoNumeric (`Themes\Concerns\RendersAutonumeric`): il frontend non
  lo caricava e `setInput()` falliva su `AutoNumeric` indefinito.
- Catalogo dei componenti: schede PHP in `docs/components/<categoria>/*.php`
  (`Wonder\Docs\ComponentDoc`, esempi eseguiti e mostrati dallo stesso
  codice), rese come pagina con anteprima viva per tema e riferimento API nel
  backend (Dev → Componenti, `/backend/app/docs/components/`) e dal pacchetto
  con `composer docs` / `php bin/docs.php` (server autonomo, asset della lib
  da `node_modules/wonder-image` tramite il nuovo `package.json`). La
  disponibilità nei temi è calcolata dal `Resolver`; le anteprime Bootstrap
  si vedono anche in modalità scura. Guida in `docs/components/README.md`.
- `Code`: blocco di codice con evidenziazione server-side
  (`Wonder\Support\Code\Highlighter`: PHP dal tokenizer nativo, HTML, CSS,
  JS, JSON, bash) e bottone "copia", nei temi Wonder e Bootstrap.
- `Preview`: finestra di anteprima (`<iframe>`) con sorgenti selezionabili,
  passaggio chiaro/scuro, altezza che segue il contenuto e gruppo sincronizzato
  via `localStorage`, nei due temi.
- `Wonder\Themes\Support\PageAssets::once()`: frammenti CSS/JS stampati una
  volta per pagina, condivisi fra renderer di temi diversi.
- `Wonder\View\CssTokens`: il template di `set-up/root.css` e `color.css`
  come classe (`cssRoot()` e `cssColor()` delegano qui); `defaultRoot()` dà i
  token di un sito nuovo senza database.
- `Dependencies::reset()` e `Dependencies::active()`; i renderer dei grafici
  accendono da soli Chart.js (`Dependencies::chartjs()`), come Swiper.
- Chiavi `components.code.*` e `components.preview.*` nelle traduzioni.
- `UserAccountGateway::createUserWithoutPassword()`: crea il cliente che ordina come
  ospite, attivo, senza password e con l'email da verificare.
- Cambio password del pannello account: `Wonder\Auth\Frontend\AccountPassword`
  (campi, validazione e salvataggio; password attuale richiesta solo se
  l'account ne ha una, nessun campo di conferma), `RememberMe::revokeUser()`
  per revocare i token "ricordami" dopo il cambio, nuove chiavi `account.password.*` e
  `auth.validation.errors.{password_same,current_password_required,current_password_wrong}`.
  Si apre da un modal di «Dati personali» (`AccountController`).
- Pannello account del core, a richiesta con `AccountRoutes::register(AccountPanel, ?AuthProfile)`
  (`Wonder\Auth\Frontend`): route `account.*` con URL italiani (`/account/`,
  `/account/dati-personali/`, `/account/indirizzi/…`, `/account/fatturazione/`),
  sezioni `overview`, `personal`, `addresses` e `billing`, `AccountController`
  (non `final`, helper `protected`: i moduli lo estendono per le loro pagine),
  `AccountPage`, `AccountModal::make()`/`confirm()` e i servizi `AccountPersonal`,
  `AccountEmail`, `AccountAddresses` e `AccountBilling`. Chi la registra non
  scrive controller né viste: bastano `register()` e, se serve, una sottoclasse
  di `AccountPanel`. Le viste sono sigillate (nessuna sostituzione da `custom/`).
- `AccountExtension` e `BaseAccountExtension`: come un modulo si aggancia al pannello
  (`AccountRoutes::extend()`). Ganci `routes()`, `navigation()`, `overviewRows()`,
  `personalRows()`, `personalFields()`, `validatePersonal()`, `personalUserValues()`,
  `afterPersonalSaved()` e `head()`; `AccountRoutes::group()` apre il gruppo delle
  route private (prefisso `/account`, nomi `account.*`, login e authority del pannello).
- Paginazione del pannello account: `Wonder\Auth\Frontend\AccountPagination`
  (`make()`, `requested()`, `url()`, `window()`) e il componente
  `frontend.account.pagination`, che stampa il piede della tabella a righe
  («Risultati da X a Y di Z» e i quadrati delle pagine) per le sezioni con molte
  righe. La pagina è `?pagina=N` e si riporta sempre tra 1 e l'ultima (`0`, `abc`
  o un array danno la prima). Testi in `account.pagination.*` (it, en). Serve il
  CSS `wi-row-table__foot` / `wi-pagination` della lib, dalla 2.1.2-alpha.24.
- Data di nascita (`birth_date`) sulla scheda `Contact`, modificabile in «Dati personali».
  Colonna nuova: chi aggiorna lancia `php forge update`; senza, il salvataggio dei dati
  personali del pannello fallisce.
- Cambio email dal pannello: chiede la password attuale e manda un link di conferma
  monouso (token `email_change`, 24 ore) alla nuova casella; fino al clic resta valida
  la vecchia. Il link apre una pagina con il bottone «Conferma la nuova email» (la GET non
  cambia niente, per i filtri antispam che aprono i link in arrivo) e il POST del bottone cambia
  l'email; funziona anche da un altro browser. Il token si consuma nella stessa transazione del
  cambio e una nuova password revoca i link di cambio email aperti. Chiavi `account.email.*`.
- `AuthValidator::completion()` accetta `$confirmation = false` per saltare il campo di
  conferma della password; il pannello lo usa per il cambio password.
- `Choice` e `ChoiceGroup`: un radio o un checkbox in un riquadro cliccabile,
  con titolo, testo e una colonna per il prezzo, e il `fieldset` che li
  raccoglie. Testi escapati, parti vuote stampate con `hidden` e ganci
  `data-choice-*` uguali nei temi Wonder (`.wi-choice` della lib) e Bootstrap
  (`form-check` in una `card`).
- `ChoiceGroup::variant('segmented'|'list')` e, su `Choice`, `icon()`, `icons()` con
  «+N» oltre il massimo e `panel()` visibile solo sulla scelta fatta: servono alla
  pagina unica del checkout. Ganci `data-choice-icons` e `data-choice-panel` nei due temi.
- `Wonder\View\WebFonts`: catalogo di 9 font web (Inter, Roboto, Open Sans, Lato,
  Montserrat, Poppins, DM Sans, Nunito, Work Sans) serviti da
  `resources/assets/font/web/` con licenza OFL; `css($chiave)` dà i `@font-face` e
  le variabili `--*-font-family` del sito, vuoto per una chiave sconosciuta.
- `Steps`: percorso a passi con link solo sui passi fatti, `aria-current="step"`
  sul passo in corso e passi da fare spenti; `.wi-steps` della lib nel tema
  Wonder, `breadcrumb` in Bootstrap. `class()`, `addClass()`, `attr()` e `id()`
  di tutti e tre passano da `RendersComponentAttributes`, come `Container` e `Modal`.
- `Button::hidden($fields)`: i campi nascosti che il form di `Button::post()`
  manda insieme al bottone, escapati, in entrambi i temi.
- `Wonder\App\LibVersion`: versione minima di `wonder-image/lib` dichiarata in
  un solo punto (`extra.wonder.lib` nel `composer.json` del framework). `php forge update` la confronta con la lib
  installata nel sito e, se è più vecchia, si ferma indicando il comando
  `npm install` da lanciare. Senza `node_modules` il controllo non blocca.
- `DataItem`: un dato di una scheda, con l'etichetta piccola sopra e il valore
  sotto, senza riquadro. Il valore si escapa (`html()` per markup fidato), dove
  manca compare un segnaposto e `action()` mette un'azione accanto all'etichetta.
- Credenziali PayPal (`live`, client ID e secret) e Nexi (`prod`, API key,
  alias e chiave MAC) nella cascata `Credentials::api()`, nella tabella
  `security` e nella pagina backend Credenziali.
- Supporto EAN-13 ed EAN-8 in `createBarcode()` (anche `ean-13` / `ean-8`),
  con calcolo o validazione della cifra di controllo tramite
  `Wonder\Support\Barcode\Ean`.
- La classe `Wonder\Plugin\Nexi\Nexi` accetta anche alias e chiave MAC di
  XPay classico e offre firma dell'avvio e verifica dell'esito, mantenendo
  compatibile il costruttore API-key esistente.
- `precision($cifre)`, `decimal($decimali)` e `integer()` sui campi dati
  numerici. `precision()` controlla le cifre totali SQL; `decimal()` e
  `integer()` sono alias coerenti con i campi form.
- `Field::richText()` sui campi dati: il testo scritto dall'editor si pulisce
  in scrittura con la whitelist di `Wonder\Support\Html\SafeHtml::clean()`
  (`p`, `br`, `strong`, `b`, `em`, `i`, `u`, `s`, `strike`, `del`, `a` senza
  attributi, salvo un `href` `http(s)`/`mailto`/`tel`; `script`, `style`,
  `iframe`, `svg`, `math`… tolti col contenuto; editor vuoto → `''`). Vale per
  `Model::create()`/`update()` (`RichTextFormatter`) e per `formToArray()`
  (`format['rich_text']`); il campo è `sanitize(false)` in scrittura e in
  lettura, e sul `FormField` non serve più `->prepare('sanitize', false)`.
- `integer()` e `suffix($testo)` sui campi numerici (`number()`, `price()`,
  `percentige()` e colonne dei repeater): `integer()` è `decimal(0)`,
  `suffix(' kg')` mette l'unità in coda al posto del simbolo (non per i
  prezzi).
- `maxLength($n)` su `text()`: l'input esce con `maxlength` in entrambi i temi.
- `error_reports` con `Wonder\App\Support\Errors\ErrorReporter`: i guasti
  tecnici ripetuti diventano una riga sola con un contatore, l'email parte alla
  prima occorrenza e riparte solo se il problema torna dopo essere stato segnato
  risolto. Gli indirizzi li passa il sito con `recipientsUsing()`. Pagina
  "Errori" in Set Up per `admin`. Sono errori per chi sviluppa: quello che
  riguarda chi usa il sito è una notifica, non un errore, e non passa di qui.
- `Wonder\Backend\Support\FlashAlert`: avviso in coda in sessione, letto da
  `alert()` in body-end. `code()` per i codici di `notifications.json`,
  `custom()` e `saved()` per i messaggi composti al momento (solo backend).
  Le Resource lo usano da sole: dopo store, update e delete il toast 650
  compare sulla pagina di arrivo, e le pagine-form mostrano il messaggio di
  `submitFormPage()` come toast (`FORM_MESSAGE` e il riquadro verde in pagina
  non ci sono più).
- Comando `php forge credentials` (alias `bitwarden:pull`) per scaricare o
  ripristinare nel `.env` locale le credenziali Bitwarden `dev-shared`;
  preserva gli override per progetto per default e supporta `--force` e
  `--refresh-token`.
- Renderer Wonder per `Elements\Components\Accordion`, con stato iniziale
  `expanded`, tre coppie di icone, descrizione semplice e preset tipografici
  configurabili per titolo e contenuto.
- `Wonder\View\ComponentNamespaceRegistry`: i moduli registrano un namespace di componenti (`prefix` → directory base).
- `View::component()` è ora module-aware: `View::component('<prefix>/<nome>')` risolve con catena di override `{ROOT}/custom/view/components/{prefix}/...` → modulo. I nomi senza prefisso registrato mantengono il comportamento legacy.
- Helper globali `props()` (default + validazione chiavi obbligatorie) e `slot()` (slot nominati nei componenti template), in `app/function/helper.php`.
- Comando forge `module:publish <slug> [--only=<path>] [--force]`: pubblica le view di un modulo negli override del sito (`custom/view/...`).
- `Swiper::slides()` per caroselli di HTML trusted e componenti renderizzabili,
  più ratio separati per immagini/thumb e classi aggiuntive sulle slide.
- `Wonder\Elements\Components\QuickCreateButton`: la creazione rapida
  staccata da un campo, come componente di layout di `formLayoutSchema()`
  (`make(Resource::class)->text()->fields()->layout()->label()->size()`).
  Apre lo stesso modal del "+" di `quickCreate(...)`, con gli stessi permessi
  (chi non può creare non vede niente) e lo stesso evento
  `wi:quick-create:created`, che parte con `input: null`, `family: 'button'`
  e la riga salvata in `item`. Sul tema Wonder non si disegna.
- Il detail di `wi:quick-create:created` porta anche `trigger`, l'elemento
  che ha aperto il modal.
- Voci personalizzate nel menu azioni della riga delle Resource:
  `TableColumn::actions()` e `action()` accettano un array (`label`, `href`
  con i segnaposto `{colonna}`, `target`, `filter.row`) e lo passano intero a
  `Field::actionButton()`, come le Table dirette. `label` può essere un array
  indicizzato dal valore della colonna con il nome della voce.
- `TableLayoutSchema::select(string $sql)`: colonne calcolate con alias nella
  lista di una Resource (`tabella`.* resta davanti). Gli alias si mostrano e si
  ordinano; restano fuori da ricerca, filtri e conteggi.
- `TableLayoutSchema::filterQuery($label, $key, $options, Closure $where)`:
  filtro con una condizione propria. La closure riceve solo i valori presenti
  fra le opzioni (figli degli alberi compresi) e restituisce l'SQL, che
  viaggia firmato come gli altri filtri. `Table::addFilter()` ha l'ottavo
  argomento `?Closure $where`.
- Ricerca annidata: un descrittore di relazione in `searchFields()` può
  contenerne altri in `relations`, così la ricerca scende di più tabelle
  (movimento → versione → articolo). Basta anche un descrittore con sole
  `relations`.
- Barra di salvataggio nel backend: un `<form data-wi-save-bar>` copia i
  bottoni di salvataggio in un'isola fissa in basso quando escono dallo
  schermo e chiede conferma prima di uscire con modifiche non salvate. JS e
  CSS stanno in `wonder-image/lib` dalla 2.1.2-alpha.19 (con il bottone Annulla); l'app mette gli
  attributi sui form delle Resource, dello scheduler, dell'account, della
  gestione utenti e del file di configurazione. Con una lib più vecchia gli
  attributi restano inerti.
- `AttributeString::render(array $attributes, array $reserved = [])`
  serializza gli attributi HTML: `true` senza valore, `false` e `null`
  omessi, valori escapati. `ResourceFormLayoutRenderer::render()` accetta
  l'opzione `attributes` per il tag `<form>` e
  `ResourceFormLayoutRenderer::hasSubmit($form, $name = 'upload')` dice se il
  layout contiene già un Submit con quel name.
- `user()` restituisce il campo `written`: `true` solo dopo l'insert o
  l'update della riga `user`.
- `Wonder\Http\Csrf`: token CSRF di sessione con `token()`, `field()` e
  `verify()`. Il campo `_csrf` esce da solo nei form resi da `Form`, in
  `Button::post()` e nella pagina form delle Resource; i layout backend e
  frontend scrivono `<meta name="wi-csrf">`. Nessuna richiesta viene ancora
  bloccata: la verifica resta a chi la chiama.
- `Modal::form($action, $method, $hidden)`: corpo e bottoni in un `<form>` con
  token CSRF e campi nascosti, in Bootstrap e nella `wi-modal` di
  `frontend()`. In fondo Annulla e poi Salva (`cancel()`, `submit()`,
  `components.buttons.save` nuova in tutte le lingue); `help()` mette un
  tooltip accanto al titolo. Una `Modal` con `form()` nel layout di una
  Resource lancia `LogicException`.
- Classi delle parti interne, aggiunte a quelle del tema: `Modal::dialogClass()`,
  `headerClass()`, `titleClass()`, `bodyClass()`, `footerClass()` e
  `Dropdown::toggleClass()`, `menuClass()`, `itemClass()` (concern
  `HasPartAttributes` / `RendersPartAttributes`).
- `Dropdown`: voci POST (`'method' => 'post'`) con token CSRF e conferma
  (`confirm`, `confirm_title`, `confirm_ok`, `confirm_variant`), `variant` per
  il colore della voce e `action()` per una voce `<button type="button">`
  senza href.
- `Image` (e `__i()`, `__ri()`, Swiper, Gallery): `data-wi-image-name` con il
  nome originale del file, che la lib scrive nel box quando l'immagine non si
  carica (#92). Nome vuoto: «Immagine non impostata» tradotta
  (`components.media.image_missing`). Si spegne con `missingLabel(false)` o
  `IMAGE_MISSING_LABEL=false`; nessun controllo sul disco.

### Changed
- **Escape della connessione al posto di `addslashes()` (`C-SQL-I5`).**
  `FilterCustom::condition($options, $value, ?callable $escape = null)`:
  `generate()` passa il `real_escape_string` della connessione del filtro;
  senza `$escape` resta `addslashes()`, come prima. `Popup::currentForPageKey()`
  costruisce la condizione con `Popup::pageKeyCondition()`, che lega i valori
  con `Query::bind()`. Per i valori normali l'SQL è identico; con
  `NO_BACKSLASH_ESCAPES` un apice non chiude più la stringa.
  Nelle LIKE di `column_type` multiple `%`, `_` e `\` del valore vengono
  escapati prima dell'escape della connessione: una barra (`\%`) non riattiva
  più il jolly. Anche `Popup::pageKeyCondition()` escapa `%`, `_` e `\` della
  chiave (e di `__all__`), che nel LIKE valgono come caratteri.
- **Deprecato il default `$REMEMBER = true` di `authenticateUserLogin()` e
  `authenticateUser()`.** Il default resta per retrocompatibilità, ma i
  chiamanti devono passare la scelta esplicita (ad esempio
  `RememberMe::requested($_POST)` o `false`). Tutti i chiamanti del core la
  passano già: login frontend (`AuthController`), login backend,
  `/oauth/login` e `authenticateUserLogin()` verso `authenticateUser()`.
  Stesso default, deprecato allo stesso modo, in
  `new LegacySessionLoginAdapter()` senza argomento
  (`class/Auth/Federated/Bridge/LegacySessionLoginAdapter.php:13`,
  `$rememberMe = true`) e in `verifyFederatedLogin()` senza l'opzione
  `remember_me` (`app/function/frontend/plugin/federated.php:154`,
  `remember_me ?? true`): un sito che usa il `verifyFederatedLogin()` legacy
  ricorda sempre l'utente, senza checkbox. I default non cambiano. Nel core
  `LegacySessionLoginAdapter` riceve sempre il valore esplicito e
  `verifyFederatedLogin()` non ha chiamanti.
- **list/table, editorjs e SSP: cambia il comportamento per chi chiama senza
  sessione backend.** `api.backend.list.table`, `api.backend.editorjs.file` e
  `.image` sono `guarded()`: senza login rimandano al Login e l'handler non
  parte. list/table accetta solo tabelle di `Table::$list` (altrimenti 422) e
  usa la chiave canonica del registro (minuscola), mentre
  `ListTableRequest::schema()` scarta gli schemi non registrati. La rotta
  lavora sempre sul database principale e non legge più area, autorità e
  database dal POST. `SSP::complex` usa la connessione `mysqli` del chiamante
  invece di aprire un PDO, escapa tabella e chiave primaria, lega i termini di
  ricerca (`%`, `_` e `\` letterali) e in caso di errore risponde solo
  `{"error":"Errore nel caricamento della tabella."}`, con il dettaglio nel
  log e, solo con `APP_DEBUG`, nella chiave `detail`. Gli upload di editorjs
  accettano un file alla volta in whitelist, con nome casuale; l'header `Dir`
  vale solo come sottocartella relativa. Un resize fallito risponde 928, non
  più 926 (che resta per la conversione di formato).
- `SSP::filter()`, `buildSearchWhere()` e `relationCondition()` richiedono
  l'array dei binding: non esiste più la ricerca con il termine nel letterale
  SQL.
- Firma della config delle tabelle backend legata al contesto:
  `ConfigCodec::encode()`/`decode()` accettano `$context` e
  `ConfigCodec::context($table, $schema, $field)` lo costruisce; Table firma
  ogni frammento per tabella canonica, schema e campo e `ListProvider::fetch()`
  lo verifica prima di qualsiasi query. Frammenti riusati con un'altra
  `config[table]` o un altro `custom[schema]` danno l'errore di firma invece
  di arrivare al database. Nessun cambio per i client: la config la genera
  solo il server; una pagina aperta prima dell'aggiornamento va ricaricata.
- `resizeImage()` usa Intervention Image (GD) al posto di Gumlet, che non era
  installato: stesse uscite small/medium/large, restituisce `bool`.
- La chiave `stats.contact_tables` dell'esito di `forge update` e di
  `/api/app/update/` si chiama `stats.external_references`: la migrazione del
  core rinomina solo `gst_external_references`. Il deploy dei siti stampa il
  JSON senza leggere la chiave; un client che la leggeva va aggiornato.
- `wonder.google-places`: tolto il parametro `apply_hours` (le pianificazioni
  che lo hanno ancora non danno errore, si ignora); decide il campo della sede.
  `GooglePlacesSync::run()` non riceve più `$applyHours`.
- Scheduler nel backend: attiva/sospendi, **Esegui ora** e registro sono nel
  menu a tre puntini di ogni riga; il nome apre la pagina della pianificazione
  con i dati di esecuzione, la DataTable **Esecuzioni** e in testata **Esegui
  ora** e **Modifica**. Esegui ora chiede conferma, attende l'esito e lo mostra
  in una finestra. Il comando cron è nella finestra **Cron sul server** della
  lista; il Riepilogo non ha più l'avvio manuale.
- Sitemap e Sedi e Google (`wonder.google-places`) sono sempre attive; senza
  `GCP_API_KEY` la sincronizzazione delle sedi viene saltata.
- Home del backend ridisegnata con sole classi Bootstrap. In alto ci sono il
  saluto e lo stato di scheduler ed errori aperti, ciascuno visibile solo a
  chi può aprire l'elenco della sua Resource. Seguono le sezioni senza sottomenu come accesso rapido e una
  card per sezione con i suoi link, divisi per gruppo. Tolte le schede
  "Update applicativo" e "Contatti".
- `Input::required(false)` toglie l'attributo `required` messo prima, invece
  di non fare niente. Il cellulare facoltativo del completamento non dà più
  errore se è compilato solo il prefisso.
- Contatti e account cliente persistente sono usciti dal core nel modulo
  `wonder-image/customer-account`: `Contact`, `ContactAddress`, le loro
  Resource, `ContactAccount`, `AccountAddresses`, `AccountBilling`, le pagine
  degli indirizzi e la migrazione delle tabelle `gst_*`. Un sito con il solo
  app non ha più tabelle, Resource né voci di menu dei contatti; il pannello
  account resta (panoramica, dati personali, email, password). Per tenerli si
  installa e abilita il modulo: tabelle (`contacts`, `contact_addresses`) e
  URL restano gli stessi. `ExternalReference`, `AccountAddressForm` e
  `AccountAddressValidation` restano nel core.
- Pannello account: la fatturazione sta in «Dati personali», come riga con il
  suo modal (`account-billing`, posta a `account.personal` con `form=billing`);
  non ha più la voce di menu né la pagina, e `/account/fatturazione/` resta solo
  in GET come redirect a «Dati personali». La sezione `billing` di `sections()`
  accende o spegne la riga. La Panoramica mostra l'anteprima di dati personali,
  fatturazione e indirizzi (`AccountOverview`), con il link a ogni pagina.
  `frontend.account.address-card` con `add` (`href`, `modal`, `label`) stampa la
  scheda per aggiungere un indirizzo. Nuovo stile di layout, menu, righe e schede
  (utility della lib al posto di `wi-side-layout`, `wi-data-row__action`,
  `wi-address-card__name`); il telefono della scheda è testo, senza link `tel:`.
  «Esci» nel menu non ha più `wi-input-submit`.
  **Aggiornamento.** Un sito che linkava `account.billing` funziona (redirect);
  un form che postava lì va spostato su `account.personal` con `form=billing`.
- `TableColumn::price()` scrive l'importo nella lingua del sito invece che
  sempre all'italiana: `1.234,50 €` in italiano, `€1,234.50` in inglese. Lo fa il
  nuovo `Wonder\Support\Money::format($importo, ?$lingua, $valuta = 'EUR')`,
  che legge la lingua da `LanguageContext` e usa `NumberFormatter` (con un
  ripiego senza `intl`: l'inglese all'inglese, il resto all'italiana). Per un
  sito italiano non cambia niente.
- Versione minima di `wonder-image/lib` alzata a `2.1.2-alpha.26` (`extra.wonder.lib` e
  `package.json`): ha `data-wi-place-names`, la ricerca per nome usata da
  `AddressExtension::withNameSearch()`.
- Versione minima di `wonder-image/lib` alzata a `2.1.2-alpha.25` (`extra.wonder.lib` e
  `package.json`): ha l'autocompletamento degli indirizzi con Google Places (`data-wi-place`,
  place ID e coordinate). Con una lib più vecchia `php forge update` chiede
  `npm install 'wonder-image@^2.1.2-alpha.25'`.
- Versione minima di `wonder-image/lib` alzata a `2.1.2-alpha.24` (`extra.wonder.lib` e
  `package.json`): ha il CSS e il JS del pannello account. Con una lib più vecchia
  `php forge update` si ferma e chiede `npm install 'wonder-image@^2.1.2-alpha.24'`.
- `AccountPanel::navigation(object $user)`, che restituiva `[]` e lasciava le voci ai
  moduli, diventa `navigation(object $user, string $active = '')`: dà le voci del core per
  chiave (`overview`, `personal`, `addresses`, `billing`) con `label`, `href`, `icon` e
  `active`, poi le fa ritoccare dai ganci `AccountExtension::navigation()`; le voci senza
  `href` o etichetta non escono. Una sottoclasse di `AccountPanel` che sovrascrive il
  vecchio `navigation(object $user): array` deve adottare la firma nuova e il ritorno
  per chiave (partendo da `parent::navigation($user, $active)`): con la firma vecchia PHP
  dà un errore fatale di dichiarazione incompatibile. Una sottoclasse della
  `EcommerceAccountPanel` dell'ecommerce, rimossa, dà un errore fatale per classe non
  trovata prima che il controllo `is_a` possa lanciare la sua `LogicException`.
- Gli URL del pannello account sono in italiano: `/account/dati-personali/`,
  `/account/fatturazione/`, `/account/indirizzi/…` al posto di `/account/personal-data/`,
  `/account/billing-address/` e `/account/shipping-addresses/…`. Le URL di login e
  registrazione (`/account/auth/…`) non cambiano. Non ci sono alias dei vecchi indirizzi.
- Il `Modal` del tema Wonder mandato già aperto dal server (classe `wi-show`) esce
  visibile e cliccabile: senza `no-interaction`, `aria-hidden` e `inert`. Serve al pannello
  per riaprire il modal con gli errori dopo un salvataggio non riuscito.
- `Themes\{Bootstrap,Wonder}\Media\Media` prendono `columnSpanClasses()`
  dai nuovi concern `Themes\{Bootstrap,Wonder}\Concerns\ColumnSpanClasses`,
  condivisi con `Code` e `Preview`.
- `PasswordReset::reset()` segna l'email come verificata: il token è arrivato a quella casella.
- `PasswordReset::issueForUser(..., revokeOpenTokens: false)` lascia validi i link già mandati.
- `Button::confirm($message, title:, ok:, variant:)`: la conferma usa
  `data-wi-confirm*` della lib al posto di `onsubmit="window.confirm(...)"`.
  La firma con il solo messaggio resta valida; sui bottoni e link non POST la
  conferma ora va sul tag invece di essere ignorata.
- Una `class` negli `attributes` di una voce del `Dropdown` entra nello stesso
  attributo `class` della voce invece di produrne un secondo.
- `AuthSession::csrfToken()` e `verify()` delegano a `Wonder\Http\Csrf`: stesso
  token e stessa chiave di sessione, nessun cambiamento per chi li usa.
- `TableColumn::price()`: nelle tabelle l'importo si legge all'italiana (`1.234,50 €`),
  allineato a destra con cifre tabulari, al posto di `1234.50€`. Lo zero resta `0,00 €`;
  un valore non numerico lascia la cella vuota.
- `php forge config` e `php forge skills` sincronizzano soltanto la raccolta
  `wonder-image/skills`: la skill esterna `pbakaus/impeccable` non viene più
  reinstallata. Nei siti dove è già presente, rimuovila una volta dalla root
  con `npx skills remove impeccable`; non viene disinstallata automaticamente.
- Numeri, prezzi e percentuali escono nel formato italiano senza
  configurazione: virgola decimale e niente migliaia per numero e percentuale,
  «1.299,90 €» per il prezzo. I default stanno negli Element, quindi valgono
  nei due temi, nelle colonne dei repeater e negli helper legacy; una config
  esplicita vince sempre, anche vuota (`groupSeparator('')`, `symbol('')`
  arrivano ad AutoNumeric come `''`).
- `Elements\Form\Components\InputPrice` estende `InputNumber` e non più
  `InputPercentige`: il prezzo non emette più `data-wi-percentige`.
- `ScheduleResource`: il timeout è `integer()` invece di `decimals(0)`.
- Il pulsante "Guida" è `btn-info btn-sm` in ogni pagina: prima nell'elenco e
  nell'header era un `btn-outline-secondary` a dimensione piena.
- `SocietyLocationResource` non è più `final`: un modulo che aggiunge dati alla
  sede la estende e, registrandosi con lo stesso percorso, prende il posto della
  pagina del core invece di affiancarne una seconda.
- Modal e script della creazione rapida stanno in
  `Wonder\Backend\Support\QuickCreateModal`, condivisi fra il "+" dei campi
  (`Themes\Bootstrap\Form\Field`) e `QuickCreateButton`; lo script si
  stampa una volta per pagina.
- `FilterCustom`: il valore predefinito `0` filtra come la stringa `'0'`;
  `true`, e un array su un filtro a scelta singola, non filtrano più (prima
  `= '1'` e `= 'Array'` con un warning). I filtri `multiple` con un valore solo
  escono fra parentesi come quelli con più valori.
- La pagina "Errori" (`ErrorReportResource`) sta in Dev → Log e diagnostica,
  sempre solo per `admin`: prima era in Set Up.
- Le classi di `Accordion`, `Modal`, `Container` del tema Wonder, `InfoCard` e
  `MetricCard` si leggono come quelle dei campi, con
  `Themes\Concerns\MergesClassAttribute`. Un booleano non è una classe: prima
  `true` poteva uscire come classe `1`. In `Accordion`, `Modal` e `Container`
  un numero passato da solo esce come classe, come già dentro un array: prima
  si perdeva.
- `header.php` del backend sottrae `var(--wi-save-bar-reserve, 0px)` dal
  `min-height` del contenitore della pagina, così le pagine corte non hanno
  scroll vuoto sopra la barra di salvataggio. Con una lib senza barra la
  variabile vale 0 e l'altezza resta com'era.
- Vincolo della lib a `^2.1.2-alpha.30` (`extra.wonder.lib` e `package.json`).
  Tolti i rimedi a wonder-image/lib#6, ora nella lib: la regola
  `.wi-checkbox-container` del CSS di `CheckGroup` Wonder (che esce solo con
  le pillole) e `.wi-input-container { position: relative; }` dell'anteprima
  del catalogo.
- `const API_TOKEN` nei `head.php` frontend e backend (e nell'anteprima del
  catalogo) è un token di pagina di `@system` (`ApiTokens::pageToken()`):
  `typ: page`, scadenza `API_PAGE_TOKEN_TTL` (1 ora, vedi Breaking) e legato al token di
  sistema corrente. Prima era il token di `@system`, riutilizzabile e senza
  scadenza. Le chiamate della lib non cambiano; `/api/app/update/`,
  `/api/task/scheduler/` e `/api/task/sitemap/` rifiutano il token di pagina
  (`Handler::run(..., allowPageToken: false)`).
- Token API: `jti` casuale (`bin2hex(random_bytes(16))`), prima era
  `uniqid()`. `exp` configurabile con `API_TOKEN_TTL` (default `0`, nessuna
  scadenza) e `API_TOKEN_REQUIRE_EXP` per rifiutare i token senza scadenza;
  `apiUser()` accetta `_api_token_ttl`, `regenerateApiToken()` ruota il token.
- `api_activity.token` salva l'impronta `sha256:…` del bearer: prima era il
  bearer in chiaro. `forge update` svuota le righe storiche
  (`ApiActivityTokenMigration`, statistica `api_activity_tokens`).

### Removed
- **Backend: blocco `$USER_FILTER` di `app/html/backend/list.php`
  (`C-SQL-I7`).** Costruiva `$QUERY_CUSTOM` concatenando `authority` e `area`
  senza escape; nessuno imposta più `$USER_FILTER` dal commit 817af162 (core,
  moduli menu/blog/gallery, siti verificati). Tolte anche le due righe che
  passavano `user_area`/`user_authority` all'endpoint: `Table` li legge con
  default vuoto, quindi il risultato non cambia. `app/function/backend/filter.php` è
  marcato `@deprecated` e resta per compatibilità.
- BREAKING: l'alias deprecato `Wonder\Auth\Frontend\SafeRedirect`. Si usa
  `Wonder\Http\SafeRedirect`, stessa API (`fromRequest()`, `fromBase64()`).
  Si aggiorna insieme a `wonder-image/ecommerce` con A-I2, che passa a
  `Wonder\Http\SafeRedirect`: le versioni precedenti usano ancora l'alias in
  `src/Support/SafeRedirect.php` e con questo core si rompono.
- Traduzioni orfane di `account.json` (it/en): `account.navigation.billing`,
  `account.navigation.payment_methods` e il blocco `account.payment_methods.*`.
  Il core non le legge (il menu usa solo `overview` e `personal`), l'ecommerce
  usa le sue `ecommerce.account.payment_methods.*`. `account.seo.description`
  non cita più i metodi di pagamento.
- `Wonder\App\Models\Contacts\{Contact,ContactAddress}`,
  `Wonder\App\Resources\Contacts\*`, `Wonder\Auth\Frontend\{ContactAccount,
  AccountAddresses,AccountBilling}`, le view degli indirizzi e le traduzioni
  `contacts.json`: ora sono in `wonder-image/customer-account`
  (`Wonder\Plugin\CustomerAccount\`).
- `AccountAddressModal` e i componenti `frontend.account.address-form` e
  `frontend.account.password-form`: il pannello compone modal e form con
  `AccountModal` e `AccountAddressForm::layout()`.

### Fixed
- **Log reCAPTCHA: IP del client dietro proxy (review `N4`).** `RecaptchaGuard`
  registra `remote_addr` via `Wonder\Http\ClientIp::resolve()`, come throttle e
  `auth_log`. L'`ip_address` del consenso (`app/function/user/user.php:209`) resta
  su `REMOTE_ADDR`: è fuori dal perimetro auth.
- **Password: costo bcrypt per versione PHP (review `RR1`).** Senza argon2id,
  `hashPassword()` e `passwordNeedsRehash()` usano il costo bcrypt di default
  della versione in esecuzione (10 prima della 8.4, 12 dalla 8.4), scelto con
  `PHP_VERSION_ID` in `passwordHashSettings()`. L'hash fittizio
  (`dummyPasswordHashFor()`) ha lo stesso costo: su PHP 8.2/8.3 non viene più
  rigenerato a ogni richiesta e non costa il doppio di una password errata.
  Senza argon2id nessuna funzione legge `PASSWORD_ARGON2ID` (review `B1`):
  prima l'hash fittizio dava un errore fatale e un 500 al posto del 925.
- **Login: tempo di risposta uniforme (review `R1`).** Con utente inesistente
  o registrato senza password, `authenticateUser()` esegue comunque una
  verifica su `dummyPasswordHash()`, con lo stesso algoritmo e lo stesso costo
  di `hashPassword()`: il tempo non distingue più questi casi da una password
  errata. Resta distinguibile, fino al blocco 2d (coda mail), l'account solo
  Google: dopo il 925 `mailFederatedHint` invia la mail via SMTP sincrono.
- **Ricordami: IP da `ClientIp`.** `RememberMe::set()` e la rotazione in
  `RememberMe::tryLogin()` scrivono in `auth_remember.ip` il valore di
  `ClientIp::resolve()` invece di `REMOTE_ADDR`.
- **Audit impersonificazione: IP da `ClientIp` (review `N9`).**
  `Impersonation::audit()` scrive in `auth_impersonation_audits.ip` il valore di
  `ClientIp::resolve()`: dietro proxy con `APP_TRUST_PROXY` è l'IP del client,
  non quello del proxy.
- **Geolocalizzazione: IP da `ClientIp` (review `M4`).** `IPInfo` senza IP
  esplicito usa `ClientIp::resolve()`: `X-Forwarded-For` e `Client-IP` non
  contano più se `APP_TRUST_PROXY` è spento.
- `ConfigCodec::context()` codifica con `JSON_THROW_ON_ERROR`: un contesto non
  codificabile lancia invece di diventare `''`.
- `SSP::complex`: i binding di `whereAll` valgono anche per le query dei dati
  e del conteggio filtrato, non solo per il totale.
- Upload di editorjs con `Dir` vuoto: i file vanno in `upload/` e non più in
  una cartella sorella `uploadXXXX`.
- `$SOCIETY` / `infoSociety()` espongono `google` e `reviews` della sede, come
  da documentazione: prima si perdevano perché si copiavano solo i valori
  scalari (wonder-image/app#108).
- La colonna azioni delle tabelle backend rispetta `hiddenDevice()` come le
  altre colonne.
- `AttributeString::parse()` legge i valori tra apici singoli: prima uscivano
  vuoti, perché il gruppo dei doppi apici valeva `''` invece di `null`
  (`data-wi-restriction='{"country":"it"}'` passato a `->attribute()` non
  arrivava all'input).
- `Query::Conditions()` con una condizione stringa toglie solo il `WHERE`
  iniziale (maiuscolo o minuscolo, anche con spazi davanti), non più ogni
  `WHERE`/`where` della stringa: una condizione con sottoquery passata a
  `Table::query()` restava SQL non valido (errore 1064). Con `$where = true` il
  `WHERE` si aggiunge anche se la sottoquery ne contiene uno, e una condizione
  stringa non apre più una connessione al database.
- Anteprime Wonder del catalogo: l'anteprima chiama `setAos()` e `setUpPage()`
  al caricamento come il layout frontend (prima solo il Bootstrap chiamava
  `setUpPage()`, quindi AutoNumeric, date e tendine restavano markup statico).
  Gli esempi dell'immagine usano `w-100`, che esiste in entrambi i temi, al
  posto di `img-fluid` e `rounded`, classi Bootstrap che la lib Wonder non ha.
- Anteprime Wonder del catalogo dei componenti: il body non esce più dalla
  larghezza dell'iframe (`head.css` gli dà `width: 100%` e il padding
  dell'anteprima lo allargava di 2rem) e le label dei campi stanno nel loro
  contenitore invece di sovrapporsi nell'angolo della pagina.
- `Form` del tema Wonder: `columns()` e `gap()` escono come utility della lib
  (`d-grid col-N gap-N`, con `col-t-*`/`gap-t-*` e `col-p-*`/`gap-p-*` per
  tablet e telefono, dal nuovo concern `Themes\Wonder\Concerns\ResponsiveGridClasses`
  condiviso col Container) invece delle classi Bootstrap `row-col-*` e `g-*`
  che la lib non definisce: prima nel frontend i campi si impilavano senza spazio.
- `noFloating()` nel tema Bootstrap stampa la label `form-label` sopra l'input;
  prima il campo restava senza nome visibile.
- `ResourceRouteRegistrar` non registra la route di export per le Resource senza
  `list` o senza tabella, e `NavigationOnlyResource` disabilita `list`: le pagine
  non-CRUD (Riepilogo dello scheduler, Upload di massa, Download SQL, catalogo dei
  componenti) non hanno più un `/{resource}/` aperto a tutti accanto alla route
  esplicita `admin` né un export che finiva in un'eccezione.
- Cambio password dall'account backend: dopo il salvataggio revoca i token
  "ricordami" dell'utente su ogni dispositivo e rigenera l'id di sessione, come
  il pannello frontend. Prima un dispositivo rimasto collegato con "ricordami"
  continuava ad accedere anche con la nuova password.
- Recupero e impostazione password del backend usano token monouso
  (`OneTimeToken`) al posto del parametro `?r=`: il link del recupero passa
  per `PasswordReset` (30 minuti, valido una sola volta), quello di
  `/backend/account/password-set/` vuole un token di scopo `password_set`. I
  link `?r=` già inviati non sono più validi: va chiesto un nuovo recupero.
- `PasswordReset::reset()` revoca i token "ricordami" dell'utente dopo il
  cambio, sia nel frontend sia nel backend.
- `AuthProfile::userValues('signup-request', …)` ora conserva gli id dei
  documenti legali (`{documento}_id`): prima li scartava, la registrazione dei
  consensi falliva e la registrazione terminava con l'errore generico 900.
- Verifica email di registrazione: se il token appartiene a un account che non
  può accedere all'area (ad esempio un admin del backend che ha usato la stessa
  email) il link non viene più consumato e compare un messaggio dedicato invece
  di "non valido". `confirmUserVerificationToken()` accetta una guardia
  opzionale `$guard(int $userId): bool` valutata prima di consumare il token.
- `user()`: quando la registrazione dei consensi fallisce (errore 900) l'eccezione
  viene scritta in `storage/logs/error.log` (servizio `user`, azione
  `register_consents`, con `user_id` e superficie) invece di restare nascosta.
- Registrazione con l'email di un account già verificato: prima mostrava
  "Controlla la tua email" senza inviare nulla e registrava di nuovo i consensi
  sull'account esistente. Ora `user()` riusa l'account solo se l'email non è
  ancora verificata (`userCanReuseForEmailVerification()`), altrimenti dà 906,
  e il form di registrazione resta sulla pagina con l'errore
  `auth.validation.errors.email_exists`.
- Un contenitore (`Card`, `Container`, `Form`, `ButtonGroup`) reso con un tema
  esplicito rende i figli con quel tema, non con quello attivo: una `Card`
  resa con `render('bootstrap')` fuori dal backend cercava i renderer Wonder
  dei figli.
- Campi data (`dateInput()`, `dateRange()`, `textDate()`, `textDatetime()`):
  un value con le barre si legge `d/m/Y`, il formato che scrive il picker.
  Prima `05/10/2026` usciva `10/05/2026`, con giorno e mese scambiati.
- `Modal` resa da sola nel tema Bootstrap (`render()`, tipica con `form()`):
  `columns()` e `columnSpan()` ora danno le colonne ai campi, come nel layout
  di una Resource. Prima ogni campo usciva senza colonna, a tutta larghezza.
- Dropdown Wonder: il separatore esce come `wi-dropdown-divider` (prima
  `dropdown-divider`, la classe Bootstrap); `text()` e `header()` escono come
  `wi-dropdown-item wi-dropdown-text`, senza l'aspetto di una voce cliccabile.
- I renderer dei temi non lasciano più uno spazio in fondo agli attributi
  scritti da un array (`class="btn btn-primary "` → `class="btn btn-primary"`).
- L'icona «cartella» della colonna `empty` (`isEmpty()`) non stampa più gli
  avvisi sul `$TEXT` nullo nella lista caricata via API: `Field` passa a
  `isEmpty()` i testi della tabella.
- Le colonne derivate da `Field::key(...)->number()` rispettano ora
  `decimals()`: il default resta `DECIMAL(10,2)`, `decimals(3)` genera
  `DECIMAL(10,3)` e una scala zero genera `DECIMAL(10)` senza virgola vuota.
- `sendMail()` non invia piu a Brevo un `replyTo` vuoto; anche il client Brevo
  ignora direttamente indirizzi vuoti e il log salva `NULL` quando il
  reply-to non esiste.
- Le route protette distinguono ora autenticazione e autorizzazione: sessioni
  assenti/scadute e account non utilizzabili continuano a tornare al Login con
  gli alert previsti, mentre un utente backend/API valido ma privo di una delle
  authority richieste riceve HTTP 403 (view errore condivisa in HTML, payload
  JSON nelle API). Il dispatcher interrompe il flusso prima dell'handler.
- I date picker del tema Wonder non interpolano piu valori del form in script
  inline: i renderer espongono attributi `data-wi-*` escapati e demandano
  inizializzazione e validazione a `wonder-image/lib`; anche `TextList` escapa
  il nome scritto in `data-wi-name`.
- Image (`__ri()`), Swiper e Gallery conservano gli URL immagine assoluti
  off-site per cover, anteprime, slide, thumbnail e lightbox, senza generare
  percorsi responsive inesistenti sul server remoto.
- Repeater: le righe aggiunte avviano AutoNumeric (`setAutonumeric()` dopo
  `setInput()`), così prezzi e quantità si formattano come nelle righe già in
  pagina anche con le lib che non lo fanno dentro `setInput()`.
- Repeater: il comando di gruppo legge i numeri scritti all'italiana con un
  solo separatore ripetuto ("1.234.567" è 1234567, non 1,234).
- Repeater: l'ultima riga, che il cestino svuota invece di togliere, svuota
  anche i campi di AutoNumeric. Prima il numero vecchio tornava all'invio, su
  una riga senza più niente intorno.
- Menu azioni della riga: al primo disegno (pre-render) usciva vuoto, perché
  le azioni arrivano come `true` e `true != 'false'` in PHP 8 è falso.
- Menu azioni della riga, voci ad array: `href` e `target` escono escapati;
  un'etichetta senza il valore della riga o un `filter.row` su una colonna
  assente nascondono la voce senza warning; `delete` ad array rispetta il
  permesso di eliminare la riga.
- `FilterCustom`: valori escapati nell'SQL (prima un GET costruito apposta
  entrava così com'era), `%` e `_` escapati nelle LIKE, `OR` dei filtri
  `multiple` fra parentesi, nome della colonna fra backtick escapati. Una
  checkbox o un albero senza spunte non producono più SQL rotto e non alzano
  il contatore dei filtri. Gli input nascosti (`redirect`, `id`, date) escono
  escapati: prima `?redirect=` iniettava HTML nella pagina.
- Ricerca nelle tabelle collegate: il `local_key` si controlla sulla tabella
  del padre, e un `foreign_key` mancante vale `id` anche nella query (prima la
  validazione lo dava per `id` ma `SSP` scartava il descrittore).
- SafeHtml: con libxml prima della 2.14 un `<embed>` si portava via il testo
  che lo seguiva, a volte il resto del documento, perché libxml lo apre come
  contenitore; dalla 2.14 è vuoto come in HTML5 e il testo restava. Ora
  `embed` si toglie come gli altri tag non ammessi e il testo resta con ogni
  libxml. Il tag e i suoi attributi non uscivano con nessuna versione.
- SafeHtml: con libxml 2.14 e successive un `xmp`, `textarea`, `title` o
  `plaintext` lasciato aperto si leggeva come testo fino a fine input,
  compresa la chiusura del documento in cui SafeHtml avvolge l'input: il
  risultato finiva con `&lt;/body&gt;&lt;/html&gt;`. Un href lasciato aperto
  la metteva invece nel link (`<a href="https://x.it` con la 2.9, senza
  virgolette con ogni versione). Ora quel documento resta aperto e lo chiude
  libxml: la chiusura non finisce più nel risultato.
- SafeHtml: un `</body>` o `</html>` senza la sua apertura chiudeva il body
  del documento in cui SafeHtml avvolge l'input, e quello che seguiva spariva
  con ogni libxml (`<p>a</p></body><p>b</p>` dava `<p>a</p>`). Ora queste
  chiusure si tolgono prima della lettura, anche maiuscole, con spazi o con
  attributi, e il testo che segue resta. Si tolgono anche dentro `textarea`,
  `title` e `script`, dove libxml 2.9 le legge come chiusure e la 2.14 e
  successive come testo: il risultato è lo stesso con tutte e due.
- SafeHtml: con libxml 2.14 e successive il contenuto di `xmp` e `plaintext`
  si leggeva come testo semplice: le lettere accentate e i `<` sciolti, che
  SafeHtml passa a libxml come entità, uscivano escapati due volte
  (`perch&amp;#233;`, `3 &amp;lt; 5`), le entità scritte nel testo pure
  (`a &amp;amp; b`) e i tag dentro restavano testo. Ora questi due tag si
  leggono come gli altri con ogni libxml, come già con la 2.9 (`perché`,
  `3 &lt; 5`, `a &amp; b`): i tag ammessi restano e quelli da togliere
  spariscono con il loro contenuto.
- SafeHtml: con libxml prima della 2.14 i tag vuoti di HTML5 che quella
  versione non conosce — `wbr`, `source`, `track`, `embed`, `bgsound` e
  `keygen` — si aprivano come contenitori: quello che li seguiva ci finiva
  dentro e i tag attorno si chiudevano in un altro punto
  (`<p>a<wbr>b<div>c</div>d</p>` dava `<p>abcd</p>` invece di `<p>ab</p>cd`).
  Ora si leggono vuoti con ogni libxml, come in HTML5 e nel browser: quello che
  li segue non è loro. Per `embed` la correzione copre così anche la struttura,
  non solo il testo che si perdeva. Il tag e i suoi attributi non escono con
  nessuna versione, mentre dove è testo (dentro `xmp`, in un valore di
  attributo) o dove il nome è un altro (`<wbrx>`) il risultato resta com'era.
- SafeHtml: con libxml 2.9 i `\r` restavano nel testo, anche dentro `xmp`,
  `listing` e `textarea`, e negli href (`<p>a\r\nb</p>` usciva così com'era),
  mentre la 2.15 li normalizza come HTML5 (`<p>a\nb</p>`). Ora `\r\n` e `\r`
  diventano `\n` prima della lettura, e il risultato è lo stesso con ogni
  libxml. Come in HTML5 si fa prima di togliere i caratteri di controllo e le
  chiusure di `body` e `html`: un `\r` e un `\n` separati da uno di questi
  restano due a capo (con la 2.15 diventavano uno). Anche un `&#13;`, che
  mette un `\r` nel documento, esce come `\n`: prima usciva `\r` con ogni
  libxml, e con la 2.15 una seconda pulizia lo cambiava.
- SafeHtml: quanto testo si portavano via `script`, `style`, `iframe`,
  `noembed` e `noframes` cambiava con la libxml. In HTML5 il loro contenuto
  arriva fino alla loro chiusura, anche oltre la chiusura di un tag che li
  contiene, e `<p>a<iframe>b</p>c` dà `<p>a</p>`. libxml legge come testo
  semplice solo `script` e `style`, e prima della 2.14 li finiva al primo `</`
  seguito da una lettera (`<div>a<script>b</div>c</script>d` dava `acd` invece
  di `ad`); `iframe`, `noembed` e `noframes` sono testo semplice dalla 2.14 e
  tag normali prima (`<p>a<iframe>b</p>c` dava `<p>a</p>c`). Ora il risultato è
  quello di HTML5 con ogni libxml: `script` e `style` si leggono passando
  `HTML_PARSE_RECOVER` a libxml, gli altri tre facendoli leggere con il nome di
  `style`. Il nome della lettura è condiviso e le chiusure si accoppiano per
  nome: `</style>`, `</iframe>`, `</noembed>` e `</noframes>` finiscono il
  contenuto di qualunque di questi quattro tag, anche prima di quanto direbbe
  HTML5, e quello che segue resta come testo escapato.
- SafeHtml: con libxml prima della 2.14, anche con `HTML_PARSE_RECOVER`, uno
  `script` o uno `style` finiva prima della sua chiusura in due casi: un `</`
  seguito da un nome all'inizio del contenuto (`<script></b)`) e la chiusura
  di un tag il cui nome comincia con il suo (`</scriptx`, `</style-a`). Lo
  stesso per `iframe`, `noembed` e `noframes`, che si leggono con il nome di
  `style`. Se il tag di quella chiusura era aperto, il contenuto usciva come
  testo (`<p>a<script></p>b</script>c</p><p>d</p>` dava `<p>a</p>bc<p>d</p>`);
  se non lo era, la chiusura si leggeva fino al primo `>`, quello del
  `</script>` vero compreso, e lo script si portava via tutto il testo che
  seguiva (`<script></b)</script><p>d</p>` dava una stringa vuota). Ora il
  contenuto arriva fino alla sua chiusura con ogni libxml, come in HTML5.
- Filtro per data del backend (`FilterDate`): chiuse una SQL injection e una
  XSS dai parametri GET. Le date entrano nella query solo se sono gg/mm/aaaa
  valide (con un solo estremo valido la condizione diventa `>=` o `<=`, senza
  nessuno restano gli ultimi N giorni), mese e anno solo come interi, la
  colonna passa da `Query::escapeIdentifier()` e campi data, titolo e link dei
  mesi sono escapati. La firma `ConfigCodec` di `Table::buildConfig()` non
  proteggeva: firmava l'SQL già iniettato. I link dei mesi usano il mese
  numerico (`?month=9&year=2026`), quindi i vecchi link con il nome inglese
  (`?month=September`) mostrano il periodo predefinito; i filtri personalizzati
  a più valori restano nei link invece di diventare "Array".
- `filterDate()` globale di `app/function/backend/filter.php` (legacy): chiuse
  la stessa SQL injection e la stessa XSS di `FilterDate`, riusandone
  `buildCondition()` e i validatori `parseDate()` e `parseInteger()`, ora
  pubblici. Nella query entrano solo date gg/mm/aaaa valide (con un solo
  estremo valido la condizione diventa `>=` o `<=`), `wi-month` e `wi-year`
  solo come interi, e link dei mesi, campi data e campi nascosti del form
  (chiavi comprese) sono escapati. I link dei mesi usano il mese numerico
  (`?wi-month=9&wi-year=2026`), quindi i vecchi link con il nome inglese
  (`?wi-month=September&wi-year=2026`) mostrano l'anno intero; i parametri a
  più valori restano nei link invece di diventare "Array". La funzione è
  deprecata: per le pagine nuove si usa `Table::filterDate()`.
- Filtri legacy delle liste in `app/function/backend/filter.php`
  (`filterCustom()`, `createFilterCustom()`, `filterLimit()`, `filterSearch()`
  e `createSearchBar()`): chiuse le SQL injection e le XSS dai parametri GET.
  Il valore di un filtro personalizzato entra nella query solo se è fra le
  opzioni del form, sempre con l'escape della connessione; un filtro senza
  opzioni note ha solo l'escape. Le colonne di filtri, ricerca e ordinamento
  passano da `Query::escapeIdentifier()`, `$FILTER_DIRECTION` vale solo `ASC`
  o `DESC` (altrimenti `ASC`) e `limit` solo i valori dei bottoni (altrimenti
  gli ultimi 25). Campi nascosti del form (chiavi comprese), barra e titolo
  della ricerca sono escapati, e un apice nella ricerca non mostra più la barra
  di `addslashes()`. I valori fuori dalle opzioni, anche da un link scritto a
  mano, valgono come assenti; i parametri a più valori restano nel form invece
  di diventare "Array".
- Filtri legacy delle liste: `filterLimit()` con `?limit=all` e senza ricerca
  chiamava `filterCustom()`, che non restituisce query e righe selezionate, e
  le lasciava vuote con due warning. Ora passa da `filter()`: la query tiene
  conto dei filtri personalizzati e il titolo resta «Tutti gli …». Non danno
  più TypeError né warning i filtri senza opzioni (per esempio un `select` con
  la sola `column`, che nel form esce senza opzioni), i filtri senza `type`
  (nella query restano filtri a valore singolo, nel form non hanno un campo e
  non ripetono più quello del filtro precedente), le sorgenti `function` che
  non restituiscono un array (valgono come una funzione senza opzioni) e la
  categoria con la sezione ma senza sottocategoria.
- Filtri legacy delle liste: `filterCustomOptions()` rinumerava con
  `array_merge()` le chiavi intere delle sorgenti `function`. Con gli id dei
  record come chiavi (`[12 => 'Acme', 40 => 'Beta']`) il form mostrava le
  etichette giuste ma filtrava su `'0'`, `'1'`, …, cioè sulle righe sbagliate,
  e un link con l'id vero (`?marca=12`) valeva come assente. Ora le chiavi
  restano quelle della funzione, come per la sorgente `database`. Le chiavi
  stringa e gli elenchi (`['Rosso', 'Blu']`) danno le stesse opzioni di prima,
  e una chiave `''` della funzione prende ancora il posto di «Tutti» nei radio.
  I link salvati prima portano la posizione: ora è letta come id e, se non è
  fra le opzioni, vale come assente.
- Filtri legacy delle liste: con i filtri sezione e sottocategoria ma senza
  categoria, lo script di `createFilterCustom()` usava `disabledCheckbox()` e
  `filterCategory()` senza definirle e legava le sottocategorie a una categoria
  assente: le nascondeva o disattivava tutte, su una sottocategoria spuntata
  dal GET il clic dava un ReferenceError e un clic su una sezione non le
  ricalcolava. Ora, senza il filtro categoria, le sottocategorie seguono solo
  le sezioni, come le categorie. Con sezione, categoria e sottocategoria lo
  script resta identico.
- Filtri legacy delle liste: la cascata sezione, categoria e sottocategoria di
  `createFilterCustom()` non agiva. Quando `check()` è passato a `CheckGroup`,
  i campi hanno perso le classi `section`, `category` e `subcategory` con cui
  lo script li cerca, così categorie e sottocategorie restavano visibili e
  attive qualunque sezione fosse spuntata. Ora `check()` riceve la classe del
  filtro quando la catena può funzionare: la sezione e il filtro che la segue
  escono come campi da spuntare (`checkbox`, oppure `radio` con almeno 5
  opzioni o con `search`), e la sottocategoria partecipa solo se anche la
  categoria, quando c'è, è da spuntare. Come nel 2023, categorie e
  sottocategorie restano nascoste finché non si spunta una sezione, e «Tutti»
  vale come nessuna sezione. Una voce spuntata fuori dalle sezioni scelte resta
  visibile in rosso e un clic la toglie. I filtri `select` e `tree` e i `radio`
  brevi senza ricerca non partecipano. Lo script non cambia.
- `CheckGroup` (le opzioni di `radio()` e `checkbox()` e il `check()` legacy):
  una classe data al campo usciva come secondo attributo `class`, dopo quello
  del tema, e il browser la ignorava. Vale per il quarto argomento di
  `check()`, per `->attribute('class="…"')` di `FormField` e per `class()` e
  `addClass()`. Ora la classe arriva a ogni input, figli compresi, dopo la
  classe del tema (`form-check-input`, `btn-check` o `wi-checkbox`), in un solo
  attributo `class`. Vale per entrambi i temi, anche per `CheckTree` nel tema
  Wonder, e passa da `Themes\Concerns\MergesClassAttribute`.
- Renderer dei campi, in entrambi i temi: un attributo che il renderer scrive
  già sul tag usciva una seconda volta fra quelli del campo, e il browser
  teneva il primo. Capitava a `data-wi-check` in quasi tutti i campi, `check()`
  legacy compreso, alla classe data con `class()`, `addClass()` o
  `attr('class', …)`, che spariva dietro quella del tema, e secondo il campo a
  `placeholder`, `readonly`, `checked`, `style`, `disabled` e `autocomplete`.
  Ora ogni attributo esce una volta: la classe del campo segue quella del tema
  nello stesso `class`, e per le altre chiavi resta il valore del tema, lo
  stesso che il browser usava già. I renderer passano da
  `AbstractFieldRenderer::fieldClass()` e `fieldAttributes()`; `Repeater` e
  `SortableInput` (deprecato) restano com'erano.
- `addClass()` dopo `attr('class', 'a')` dava un TypeError, perché
  `pushAttr()` trovava una stringa dove si aspettava un array. Ora la stringa
  diventa il primo valore (`class="a b"`); `false` e `''` valgono come
  assenti.
- `DynamicCheck` (backend): `data-wi-attribute`, gli attributi che la lib
  copia sulle caselle che crea, non era escapato. Il `data-wi-check="true"`
  che ogni campo ha ne chiudeva il valore alle prime virgolette: alla lib
  arrivava `data-wi-check=` e il resto finiva sul campo di ricerca come
  markup rotto. Ora il valore è escapato e senza `data-wi-check`, che la lib
  scrive già: gli attributi del campo arrivano alle caselle.
- `selectDate()` del frontend legacy (`SelectDate` nel tema Wonder) si
  fermava con un Error dalla v2.2.2: chiamava `jsString()`, privato in
  `DatePicker`. Ora è protetto e il campo si rende.
- Form con `formLayoutSchema()`: un `Submit` con name `upload` nel layout
  prende il posto del Salva del footer, prima i Salva erano due. Un Submit
  dentro Modal, QuickCreate, Accordion chiusi o componenti con `visibleWhen()`
  o `hiddenWhen()` non conta, e il Salva del footer resta.
- Repeater: svuotare l'ultima riga emette `change` con `bubbles` su ogni campo
  e passa i campi non checkbox o radio da `wiRepeaterSetFieldValue()`, quindi
  AutoNumeric si svuota dalla sua API e `check()` vede lo svuotamento. Prima
  si assegnava solo `value`, senza eventi.
- `SecurityResource`: i segreti (`klaviyo_api_key`, `brevo_api_key`,
  `mail_password`, `google_oauth_client_secret`, `apple_oauth_private_key`)
  hanno `autocomplete="new-password"`: prima il browser poteva inserirci la
  password di login salvata.
- API: `Endpoint` confronta il bearer con `api_users.token` (`hash_equals`,
  in `Api\Token\BearerVerifier`): prima bastava un JWT firmato con il `sub`
  giusto, quindi rigenerare o revocare un token non aveva effetto.
- API: un token scaduto, con firma errata o non JWT risponde `401`, prima era
  un `500` con il messaggio della libreria JWT.
- PDF dei documenti legali (`generateLegalDocumentPdf()`): tipo documento,
  «Versione», «Pubblicato» e «Pagina» seguono il `language_code` del
  documento (chiavi `components.legal.pdf.version`, `.published`, `.page`,
  tradotte in it, en, de, fr, es) anche quando il PDF nasce da una richiesta
  o da un task in un'altra lingua: prima un documento tedesco usciva con le
  etichette italiane. La lingua passa per il nuovo
  `LanguageContext::withLang()`, che ripristina lingua e traduzioni correnti
  in un `finally`; una lingua assente o non registrata ricade sulla lingua di
  default (#77).
- Open redirect nel login backend (#87): `infoPage()` validava il
  `?redirect=` base64 solo decodificandolo, e dopo il login
  `header("Location: $PAGE->redirect")` poteva portare su un host esterno. Ora
  `Wonder\Http\SafeRedirect::fromBase64()` scarta host esterni, `//host`,
  `/\host`, `javascript:` e CR/LF (`$PAGE->redirect` e `redirectBase64` restano
  vuoti, quindi il login ripiega su `backend.home`), mentre path interni e URL
  dello stesso host di `APP_URL`, anche con `www.`, restano quelli di oggi.
  `SafeRedirect` passa da `Wonder\Auth\Frontend` a `Wonder\Http`; il vecchio
  nome resta come alias deprecato.
