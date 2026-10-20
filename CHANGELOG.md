# Changelog

## Unreleased

### Added
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
  (`GCP_API_KEY`). Di default segnala solo le differenze, con `apply_hours`
  sostituisce gli orari diversi; lo stato non si applica mai. Nuove tabelle
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
  `typ: page`, scadenza `API_PAGE_TOKEN_TTL` (12 ore) e legato al token di
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
- `Wonder\App\Models\Contacts\{Contact,ContactAddress}`,
  `Wonder\App\Resources\Contacts\*`, `Wonder\Auth\Frontend\{ContactAccount,
  AccountAddresses,AccountBilling}`, le view degli indirizzi e le traduzioni
  `contacts.json`: ora sono in `wonder-image/customer-account`
  (`Wonder\Plugin\CustomerAccount\`).
- `AccountAddressModal` e i componenti `frontend.account.address-form` e
  `frontend.account.password-form`: il pannello compone modal e form con
  `AccountModal` e `AccountAddressForm::layout()`.

### Fixed
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
