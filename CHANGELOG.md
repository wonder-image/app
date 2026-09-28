# Changelog

## Unreleased

### Added
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

### Changed
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

### Fixed
- Image (`__ri()`), Swiper e Gallery conservano gli URL immagine assoluti
  off-site per cover, anteprime, slide, thumbnail e lightbox, senza generare
  percorsi responsive inesistenti sul server remoto.
- Repeater: le righe aggiunte avviano AutoNumeric (`setAutonumeric()` dopo
  `setInput()`), così prezzi e quantità si formattano come nelle righe già in
  pagina anche con le lib che non lo fanno dentro `setInput()`.
- Repeater: il comando di gruppo legge i numeri scritti all'italiana con un
  solo separatore ripetuto ("1.234.567" è 1234567, non 1,234).
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
  script resta identico. I campi dei filtri non hanno ancora le classi
  `section`, `category` e `subcategory` con cui lo script li cerca: finché
  mancano, nel browser la cascata non agisce.
