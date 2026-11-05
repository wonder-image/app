# Tracciati per import ed export — design

**Data:** 2026-11-05
**Repo:** app (framework). Cambiano anche i moduli gestionale (catalogo, anagrafiche, ordini, feed) e customer-account (export contatti). Ecommerce resta invariato.
**Tipo:** architetturale
**Issue:** nessuna. Nasce dalla richiesta di usare i tracciati soprattutto per l'export (feed Google Merchant e Meta, file per i programmi dei clienti), per qualsiasi tabella o insieme di Model.

## Contesto

### Cosa c'è oggi

- **Motore di import del core** (`Wonder\App\Import\*`, `docs/app/concetti/importazione.md`).
  - Un `Layout` di codice dichiara `columns()`, `validate()`, `apply()`, `undo()` e `permits($user)`. I Layout si registrano in config `import.layouts`; `Layouts::CORE` è vuoto.
  - `Column` contiene key, header, aliases, required e field. Non sa leggere un valore da una riga e non sa trasformarlo.
  - La pagina Set-up → Tracciati file (`ImportLayoutResource`, tabelle `import_layouts` e `import_layout_columns`) salva per ogni Layout un profilo: quali colonne, con quale intestazione e in quale ordine. Lo legge `Profile::for($layout, ?$id)`.
- **Export delle liste Resource del core.** `TableLayoutSchema::download()` / `downloadColumns(['label', 'value' => campo|callable])` / `downloadUsing()`, servito da `ResourceDownloadController`. Rispetta query e `RowVisibility` e scrive CSV e XLSX con `SpreadsheetText`.
- **Gestionale.**
  - `Support/Catalog/ProductExport`: colonne del catalogo (`COLUMNS` più `attr:<id>`) e `rows()` puro, una riga per Product. `csv()` e `xlsx()` accettano già `?array $columns`.
  - `Support/Catalog/Exports/ProductExports`: formati xlsx, csv, meta, google, con `body()` scritto a mano per ciascuno.
  - `GoogleMerchant` (RSS 2.0 `g:`) e `MetaCatalog` (CSV), su `ProductFeed`, che gestisce `eligible`, `money`, `gtin` e la valuta.
  - `ProductFeeds` e `ProductFeedsTask`: i file `shared/feeds/catalogo-meta.csv` e `shared/feeds/google-merchant.xml` vengono rigenerati ogni 6 ore.
  - `Support/Export/{Delimited,Download,XmlFeed}`: writer generici, ma dentro il modulo.
  - `CatalogLayout` (KEY `catalog`) riusa le colonne di `ProductExport` per l'import: è il round trip export→import.
  - `ContactExports` filtra i formati di customer-account `ContactExport`, che usa `downloadColumns`.
  - Gli ordini (`gst_orders`, `gst_order_items`) non hanno export.
  - Feature dell'area «Esportazioni»: `export_products`, `export_contacts`, `export_meta`, `export_google`. Sono previste `export_tiktok` e `export_amazon`.
- **Ecommerce.** Non ha feed propri. Serve `/shared/catalogo.json` (`CatalogFeed`), e la valuta è duplicata: `catalog.currency` qui, `exports.currency` nel gestionale.

### Limiti

1. **Il tracciato salvato non serve a niente in export.** `Profile::for()` non viene chiamato da nessuna parte nel gestionale: `ProductExports::body()` chiama `csv()` e `xlsx()` senza colonne. Chi configura un tracciato non ne vede l'effetto.
2. **Il tracciato è legato a un Layout di import.** Una tabella senza Layout di import non può avere un tracciato. Il Layout stesso è solo import.
3. **Ci sono tre formati di colonna che non si parlano.** `Import\Column` ha key e alias ma nessun valore. `downloadColumns` ha label e valore ma nessuna key né alias. Le colonne del catalogo sono costruite inline in `ProductExport`. Le conversioni (Sì/No, `' | '`, `' › '`, «Illimitata») sono scritte a mano in `CatalogCells` e in `ProductExport`.
4. **I feed sono codice fisso.** Campi, titoli e `condition = new` sono scritti nel modulo. Un campo in più per Google (`google_product_category`, `custom_label_0`) o un tracciato per il gestionale di un cliente richiede una PR.
5. **Non c'è un export automatico generico.** Solo i due feed del catalogo vengono rigenerati, con un task dedicato. Non esistono file a URL, invii per email o export incrementali per anagrafiche e ordini.
6. **Ci sono writer duplicati.** Il core ha CSV e XLSX in `ResourceDownloadController` e `Sample`, il gestionale in `Support/Export`.

