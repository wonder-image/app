# Bottone «File» — piano di implementazione

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** In tutte le liste Resource, «Esporta» e «Importa» diventano un solo bottone «File». Il bottone apre una finestra con le schede Esporta e Importa, e la finestra ricorda l'ultima scelta.

**Architecture:** Due aggiunte pubbliche nell'app: `Form::action()` e un componente `Tabs`, solo Bootstrap. Una classe nuova, `ResourceFileModal`, costruisce bottone e finestra da dati semplici. `ResourceTableRenderer` raccoglie quei dati (rotte, permessi, tracciati) e passa a `Table::buttonDataHtml()` il bottone e la finestra. L'export si scarica da una rotta nuova, senza formato nel percorso (`?format=`), servita dallo stesso handler. `ResourceDownloadController` salva in sessione l'ultima scelta.

**Tech Stack:** PHP 8.2, i componenti `Wonder\Elements`, il renderer Bootstrap del backend, Bootstrap 5 tabs (nessun JS nuovo), test PHP con `tests/harness.php` (`check()`, `summary()`).

**Spec:** `docs/superpowers/specs/2026-11-05-bottone-file-design.md`

## Global Constraints

- Branch app: `feat/bottone-file`, già creato da origin/main. Il ramo del gestionale si chiamerà `feat/bottone-file` anche lì.
- Etichetta del bottone: sempre «File». Icona: `bi bi-arrow-down-up`. La finestra si apre sempre sulla scheda Esporta.
- Niente XML, niente «solo righe filtrate», nessun JS nuovo e nessun rilascio della lib.
- L'export abituale costa al massimo 3 clic: File, Scarica, con la scelta già pronta.
- Se l'utente non può importare, la scheda Importa non c'è proprio (non disabilitata). Con una sola scheda, la barra delle schede non c'è.
- Testo d'aiuto sotto il formato, quando un formato non accetta i tracciati: «I tracciati valgono per {formati di downloadLayouts()}». Esempio: «I tracciati valgono per Excel e CSV».
- Prima voce del tracciato in Esporta: «Tutte le colonne», con valore vuoto. In Importa: «Nessuno, abbino le colonne io», con valore vuoto.
- Gli indirizzi che esistono oggi, `…/export/{format}/` e `?layout=`, continuano a funzionare.
- Tutti gli input passano da `FormField`. I commenti nel codice sono brevi, in italiano e solo dove servono.
- I test dell'app sono gitignored: si aggiungono con `git add -f`.
- Non si modificano `vendor/`, `node_modules/` o `assets/lib/wonder-image/dist/`. Da questo piano non si lanciano `php forge config|credentials|provision|db:init|build`.
- Niente merge e niente push su main senza chiedere: le PR sì.

## Review Focus

1. **Scaricare non deve lasciare lo spinner acceso.** Il form GET di Esporta non ha `onsubmit="loadingSpinner()"`: la pagina non cambia, e lo spinner girerebbe all'infinito. Test nel Task 1.
2. **L'ultimo tracciato non esiste più, o il formato non è più concesso.** Si torna al primo formato e a «Tutte le colonne», senza errori. Test nel Task 4.
3. **Un `?format=` inventato o vuoto sulla rotta nuova** dà lo stesso errore di un formato sconosciuto oggi, e non salva la scelta in sessione. Test nel Task 3.
4. **Senza sessione attiva** (CLI, API) la scelta non si salva e la lettura dà `null`, senza warning. Test nel Task 3.
5. **Nomi di tracciati con HTML** (`<b>`) vanno escapati, sia nelle opzioni sia nel titolo della scheda (`download(label:)`). Test nel Task 4.

---

### Task 1: `Form::action()`

**Files:**
- Modify: `class/Elements/Form/Form.php` (aggiunge `action()`)
- Modify: `class/Themes/Bootstrap/Form/Form.php:22-23`
- Modify: `class/Themes/Wonder/Form/Form.php` (il tag `<form>`, stessa regola)
- Modify: `class/Backend/Support/ResourceFormLayoutRenderer.php:124-170`: un ramo `instanceof Form` in `renderComponents()`, così un form dentro una scheda o una finestra si rende con `self::render()`
- Modify: `docs/components/form/form.php` (un esempio con `action()`), `docs/app/concetti/form/README.md` (sezione «Indirizzo e metodo»)
- Test: `tests/Themes/FormActionTest.php` (nuovo)

