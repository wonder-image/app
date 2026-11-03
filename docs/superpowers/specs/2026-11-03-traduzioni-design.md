# Traduzioni — design

**Data:** 2026-11-03
**Repo:** app (framework). Il sito di riferimento è new-site, dove cambiano solo le pagine legali.
**Tipo:** architetturale
**Issue:** wonder-image/app#124

## Contesto

Ci sono tre esigenze, che passano per la stessa strada.

1. **Testi per il traduttore.** Il sito ha i suoi testi nei JSON di `lang/`. Il traduttore lavora su un foglio di calcolo, non sui file: serve un export e un import dal backend.
2. **Documenti legali.** Oggi i testi stanno in due posti: `legal.json`, per le pagine pubbliche, e `legal_documents`, per le versioni accettate. Il risultato è difficile da creare, tradurre e aggiornare.
3. **Contenuti dei Model** (issue #124), per esempio un catalogo che arriva in italiano e va pubblicato in inglese con il termine tecnico giusto: traduzione automatica, vocabolario, stati delle traduzioni.

Vincolo comune: **una tabella o un sito che non usa le traduzioni si comporta come oggi.** Nessuna query in più e nessun output diverso.

## Decisioni

1. **Un solo catalogo, più sorgenti.**
   - `Wonder\Localization\Catalog\TranslationSource` è il contratto: `key()`, `label()`, `entries($source, $target)`, `apply($source, $target, $changes)`.
   - Le sorgenti del core sono tre:
     - `json`: i testi del sito;
     - `legal`: i documenti legali;
     - `model:<tabella>`: una per ogni Model traducibile.
   - L'elenco segue il modello di `HomeStatus` e `Integrations`: core, poi moduli (`localization.sources`, dove `false` toglie una sorgente), poi sito (`Catalog::set()`).
2. **Export e import in XLSX, oppure CSV.**
   - Le colonne sono `id`, `contesto`, `<lingua sorgente>`, `<lingua destinazione>`; le intestazioni sono i codici lingua.
   - L'export scrive le celle con `SpreadsheetText::cell()`, così nessun valore viene letto come formula.
   - L'import accetta `.xlsx` e `.csv` e legge le celle come testo. Un apice iniziale aggiunto dall'export viene tolto solo se la sorgente non lo ha.
3. **L'import si fa in due passi, con anteprima.**
   - **Primo POST (upload).** `Csrf::verify()`, poi il file viene letto e confrontato con il catalogo corrente. Il diff normalizzato si salva in `storage/translations/<token>.json`, con il token legato alla sessione e una scadenza di un'ora.
   - **La pagina mostra l'anteprima.** Ogni riga ha una categoria:
     - `nuova`: la destinazione oggi è vuota;
     - `modificata`;
     - `invariata`;
     - `sorgente cambiata`: il testo sorgente nel file è diverso da quello di oggi. La riga resta deselezionata;
     - `errore`, nei casi seguenti:
       - l'id non esiste;
       - un `{{placeholder}}` della sorgente manca o è stato aggiunto;
       - i tag HTML della destinazione non sono gli stessi della sorgente (stesso insieme di nomi, con le ripetizioni);
       - una lista JSON resta incompleta;
       - per i documenti legali, la versione è già stata accettata.
   - **Secondo POST (conferma).** `Csrf::verify()`, poi si rilegge il diff dal server: dal client arrivano solo il token e gli id scelti. Le righe in errore non si applicano mai.
   - Una cella vuota non vuol dire "cancella": la riga viene saltata. **Nessuna chiave si cancella mai.**
4. **Sorgente JSON.**
   - L'id è la chiave puntata, la stessa di `__t()`. Il contesto è il file in cui la chiave è dichiarata.
   - La lettura usa lo stesso ordine di `TranslationProvider` (core, moduli, sito; per mtime dentro una cartella), ma non passa dal singleton.
   - La scrittura va solo in `{ROOT}/lang/{target}/{file}.json` del sito. Il JSON è pretty e unescaped; le liste restano array.
   - Una lista toccata deve risultare completa nella lingua di destinazione. `getValue()` cerca la chiave intera nella lingua corrente, quindi una lista parziale nasconderebbe gli indici che mancano.
5. **I documenti legali hanno una sola fonte: `legal_documents`.**
   - Per le pagine pubbliche c'è l'Element `LegalDocument::make($type)`, che rende il documento attivo nella lingua corrente e, se manca, quello nella lingua di default. Usa lo stesso `infoLegalDocument()` del backend, con i placeholder risolti.
   - `legal.json` resta solo come seed per `build/row`.
   - La sorgente `legal` usa come id `<tipo>.name`, `<tipo>.checkbox_label` e `<tipo>.blocks.<n>.<campo>`, ricavati dai blocchi dell'editor.
   - All'import, il documento nella lingua di destinazione prende la **stessa versione** del documento sorgente:
     - se non esiste, viene creato copiando la struttura dei blocchi;
     - se esiste e nessun `consent_events` lo referenzia, viene aggiornato sul posto (con `content_hash` ricalcolato);
     - se è già stato accettato, la riga va in errore. Per correggerlo serve una versione nuova.
6. **Campi traducibili dei Model.**
   - Il Model implementa `Wonder\Localization\Contracts\Translatable` e usa il concern `HasTranslations`. Dichiara `translatableFields()`, per esempio `['name' => 'text', 'description' => 'html']`.
   - Le traduzioni stanno nella tabella generica `translations`: `table_name`, `row_id`, `field`, `language_code`, `value`, `source_hash`, `status`, `engine`, `translated_at`, unique sulle prime quattro colonne.
   - La lettura è esplicita: `Model::translated($rows, ?$lang)`, una query per batch. `decorate()` resta puro e un Model che non usa il concern non cambia.
   - Se manca una traduzione si ricade sul valore della lingua base, cioè la colonna stessa.
7. **Stati.**
   - `automatic`: i job la possono rifare.
   - `reviewed`: corretta a mano (backend o import). Nessun job la tocca più.
   - `review`: il testo base è cambiato dopo una traduzione `reviewed`. Si mostra ancora, ma non si rifà da sola.

   Il task confronta `source_hash` con l'hash del valore corrente. Una richiesta di ritraduzione azzera `source_hash` delle sole righe `automatic`.
8. **Motore.**
   - Il contratto è `Wonder\Localization\Machine\Translator::translate(array $html, $source, $target): array`.
   - Il primo driver è `GoogleTranslator`, su Cloud Translation **v2**:
     - è l'unica versione che funziona con la sola chiave API (`gcp_api_key` di `GoogleCloud`) e con `format=html`, che serve perché `translate="no"` venga rispettato;
     - il trasporto HTTP si inietta, così i test non chiamano mai Google;
     - Cloud Translation si aggiunge a `GoogleApis::core()`.
9. **Pipeline per ogni testo** (`MachineTranslation`):
   1. memoria (`translation_memory`, chiave hash di lingua sorgente, lingua destinazione e testo);
   2. composizione locale, quando ogni segmento è un termine del vocabolario, una parola da non tradurre o un codice/numero;
   3. altrimenti protezione: i termini sono sostituiti dalla loro traduzione, e termini, parole fisse e `{{placeholder}}` sono avvolti in `<span translate="no">`. Poi il driver, poi si tolgono gli span e si salva in memoria.

   Il testo semplice viene fatto passare per l'escape e poi decodificato; l'HTML si sostituisce solo nei nodi di testo.
10. **Vocabolario e parole da non tradurre.** Stanno nella stessa tabella `translation_terms`:
    - `term` nella lingua base;
    - `mode`: `glossary` oppure `keep`;
    - `status`: `confirmed` oppure `candidate`;
    - `occurrences`.

    Le traduzioni di ogni termine vanno in `translations` (`table_name = translation_terms`, `field = term`, stato `reviewed`). La Resource "Vocabolario" mostra un campo per ogni lingua attiva. Le lingue sono note solo a runtime, quindi niente colonne per lingua.
11. **«Compare in N contenuti già tradotti: vuoi aggiornarli?»**
    - Dopo il salvataggio di un termine, la sua scheda mostra quanti contenuti tradotti lo contengono.
    - Un `Button::post()->confirm()` ritraduce solo quelli `automatic`, accodandoli: azzera `source_hash`, poi ci pensa il task.
    - I contenuti `reviewed` vengono elencati con un link alla traduzione, perché il cliente decida caso per caso.
12. **Termini imparati.** A ogni passaggio il task conta i segmenti non coperti dal vocabolario che si ripetono nei testi sorgente. Oltre una soglia (default 3) li propone come `candidate`, insieme alla traduzione presa dalla memoria. Il cliente li conferma o li trasforma in `keep` dalla lista del Vocabolario. Un candidato scartato diventa `rejected` e non viene più proposto.
13. **Task dello Scheduler: `wonder.content-translation`.**
    - Gira ogni 10 minuti, con un budget per esecuzione di 200 testi.
    - Senza chiave API si salta (`skipped: missing_api_key`), ma la memoria e la composizione locale lavorano lo stesso.
    - Riceve driver e store per costruttore, come `GooglePlacesTask`.
    - Nessuna traduzione parte al salvataggio di un contenuto.
14. **Backend.**
    - La pagina **Traduzioni** (sezione `setup`) contiene export, import e anteprima per ogni sorgente.
    - **Vocabolario** è la Resource dei termini.
    - **Contenuti tradotti** è la Resource sulla tabella `translations`: lista filtrabile per tabella, lingua e stato. Una modifica a mano la segna `reviewed`.
    - La lista dei documenti legali ha un bottone verso Traduzioni, con la sorgente `legal` già scelta.

## Fuori dal perimetro

- Traduzione automatica dei testi JSON del sito: si usa l'export per il traduttore.
- Vocabolario condiviso fra clienti.
- Gestionale (campi dichiarati, lingue nelle schede) ed ecommerce (`/en/`, slug tradotti, `hreflang`, sitemap per lingua): issue a parte.
- DeepL e LLM: dietro lo stesso contratto, quando serviranno.

## Test

Script in `tests/Localization/`:

- catalogo JSON (lettura, scrittura, liste);
- anteprima e validazione;
- foglio XLSX/CSV e formule;
- sorgente legale con store in memoria;
- `HasTranslations::translated()` con store in memoria;
- pipeline: memoria, composizione, protezione, con driver finto;
- driver Google con trasporto finto;
- stati del task;
- termini candidati.

Più `php tests/Docs/CatalogRenderTest.php` per la scheda di `LegalDocument`.