### Vincoli

- **Qualsiasi tabella.** Un tracciato deve poter partire da qualsiasi Model o Resource, e dalle tabelle collegate per chiave esterna, senza che un modulo scriva codice. Il codice di modulo serve solo per la logica di dominio (prezzo IVA inclusa, varianti, disponibilità).
- **Nessun impatto su chi non lo usa.** Un sito senza tracciati si comporta come oggi. I feed esistenti mantengono URL e contenuto.
- **Sicurezza.** Un export automatico gira senza utente e quindi senza `RowVisibility`. Un file a URL pubblico rende pubblici i suoi dati.
- **Rilascio a fasi.** La scadenza del primo negozio (2026-10-20) è passata, quindi ogni fase deve dare valore da sola.

## Decisioni

1. **Quattro ruoli distinti.**
   - **Sorgente** (`Source`): da dove vengono i dati. Dichiara i campi e restituisce le righe. Può anche essere importabile.
   - **Destinazione** (`Target`): il formato del file. Può essere generica (CSV, XLSX, JSON, con intestazioni libere) oppure a schema fisso (Google Merchant, Meta, con campi obbligatori).
   - **Tracciato**: sorgente + destinazione + righe di mappatura. È il record salvato dall'admin.
   - **Consegna**: download manuale, file pubblicato, invio per email.

   Così un solo concetto di tracciato copre import, export e feed, e ogni parte si estende da sola.

2. **Una sola `Column` nel core, condivisa da import, export e `downloadColumns`.**
   - `Wonder\App\Dataset\Column` (il nuovo namespace è descritto al punto 3): `key`, `header`, `aliases`, `required`, `field`, più:
     - `value`: nome del campo della riga, percorso di relazione (`brand.name`) o callable `fn(array $row): mixed`;
     - `type`: `text`, `number` (`decimals`), `money`, `boolean`, `date` / `datetime`, `list` (separatore), `path` (separatore `›`), `url`, `enum` (mappa valore → etichetta).
   - Il `type` porta nel core le conversioni che oggi stanno in `CatalogCells`, `yes()`, `' | '` e `' › '`. Ogni tipo sa **formattare** un valore per l'export e **leggerlo** per l'import, quindi il round trip export→import diventa generico.
   - `Import\Column` resta come sottoclasse o alias deprecato, con lo stesso costruttore. `CatalogLayout` e i Layout dei moduli continuano a funzionare.
   - `TableLayoutSchema::downloadColumns()` accetta anche le nuove `Column`, oltre agli array `['label', 'value']` (con `key` e `type` facoltativi, punto 4). `ContactExport` passa al nuovo formato senza cambiare l'output.

3. **La sorgente è un contratto del core: `Wonder\App\Dataset\Source`.**
   - Metodi:
     - `key()`, `label()`;
     - `columns()`: le `Column` disponibili;
     - `rows(Query $query): iterable`: righe a blocchi. `Query` porta filtri, ordinamento, limite, `since` per gli export incrementali e l'utente;
     - `permits(?object $user, string $action)`, con azione `export` o `import`;
     - `public(): bool`, cioè se i dati possono finire in un file a URL pubblico (punto 9).
   - `Importable` è un'interfaccia opzionale con `validate()`, `apply()`, `undo()`, `mappingErrors()` e `message()`: la parte di import dell'attuale `Layout`.
   - `Import\Layout` diventa una classe astratta che implementa `Source` e `Importable`, così i Layout esistenti valgono come sorgenti senza modifiche. `rows()` di default restituisce nulla, e un Layout senza `rows()` resta solo import.
   - Il registro è `Sources`, con lo stesso modello di `Layouts`, `HomeStatus` e `Integrations`: core, poi moduli (config `dataset.sources`, dove `false` toglie una sorgente), poi sito (`Sources::set()`). `import.layouts` resta letto come alias deprecato.