**Interfaces:**
- Produces: `Form::action(string $url, string $method = 'post'): static`. Scrive `schema['action'] = $url` e `schema['method'] = 'get'|'post'`. Un metodo diverso da `get` vale `post`.
- Produces: `ResourceFormLayoutRenderer::formOptions(Form $form): array`, pubblico statico. Dà le opzioni di `render()` per quel form:
  - con GET: `method GET`, `enctype application/x-www-form-urlencoded`, `onsubmit ''`, nessun footer;
  - con POST: multipart, `onsubmit loadingSpinner()` e `footer Csrf::fieldFor('post')`;
  - senza `action()`: `[]`, cioè il comportamento di oggi.

- [ ] **Step 1: Scrivi i test che falliscono** in `tests/Themes/FormActionTest.php`:
  - `senza action() il form resta action="" POST multipart`: il render Bootstrap contiene `action=""`, `method="post"` e `enctype="multipart/form-data"`;
  - `action GET: niente enctype, niente CSRF, niente spinner`: `(new Form)->action('/x/?a=1&b=2', 'get')` contiene `action="/x/?a=1&amp;b=2"` e `method="get"`, e non contiene `enctype`, `_csrf` o `loadingSpinner`;
  - `action POST: multipart e campo CSRF`: con una sessione attiva (`session_id('t'); @session_start();`), il form contiene `name="_csrf"` e `enctype="multipart/form-data"`;
  - `un form dentro una Modal usa la sua action`: `Modal::make('M')->components([(new Form)->action('/dl/', 'get')->components([...])])->render('bootstrap')` contiene `<form` con `action="/dl/"` e `method="GET"` (accettare entrambe le maiuscole: confronto case-insensitive);
  - `metodo sconosciuto vale post`: con `action('/x/', 'put')` il form ha `method="post"`.
- [ ] **Step 2: Lancia il test e verifica che fallisca.** Run: `php tests/Themes/FormActionTest.php`. Expected: FAIL, perché `action()` non esiste.
- [ ] **Step 3: Implementa** `Form::action()`, poi `formOptions()`. Il tema Bootstrap e il tema Wonder leggono `schema['action']` e `schema['method']` per il tag `<form>`. Il ramo `Form` in `renderComponents()` chiama `self::render($component, ['id' => …] + self::formOptions($component))`. L'id viene da `schema['id']` se c'è, altrimenti da `uniqid('wi-form-')`.
- [ ] **Step 4: Lancia i test.** Run: `php tests/Themes/FormActionTest.php && php tests/Themes/ModalFormTest.php && php tests/Themes/FormGridAndLabelTest.php && php tests/Docs/CatalogRenderTest.php`. Expected: tutti PASS.
- [ ] **Step 5: Commit.** `git add -f tests/Themes/FormActionTest.php`, poi aggiungi i file toccati. Messaggio: `Form::action(): indirizzo e metodo del form, GET senza CSRF né spinner`.

### Task 2: componente `Tabs`

**Files:**
- Create: `class/Elements/Components/Tabs.php`
- Create: `class/Themes/Bootstrap/Components/Tabs.php`
- Modify: `class/Backend/Support/ResourceFormLayoutRenderer.php`: un ramo `instanceof Tabs` in `renderComponents()`. Il pannello rende i suoi componenti con la griglia, come fa `renderAccordion()`.
- Create: `docs/components/components/tabs.php`, la card con `group('choice')` e `note('bootstrap', 'Rende nav-tabs e tab-content di Bootstrap.')`. Il tema Wonder non c'è: lo calcola `ThemeSupport`.
- Modify: `docs/app/concetti/componenti/README.md`, una sezione `Tabs`.
- Test: `tests/Themes/TabsTest.php` (nuovo)

**Interfaces:**
- Produces:
  - `Tabs::make(string $id): self`. `$id` è il prefisso degli id delle schede e dei pannelli; lo si ripulisce a `[a-z0-9-]`;
  - `Tabs::tab(string $key, string $label, array $components): self`, nell'ordine di chiamata;
  - `Tabs::active(string $key): self`: la scheda aperta; senza chiamata è la prima;
  - `$tabs->tabs(): array<int, array{key: string, label: string, components: array}>`.
