# Changelog

## Unreleased

### Added
- `TableColumn::money()`: importo all'italiana (`1.234,50 €`) allineato a destra con
  cifre tabulari, al posto di `->text()->formatter(...)` con lo span scritto a mano.
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

### Changed
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

### Fixed
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