4. **Una sorgente generica per qualsiasi Resource, attivata con `download()` (opt-in).**
   - Una Resource è sorgente se la sua lista dichiara `download()`; le sue colonne sono quelle di `downloadColumns()`. Non serve una seconda configurazione: l'export della lista dice già quali Resource sono esportabili e con quali campi. Una Resource senza `download()` non compare fra le sorgenti, a meno che un modulo non la registri in `dataset.sources`. Così tabelle come `user`, `api_users` e `import_jobs` restano fuori.
   - `ResourceSource::for(SomeResource::class)` costruisce la sorgente da `tableLayoutSchema()`:
     - colonne da `downloadColumns()`, convertite in `Dataset\Column`; senza `downloadColumns()`, le stesse colonne di default dell'export di oggi;
     - righe con le stesse query, filtri e `RowVisibility::for($user)` dell'export della lista, passate a `decorate()` e a `Model::translated()` se il Model è traducibile;
     - permesso `Resource::allows($authorities, 'list')`;
     - mai `public()`.
   - `downloadColumns()` si allarga, e gli array di oggi restano validi:
     - `key` facoltativa e stabile, perché un tracciato salvato non si rompa se cambia l'etichetta o la lingua. Se manca vale il campo quando `value` è una stringa, altrimenti l'etichetta normalizzata;
     - `type` facoltativo (punto 2), per le trasformazioni del tracciato e per un eventuale import;
     - `value` accetta anche un percorso di relazione (`brand.name`), risolto da `Column::foreign()` in `tableSchema()`.
   - Nuovo hook `TableLayoutSchema::downloadRows(callable $rows)`: riceve le righe a blocchi e ne restituisce altre, per esempio una riga per partecipante invece che per risposta. Vale per l'export della lista e per i tracciati.
   - Le colonne condizionali o dinamiche (dipendenti da impostazioni o campi personalizzati) restano codice in `downloadColumns()`, come oggi.
   - Una colonna con `value` callable si esporta ma non si rilegge in import: per quella il tracciato è solo in uscita.
   - **Esempio di migrazione: rsvp.** `ResponseResource` oggi sostituisce la rotta dell'export (`registerBackendRoutes()`, `/participants-export/{format}/`) e `ResponseExporter` rilegge le colonne e scrive il file solo per espandere le righe. Con `downloadRows(fn ($rows) => ResponseExporter::expandRows($rows))` la rotta personalizzata sparisce, `ResponseExporter` si riduce a `expandRows()` e il file resta lo stesso.
   - L'import generico per qualsiasi Resource (crea/aggiorna via `Model::validate()` / `prepare()` / `createUpdate()` per chiave) è una possibilità futura, non in questa spec: ogni tabella ha regole di unicità e di sicurezza proprie.

5. **Sorgenti di dominio nei moduli.**
   - Gestionale:
     - `catalog`: già `CatalogLayout`. Implementa `rows()` sopra `ProductExport::rows()` e aggiunge colonne derivate (`price_with_tax`, `availability`, `image_link`, `additional_image_links`, `item_group_id`, `product_type`). È `public()`;
     - `customers` e `suppliers`: `ResourceSource` delle Resource esistenti, con le colonne di `ContactExport` come preset;
     - `orders`: testata ordine con cliente e indirizzi, `items` come lista. Supporta `since` (data di modifica), così il gestionale di un cliente riceve solo gli ordini nuovi;
     - `order_items`: una riga per riga d'ordine, per i programmi che vogliono il dettaglio piatto.
   - Customer-account: `contacts`, al posto dell'attuale `ContactExport`, e in futuro i contatti dei form pubblici.
   - Le sorgenti di dominio si registrano in `module.json` / config `dataset.sources`, come i Layout di oggi.