- Il markup Bootstrap:
  - `<ul class="nav nav-tabs" role="tablist">`, con un `<button class="nav-link" data-bs-toggle="tab" data-bs-target="#{id}-{key}" role="tab" aria-controls="{id}-{key}" aria-selected>` per ogni scheda;
  - poi `<div class="tab-content">`, con un `<div class="tab-pane fade" id="{id}-{key}" role="tabpanel">` per ogni pannello;
  - la scheda attiva ha `active` sul bottone e `show active` sul pannello.
  - **Con una sola scheda**: niente `<ul>`, solo il contenuto del pannello, senza il `tab-pane`.

- [ ] **Step 1: Scrivi i test che falliscono** in `tests/Themes/TabsTest.php`:
  - `due schede: barra e due pannelli, la prima attiva`: un `<ul class="nav nav-tabs"`, due `data-bs-toggle="tab"`, `id="f-export"` con `show active`, e `id="f-import"` senza `active`;
  - `active() apre la scheda chiesta`;
  - `una sola scheda: niente barra né tab-pane`: non contiene `nav-tabs` né `tab-pane`, ma contiene il contenuto;
  - `l'etichetta è escapata`: `tab('a', '<b>X</b>', [])` contiene `&lt;b&gt;X&lt;/b&gt;`;
  - `un pannello con campi ha la griglia`: un `FormField::key('x')->text()->columnSpan(6)` dentro un pannello dà `col-6`, o la classe di colonna che usa `renderAccordion()`; verifica la classe esatta nell'output di Accordion e usa quella.
- [ ] **Step 2: Lancia il test e verifica che fallisca.** Run: `php tests/Themes/TabsTest.php`. Expected: FAIL, classe non trovata.
- [ ] **Step 3: Implementa** l'Element (sul modello di `Steps`: `Renderer` e `schemaPush('tabs', …)`), il renderer Bootstrap, il ramo nel `ResourceFormLayoutRenderer` e la card. Il renderer Bootstrap passa da un metodo pubblico statico di `ResourceFormLayoutRenderer`, `renderTabs(Tabs $tabs, array $parentColumns = []): string`, così dentro e fuori da un layout il markup è uno solo, come succede con `renderModalBody()`.
- [ ] **Step 4: Lancia i test.** Run: `php tests/Themes/TabsTest.php && php tests/Docs/CatalogRenderTest.php && php tests/Themes/AccordionGridTest.php`. Expected: tutti PASS.
- [ ] **Step 5: Commit.** `git add -f tests/Themes/TabsTest.php` più i file. Messaggio: `Componente Tabs (Bootstrap): schede con pannelli, una sola scheda senza barra`.

### Task 3: rotta senza formato e ultima scelta in sessione

**Files:**
- Modify: `class/App/ResourceRouteRegistrar.php:116-122`. Accanto a `/export/{format}/` si aggiunge `Route::get('/export/', …export.php…)->name('export.file')`, con gli stessi meta e permessi.
- Modify: `app/http/backend/resource/export.php`. Il formato viene da `ResourceDownloadController::formatFrom($routeParameters, $_GET)`.
- Modify: `class/Backend/Support/ResourceDownloadController.php`
- Test: `tests/Backend/Support/ResourceDownloadChoiceTest.php` (nuovo)

**Interfaces:**
- Produces:
  - `ResourceDownloadController::formatFrom(array $routeParameters, array $query): string`. Usa il parametro di rotta se non è vuoto, altrimenti `$query['format']`. Il risultato è in minuscolo e passa solo se corrisponde a `^[a-z]+$`, altrimenti `''`;
  - `ResourceDownloadController::lastChoice(string $slug): ?array{format: string, layout: ?int}`, letto da `$_SESSION['wi_resource_export'][$slug]`. Dà `null` senza sessione attiva o senza valore;
  - `ResourceDownloadController::remember(string $slug, string $format, ?int $layoutId): void`, pubblico per i test. Senza sessione attiva non fa niente.
- `handle()` chiama `remember()` dopo i due controlli (export abilitato, formato concesso) e prima del gestore o di `dispatch()`. Salva il tracciato *chiesto*, anche quando quel formato lo ignora.
- export.php con un formato `''` lancia la `RuntimeException` di oggi («Metadata export resource backend mancanti.»).

