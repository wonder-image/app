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
- `FilterCustom`: il valore predefinito `0` filtra come la stringa `'0'`;
  `true`, e un array su un filtro a scelta singola, non filtrano più (prima
  `= '1'` e `= 'Array'` con un warning). I filtri `multiple` con un valore solo
  escono fra parentesi come quelli con più valori.

### Fixed
- Image (`__ri()`), Swiper e Gallery conservano gli URL immagine assoluti
  off-site per cover, anteprime, slide, thumbnail e lightbox, senza generare
  percorsi responsive inesistenti sul server remoto.
- Repeater: le righe aggiunte avviano AutoNumeric (`setAutonumeric()` dopo
  `setInput()`), così prezzi e quantità si formattano come nelle righe già in
  pagina anche con le lib che non lo fanno dentro `setInput()`.
- Repeater: il comando di gruppo legge i numeri scritti all'italiana con un
  solo separatore ripetuto ("1.234.567" è 1234567, non 1,234).
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