6. **Il tracciato diventa generico.**
   - Tabelle con nomi neutri: `data_layouts` e `data_layout_columns`. Oggi `import_layouts` e `import_layout_columns`; la migrazione è al punto 11.
   - `data_layouts`:
     - `name`, `source` (chiave della sorgente, oggi `layout`), `direction` (`import`, `export`, `both`), `target` (chiave della destinazione), `is_default` per sorgente e direzione;
     - `options` (JSON): separatore e codifica del CSV, intestazione sì/no, formato data, separatore decimale, filtri fissi (per esempio «solo prodotti visibili online»);
     - consegna (punto 8): `schedule`, `delivery`, `recipients`, `public_token`, `last_run_at`, `last_cursor`.
   - `data_layout_columns`: `column_key` (campo della sorgente, vuoto se la colonna è fissa), `header` (intestazione, oppure campo della destinazione), `position`, più:
     - `constant`: valore fisso, per esempio `condition = new`;
     - `transform` (JSON): pipeline breve di trasformazioni da un elenco chiuso del core: `upper`, `lower`, `trim`, `truncate:N`, `prefix`, `suffix`, `replace`, `default`, `map` (valore → valore), `date:<formato>`, `number:<decimali>`, `join:<sep>`, `first`, `strip_tags`.
   - Le trasformazioni sono funzioni del core registrate per chiave (`Transforms`), estendibili dai moduli. Niente template liberi né espressioni valutate: l'admin compone da un elenco, e lo stesso tracciato produce lo stesso file su ogni ambiente.
   - Il profilo di oggi (colonne, intestazioni, ordine) è il caso con destinazione generica e senza trasformazioni. La pagina Tracciati file si allarga senza cambiare il caso semplice.

7. **Destinazioni.**
   - Contratto `Wonder\App\Dataset\Target`: `key()`, `label()`, `extension()`, `mime()`, `fields()` (vuoto se generica, altrimenti i campi con obbligatorietà e descrizione), `write(iterable $rows, Layout $layout, $stream)`.
   - Core:
     - `csv` e `xlsx`: unificano `ResourceDownloadController`, `Sample` e `Support/Export/{Delimited,Download}` del gestionale, che si spostano nel core. Ogni cella passa da `SpreadsheetText`: anagrafiche e ordini contengono testo scritto dai visitatori;
     - `json`: elenco di oggetti, per integrazioni;
     - `xml-feed`: RSS 2.0 con namespace configurabile, da `Support/Export/XmlFeed` del gestionale, che si sposta nel core.
   - Gestionale (dominio feed), costruite sopra `xml-feed` e `csv`:
     - `google-merchant`: campi `id`, `title` (max 150), `description` (max 5000), `link`, `image_link`, `availability`, `price` obbligatori; `brand`, `gtin` / `mpn` / `identifier_exists`, `condition` obbligatori per categoria; `item_group_id`, `sale_price`, `sale_price_effective_date`, `additional_image_link` (max 10), `product_type`, `google_product_category`, `shipping_weight`, `custom_label_0..4` facoltativi;
     - `meta-catalog`: `id`, `title`, `description`, `availability`, `condition`, `price`, `link`, `image_link`, `brand` obbligatori; facoltativi `item_group_id`, `sale_price`, `gtin`, `mpn`, `additional_image_link`, `product_type`, `google_product_category`. CSV, oppure XML con lo stesso writer di Google.
   - `GoogleMerchant` e `MetaCatalog` non si riscrivono: diventano i `write()` delle due destinazioni, e le regole di `ProductFeed` (`money`, `gtin`, `eligible`, troncamenti) diventano trasformazioni o colonne della sorgente `catalog`.
   - Ogni destinazione a schema fisso offre un **tracciato preimpostato**: il seed crea il tracciato «Google Merchant» con le colonne mappate come oggi. L'admin lo copia e lo cambia, e la pagina segnala i campi obbligatori non mappati prima del salvataggio.
   - TikTok e Amazon (feature già previste) sono altre destinazioni dello stesso tipo, senza toccare il core.