- [ ] **Step 1: Scrivi i test che falliscono**:
  - `formatFrom: il percorso vince sulla query`: `formatFrom(['format' => 'csv'], ['format' => 'xlsx']) === 'csv'`;
  - `formatFrom: senza percorso legge ?format=`: `formatFrom([], ['format' => 'XLSX']) === 'xlsx'`;
  - `formatFrom: valori strani danno vuoto`: con `'x/../y'`, con `['a']` (un array) e senza chiave dà `''`;
  - `senza sessione niente scelta`: con `session_status() !== PHP_SESSION_ACTIVE`, `remember('products', 'csv', 3)` non dà errori e `lastChoice('products') === null`;
  - `con sessione la scelta si rilegge`: dopo `@session_start()`, `remember('products', 'csv', 3)` dà `lastChoice('products') === ['format' => 'csv', 'layout' => 3]`, e `remember('products', 'xlsx', null)` dà `layout => null`;
  - `una slug diversa non vede la scelta`: `lastChoice('orders') === null`.
- [ ] **Step 2: Lancia il test e verifica che fallisca.** Run: `php tests/Backend/Support/ResourceDownloadChoiceTest.php`. Expected: FAIL, metodo non definito.
- [ ] **Step 3: Implementa** i tre metodi, la chiamata in `handle()`, la rotta e export.php.
- [ ] **Step 4: Lancia i test.** Run: `php tests/Backend/Support/ResourceDownloadChoiceTest.php && php tests/Backend/Support/CsvFormulaSafeTest.php`. Expected: PASS. Poi `php -l` su export.php e sul registrar.
- [ ] **Step 5: Commit.** `git add -f` sul test, poi aggiungi i file toccati. Messaggio: `Export: rotta con ?format= e ultima scelta della lista in sessione`.

### Task 4: `ResourceFileModal` e collegamento nella lista

**Files:**
- Create: `class/Backend/Support/ResourceFileModal.php`
- Modify: `class/Backend/Support/ResourceTableRenderer.php:137-228`. `applyButtonData()` usa `ResourceFileModal`. `exportDropdown()` e `importButton()` si tolgono; dai loro controlli nascono `exportData(): ?array` e `importData(): ?array`.
- Modify: docs dev `docs/app/concetti/tracciati.md`, `docs/app/concetti/importazione.md` e `docs/app/concetti/tabelle/tablecolumn.md` (o la pagina dove è descritto `download()`). Descrivono:
  - il bottone «File» e le sue schede;
  - che `download(label:)` dà il titolo della scheda;
  - l'ultima scelta in sessione;
  - la rotta `export.file`.
- Modify: `CHANGELOG.md`, sotto `## Unreleased`, voce «Cambiato».
- Test: `tests/Backend/Support/ResourceFileModalTest.php` (nuovo)

**Interfaces:**
- Consumes:
  - `Form::action()` dal Task 1;
  - `Tabs::make()`, `tab()` e `active()` dal Task 2;
  - `ResourceDownloadController::lastChoice()` e la rotta `backend.resource.{slug}.export.file` dal Task 3.
- Produces: `ResourceFileModal::build(string $slug, ?array $export, ?array $import, ?array $last = null): ?array{button: Button, modal: Modal}`. Con `$export` e `$import` entrambi `null` dà `null`. Le forme degli argomenti:
  - `$export = ['action' => string (url di export.file), 'label' => string, 'formats' => array<string,string>, 'profiles' => array<int,string>, 'layout_formats' => list<string>|null]`;
  - `$import = ['action' => string (ImportResource::url()), 'layout' => string, 'profiles' => array<int,string>, 'default' => ?int, 'sample' => string]`.
- Produces: `ResourceFileModal::preselect(array $formats, array $profiles, ?array $last): array{format: string, layout: ?int}`.
- Il markup:
  - id della finestra: `wi-file-`, seguito dalla slug ripulita a `[a-z0-9-]`;
  - il bottone è `Button::make('File')->variant('secondary')->size('sm')->icon('bi bi-arrow-down-up', 'start')->opensModal($id)`;
  - la finestra è un `Modal::make('File')`, senza `form()` e senza footer.