8. **Consegna manuale e automatica.**
   - **Manuale.** Il pulsante Esporta di una lista Resource mostra i tracciati in uscita della sua sorgente (`downloadUsing` → `DatasetExport::download($layout)`), con i filtri correnti della lista. Senza tracciati restano i formati di oggi.
   - **Automatica.** Un solo task del core sempre attivo, `wonder.exports`, ogni minuto e sotto `NamedLock`: legge i tracciati con `schedule` non vuoto e lancia quelli scaduti. `schedule` è un'espressione cron validata con `CronExpression`, oppure un preset (ogni ora, ogni 6 ore, ogni giorno alle 3). Non si crea un task scheduler per tracciato: i task sono definizioni di codice, i tracciati sono dati. Le esecuzioni compaiono nel log del task (contatori per tracciato) e in `last_run_at`.
   - **Uscite** (`delivery`, anche più di una):
     - `file`: scrive in `shared/exports/<nome>-<token>.<ext>` in modo atomico (file temporaneo, poi `rename`). Il token è casuale (32 esadecimali), si rigenera dalla pagina, e il nome è il segreto: Apache serve il file come statico, senza PHP. Chi vuole cambiare URL rigenera il token;
     - `email`: `queueMail()` ai `recipients`, con il file in allegato o, sopra una soglia di dimensione, un link a scadenza;
     - `sftp` / `ftp`: fase successiva, come `Integration` con credenziali in Credenziali.
   - **Incrementale.** Se la sorgente supporta `since`, il tracciato può esportare solo le righe cambiate dall'ultimo invio riuscito (`last_cursor`), per esempio gli ordini verso il gestionale di un cliente. Un invio fallito non avanza il cursore.

9. **Sicurezza degli export automatici.**
   - Il task gira con utente `null`, cioè senza `RowVisibility`. Per questo:
     - l'uscita `file` (URL pubblico) è permessa solo per le sorgenti `public()`: il catalogo sì; anagrafiche, ordini e `ResourceSource` mai;
     - le altre sorgenti ammettono download manuale (con l'utente e la sua visibilità) ed email a destinatari scritti dall'admin;
     - un tracciato salva l'utente che lo ha programmato (`owner_id`). Il task applica `permits()` e `RowVisibility::for($owner)` di quell'utente: se l'utente non esiste più o perde il permesso, il tracciato si sospende e compare un avviso in home (`HomeStatus`).
   - Configurare tracciati in uscita e programmarli richiede permessi separati (`update` sulla Resource Tracciati file e `export` sulla sorgente).
   - La cartella `shared/exports/` ha la regola del blocco `WONDER SECURITY` che vieta l'indice della directory.

10. **Dove vive ogni pezzo.**

    | Pezzo | Dove |
    |---|---|
    | `Dataset\Column`, tipi, `Transforms`, `Source`, `Importable`, `Sources`, `Query` | app (core) |
    | `ResourceSource`, `downloadColumns()` allargato, `downloadRows()` | app |
    | Destinazioni `csv`, `xlsx`, `json`, `xml-feed`, writer da `Support/Export` | app (spostati dal gestionale) |
    | Tracciati (Model, Resource, pagina), `DatasetExport`, task `wonder.exports`, uscite `file` ed `email` | app |
    | Motore di import (`Importer`, `Mapping`, `Reader`, `Store`) | app, invariato salvo leggere i nuovi `type` |
    | Sorgenti `catalog`, `customers`, `suppliers`, `orders`, `order_items` | gestionale |
    | Destinazioni `google-merchant`, `meta-catalog` (poi TikTok, Amazon) e i loro preset | gestionale |
    | Sorgente `contacts` | customer-account |
    | JS e CSS della pagina tracciati (riordino colonne, editor trasformazioni) | lib, se servono componenti nuovi; altrimenti `FormField` / `Repeater` esistenti |

    Ecommerce non cambia. La valuta duplicata (`catalog.currency` / `exports.currency`) si unifica in una sola impostazione letta da entrambi: è un'attività separata, da segnalare nella fase 4.

11. **Migrazione.**
    - Le tabelle `import_layouts` e `import_layout_columns` sono del 2026-11-04 e probabilmente esistono solo sul sito di prova. **Si rinominano ora** in `data_layouts` / `data_layout_columns` con una migrazione del core che usa `LegacyTableRenames` (un solo `RENAME TABLE`, si ferma se esistono entrambi i nomi). Le nuove colonne si aggiungono con l'allineamento di `forge update`. Rinominare fra sei mesi costerebbe molto di più.
    - Le righe esistenti: `layout` → `source`, `direction = both`, `target = csv` (o `xlsx`, il formato che usa l'export manuale), `constant` e `transform` vuoti. Il significato non cambia.
    - `ImportLayoutResource` mantiene `path()` (`app/config/import-layouts`) e diventa `DataLayoutResource` con alias, così la sottoclasse di un modulo che sposta o nasconde la voce continua a funzionare.
    - I feed esistenti mantengono URL e nomi fissi in `shared/feeds/`, perché i Merchant Center dei clienti li hanno già configurati. Il seed crea i tracciati «Google Merchant» e «Meta» con `delivery = file` e un percorso fisso legacy al posto del token. Da quel momento `ProductFeedsTask` delega a `wonder.exports`; il suo intervallo `EVERY` diventa lo `schedule` del tracciato. Il task del gestionale resta un rilascio, poi si rimuove.
    - `ProductExports` (formati xlsx, csv, meta, google del pulsante Esporta) diventa l'elenco dei tracciati in uscita della sorgente `catalog`. Le feature `export_*` restano il cancello: una destinazione non abilitata non compare.

12. **Fasi, una PR ciascuna.** Ogni fase aggiorna nello stesso cambio i docs `docs/app/*`, `AGENTS.md`, la skill wi-app e il CHANGELOG (sezione Unreleased unica).
    1. **F1, gestionale: collegare il profilo.** `ProductExports::body()` passa `Profile::for('catalog')` a `csv()` / `xlsx()`. Valore immediato, rischio minimo, nessun cambio nel core. Docs: pagina export del catalogo.
    2. **F2, core: Dataset.** `Dataset\Column` con `value` e `type`, `Transforms`, `Source` / `Importable` / `Sources`, `Import\Layout` e `Import\Column` come compatibilità, destinazioni `csv` / `xlsx` / `json` spostate nel core, rename delle tabelle, colonne `direction` / `target` / `constant` / `transform`, export manuale da tracciato in `ResourceDownloadController`. Test: round trip per ogni `type`, migrazione da `import_layouts`, `downloadColumns` invariato byte per byte. Docs: nuova `docs/app/concetti/tracciati.md` (sviluppatore e utente), `importazione.md` aggiornata.
    3. **F3, gestionale e customer-account: sorgenti.** `catalog` con `rows()`, `customers`, `suppliers`, `contacts`, export manuale dai tracciati, writer di `Support/Export` rimossi in favore del core.
    4. **F4, core: consegna automatica.** Task `wonder.exports`, `schedule`, uscite `file` ed `email`, `owner_id`, regola `public()`, avviso in home, incrementale `since` / `last_cursor`.
    5. **F5, core e gestionale: feed.** Destinazione `xml-feed` nel core; `google-merchant` e `meta-catalog` come destinazioni a schema fisso con preset; migrazione dei due feed con URL invariati; `ProductFeedsTask` delega.
    6. **F6, gestionale: ordini.** Sorgenti `orders` / `order_items` con `since`, preset per i programmi gestionali più comuni dei clienti.
    7. **F7, core: `ResourceSource`.** Sorgente da `download()` / `downloadColumns()`, `key` e `type` sulle colonne, `downloadRows()`, relazioni da `Column::foreign()`, campi tradotti. Test: export di una lista invariato byte per byte. Migrazione di rsvp nel modulo, nella stessa fase. È l'ultima perché si appoggia su tutto il resto; può anticipare F6 se la priorità cambia (decisione aperta).

## Fuori ambito

- Import generico per qualsiasi Resource (punto 4).
- SFTP / FTP (punto 8, fase successiva).
- Template liberi o espressioni nelle colonne: solo trasformazioni da elenco chiuso.
- API di Google Content e Meta Graph per l'invio diretto: i feed restano file letti dai Merchant Center.

## Decisioni aperte per l'utente

Tutto il resto della spec è deciso. Le sorgenti generiche sono opt-in tramite `download()` / `downloadColumns()` (punto 4). Resta una scelta:

1. **Priorità dopo F2-F3.** Prima i feed Google e Meta configurabili dai tracciati (F5), oppure prima gli export di anagrafiche e ordini, anche incrementali, per i programmi dei clienti (F6 + F7)? L'ordine proposto è F4 → F5 → F6 → F7.