- Scheda Esporta (`key 'export'`, titolo `$export['label']` o «Esporta»). Contiene un `Form->action($export['action'], 'get')` con:
  - `layout`: un select `['' => 'Tutte le colonne'] + profiles`. Compare solo se `profiles` non è vuoto e almeno un formato accetta i tracciati;
  - `format`: radio se i formati sono al massimo 4, altrimenti select. Il valore è quello di `preselect()`;
  - il testo d'aiuto «I tracciati valgono per …» sotto `format`, solo se `layout_formats` non è `null` e non copre tutti i formati. Le etichette vengono da `formats` e si uniscono con «, » e « e »;
  - il bottone `Button::make('Scarica')->type('submit')->variant('primary')`.
- Scheda Importa (`key 'import'`, titolo «Importa»). Contiene un `Form->action($import['action'], 'post')` con:
  - i campi nascosti `layout` e `action=upload`;
  - `import_layout_id`: un select `['' => 'Nessuno, abbino le colonne io'] + profiles`, con valore `default`; solo se `profiles` non è vuoto;
  - `file`: `FormField::key('file')->file('spreadsheet')->label('File .xlsx o .csv')`;
  - un link «Scarica il modello» a `sample`, fatto con `Button` `variant('link')`;
  - il bottone «Continua», di tipo submit.
- Regole di `preselect()`:
  - se `$last['format']` è tra i formati usa quello, altrimenti il primo;
  - se `$last['layout']` è tra le chiavi di `profiles` usa quello, altrimenti `null`.

- [ ] **Step 1: Scrivi i test che falliscono** in `tests/Backend/Support/ResourceFileModalTest.php`:
  - `export e import: due schede`: il render della finestra contiene `nav-tabs`, `id="wi-file-products-export"` attivo e `wi-file-products-import`. Il bottone contiene `File`, `bi-arrow-down-up` e `data-bs-target="#wi-file-products"`, o l'attributo che stampa `opensModal()`: verificalo in `ModalTest`;
  - `solo export: una scheda, niente barra, niente upload`: non contiene `nav-tabs`, `name="file"` né `Continua`;
  - `solo import: una scheda senza Scarica`;
  - `né export né import: null`;
  - `preselect: ultima scelta valida`: `preselect(['xlsx' => 'Excel', 'csv' => 'CSV'], [3 => 'Meta'], ['format' => 'csv', 'layout' => 3]) === ['format' => 'csv', 'layout' => 3]`;
  - `preselect: tracciato sparito e formato tolto tornano ai valori di base`: con `['format' => 'xml', 'layout' => 9]` dà `['format' => 'xlsx', 'layout' => null]`;
  - `il form di Esporta è GET verso export.file, senza spinner`: contiene `method="GET"` (case-insensitive) e l'action passata, e la parte del form Esporta non contiene `loadingSpinner`;
  - `il testo d'aiuto compare se un formato non accetta i tracciati`: formati xlsx, csv e meta, `layout_formats ['xlsx', 'csv']`. Il risultato contiene `I tracciati valgono per Excel e CSV`;
  - `niente select tracciato senza tracciati salvati`: con `profiles []` non contiene `name="layout"`. Fai attenzione: il nascosto `layout` dell'import ha lo stesso nome, quindi in questo test passa `$import = null`;
  - `nomi con HTML escapati`: un profilo `<b>X</b>` e una label `<i>E</i>` danno solo forme escapate;
  - `Importa: POST con CSRF e action=upload`: con una sessione attiva contiene `name="_csrf"`, `value="upload"` e `name="import_layout_id"`.
- [ ] **Step 2: Lancia il test e verifica che fallisca.** Run: `php tests/Backend/Support/ResourceFileModalTest.php`. Expected: FAIL, classe non trovata.
- [ ] **Step 3: Implementa `ResourceFileModal`**, poi collega `ResourceTableRenderer`:
  - `exportData()`: i controlli di oggi su `download` e i formati. `action` viene da `__r('backend.resource.'.$slug.'.export.file')`, dentro un try e con `null` se la rotta manca. Poi `Profile::options()` e i formati di `ResourceDownloadController::layouts()`;
  - `importData()`: i controlli di oggi di `importButton()`. Poi `ImportResource::url()`, `Profile::options($key)`, `Profile::defaultId($key)` e `ImportResource::sampleUrl($key)`;
  - `applyButtonData()`: `Table::buttonDataHtml($button->render('bootstrap').$modal->render('bootstrap'))`.
  - Gli import non più usati (`Dropdown`, `ButtonGroup`) si tolgono se nessun altro li usa nel file.
- [ ] **Step 4: Lancia i test.** Run: `php tests/Backend/Support/ResourceFileModalTest.php && php tests/Backend/Support/ResourceTableColumnsTest.php && php tests/Backend/Support/ResourceTableSelectTest.php && php tests/Themes/ModalTest.php && php tests/Docs/CatalogRenderTest.php`. Expected: PASS. Poi `php -l` sui file toccati e `composer dump-autoload`.
- [ ] **Step 5: Aggiorna le docs dev e il CHANGELOG**, come elencato in **Files**.
- [ ] **Step 6: Commit.** `git add -f` sul test, poi i file e le docs. Messaggio: `Liste Resource: bottone «File» con schede Esporta e Importa al posto di Esporta/Importa`.
- [ ] **Step 7: PR dell'app.** Fai push del ramo `feat/bottone-file` e apri la PR verso main. Poi chiedi prima di fare merge.

### Task 5: gestionale

**Files:**
- Modify: `gestionale/src/Resources/Catalog/ProductModelResource.php`, circa le righe 1390–1400: il blocco `if (Gestionale::feature('import_catalog') && static::allows(...)) { $layout->buttonCustom(... 'Importa' ...) }` diventa `$layout->importLayout(CatalogLayout::KEY);`, senza `if`.
  - Il controllo sulla funzionalità e sul permesso lo fa già `CatalogLayout::permits()`: `feature('import_catalog') && ProductModelResource::allows(..., 'update')`.
  - Togli gli import diventati inutili (`ImportResource`, `Button`), se nessun altro li usa nel file. Il commento «Dev → File → Tracciati file» resta.
- Modify: `gestionale/docs/user/catalogo-prodotti.md`, intorno alle righe 587 e 638.
  - «Esporta» e «Importa» diventano il pulsante **File**, con le due schede.
  - L'export veloce in tre passi: **File**, scelta già pronta, **Scarica**.
  - Il formato e il tracciato proposti sono gli ultimi usati.
  - Catalogo Meta ignora i tracciati.
- Modify: `gestionale/CHANGELOG.md`, sotto `## 0.1.0 — non rilasciata`.
- Test: un test esistente del gestionale che costruisce `tableLayoutSchema()`, se c'è (cerca `tableLayoutSchema` in `gestionale/tests`). Se c'è, verifica che lo schema abbia `import.layout === CatalogLayout::KEY` e che `buttonsCustom` non contenga «Importa». Se non c'è, crea `tests/ProductModelImportButtonTest.php` con quelle due verifiche.

**Interfaces:**
- Consumes: il `TableLayoutSchema::importLayout(?string $layout, ?string $label = null)` di oggi e il bottone «File» del Task 4, che arriva con l'app aggiornata.

- [ ] **Step 1: Scrivi o estendi il test** come indicato sopra.
- [ ] **Step 2: Lancialo e verifica che fallisca**: oggi `import` è `null`.
- [ ] **Step 3: Applica la modifica** a `ProductModelResource`.
- [ ] **Step 4: Rilancia il test e verifica che passi.** Poi `php -l` sul file.
- [ ] **Step 5: Aggiorna la guida utente e il CHANGELOG.**
- [ ] **Step 6: Commit sul ramo `feat/bottone-file` del gestionale, push e PR.** Messaggio: `Catalogo: import nel bottone «File» con importLayout()`. Poi chiedi prima di fare merge.

### Task 6: verifica sul sito (dopo i merge approvati)

- [ ] **Step 1:** Nel sito: `composer update 'wonder-image/*' && php forge update --local`.
- [ ] **Step 2:** In https://ecommerce.test › Prodotti, con l'utente admin locale (l'utente fa il login da sé), verifica:
  - il bottone «File» al posto di Esporta/Importa;
  - la scheda Esporta: Excel, CSV e Catalogo Meta, i tracciati Disponibilità e Facebook, e il testo d'aiuto;
  - «Scarica» scarica il file e non lascia lo spinner acceso;
  - riaprendo la finestra, formato e tracciato sono quelli dell'ultima volta;
  - la scheda Importa porta all'abbinamento delle colonne;
  - «Scarica il modello» funziona;
  - il vecchio indirizzo `…/export/xlsx/?layout=<id>` scarica ancora.
- [ ] **Step 3:** Su una lista senza import (per esempio Dev › File › Tracciati file, se ha l'export, oppure un'altra Resource con `download()`), verifica la sola scheda, senza barra.
