# Traduzioni

Tre strade per lo stesso problema, tutte nel gruppo **Traduzioni** della sezione **Set-up** del backend (voci Export e import, Contenuti tradotti, Vocabolario); i documenti legali restano in Set-up → Documenti legali:

| Cosa | Dove | Come si traduce |
| --- | --- | --- |
| Testi del sito (`lang/*.json`) | Export e import | export per il traduttore, import con anteprima |
| Documenti legali (`legal_documents`) | Documenti legali, Export e import | export/import, una versione per lingua |
| Campi dei Model (`Translatable`) | Contenuti tradotti, Vocabolario | task automatico, correzioni a mano |

Un sito o una tabella che non usa le traduzioni si comporta come prima: nessuna
query in più, nessun output diverso.

## Pagina Traduzioni: export e import

`TranslationsResource` (Set-up → Traduzioni → Export e import, solo `admin`) è
una procedura in tre passi, uno per schermata, guidata solo dall'URL (nessun
JavaScript):

1. **Cosa tradurre**: sorgente, lingua di partenza e di destinazione (form GET).
2. **Scarica e carica**: i link al file per il traduttore e l'upload di quello
   tradotto. Si arriva qui con `?source=&from=&to=`; `step=1` torna al passo 1
   tenendo la scelta.
3. **Controlla e applica**: l'anteprima (`?preview=<token>`) e la conferma.

Con una sola lingua il sito vede un avviso al posto dei passi.

- **Export** in XLSX o CSV con le colonne `id`, `contesto`, `<lingua sorgente>`,
  `<lingua destinazione>`. Le celle passano da `SpreadsheetText::cell()`, così
  Excel non legge formule.
- **Import in due passi.** L'upload confronta il file con il catalogo e salva il
  diff sul server (`storage/translations/<token>.json`, legato alla sessione,
  scade dopo un'ora). L'anteprima mostra ogni riga come nuova, modificata,
  invariata, *sorgente cambiata* (deselezionata) o in errore. La conferma
  rilegge il diff dal server: dal client arrivano solo il token e gli id scelti.
- Una riga è in errore se l'id non esiste, se un `{{placeholder}}` manca o è
  in più, se i tag HTML non coincidono con quelli della sorgente, se una lista
  JSON resta incompleta o se il documento legale è già stato accettato.
- Una cella vuota salta la riga: **l'import non cancella mai una chiave.**

### Sorgenti

Il catalogo è `Wonder\Localization\Catalog\Catalog`; ogni sorgente implementa
`TranslationSource` (`key()`, `label()`, `entries($source, $target)`,
`apply($source, $target, $changes)`). Le sorgenti del core:

- `json`: le chiavi puntate di `__t()`, lette nello stesso ordine di
  `TranslationProvider` (core, moduli, sito). Scrive solo in
  `{ROOT}/lang/{lingua}/{file}.json` del sito.
- `legal`: i documenti attivi di `legal_documents` (id `<tipo>.name`,
  `<tipo>.checkbox_label`, `<tipo>.blocks.<n>.<campo>`).
- `model:<tabella>`: una per ogni Model `Translatable`.

Moduli e sito aggiungono, sostituiscono o tolgono sorgenti come per
`HomeStatus`:

```php
// modulo: hook localization.sources, false toglie una sorgente
// sito, per esempio in custom/config:
use Wonder\Localization\Catalog\Catalog;

Catalog::set('faq', new FaqTranslationSource());
Catalog::set('legal', false);
```

## Documenti legali

`legal_documents` è l'unica fonte: le pagine pubbliche usano l'Element
`LegalDocument`, che rende il documento attivo nella lingua corrente e, se
manca, quello nella lingua di default (stessi placeholder del backend).

```php
use Wonder\Elements\Components\LegalDocument;

echo LegalDocument::make('privacy');
echo LegalDocument::make('cookie')->title(true, 'h2');
```

`lang/*/legal.json` resta solo come seed per `build/row`. All'import il
documento nella lingua di destinazione prende la stessa versione della
sorgente: si crea se manca, si aggiorna se nessuno l'ha ancora accettato,
altrimenti la riga va in errore e serve una versione nuova. La lista dei
documenti legali ha un bottone verso Export e import con la sorgente già scelta.

## Campi traducibili dei Model

Il Model implementa `Translatable` e usa `HasTranslations`:

```php
use Wonder\Localization\Concerns\HasTranslations;
use Wonder\Localization\Contracts\Translatable;

final class Product extends Model implements Translatable
{
    use HasTranslations;

    public static function translatableFields(): array
    {
        return ['name' => 'text', 'description' => 'html'];
    }
}

$rows = Product::translated($rows);        // lingua corrente
$row = Product::translated($row, 'en');    // una riga sola
```

- La colonna resta il testo nella lingua base. Le traduzioni stanno nella
  tabella generica `translations` (`table_name`, `row_id`, `field`,
  `language_code`, `value`, `source_hash`, `status`, `engine`,
  `translated_at`).
- La lettura è esplicita: `translated()` fa una query per batch e lascia il
  testo base dove la traduzione manca o è vuota. `decorate()` resta puro.
- Nessuna traduzione parte al salvataggio del contenuto: ci pensa il task.

### Stati

| Stato | Significato | Il task |
| --- | --- | --- |
| `automatic` | scritta dal motore, dalla memoria o dal vocabolario | la rifà quando cambia il testo base |
| `reviewed` | corretta a mano (backend o import) | non la tocca più |
| `review` | il testo base è cambiato dopo una correzione a mano | la segnala, non la rifà |

Il confronto è fra `source_hash` e `Translations::hash()` del testo corrente.

## Traduzione automatica

Il task `wonder.content-translation` (`ContentTranslationTask`) gira ogni 10
minuti con i parametri `budget` (testi per esecuzione, default 200) e
`threshold` (default 3, vedi i termini proposti). Senza `GCP_API_KEY` chiude
con `skipped: missing_api_key`, ma memoria e vocabolario lavorano lo stesso.

Per ogni testo `MachineTranslation` prova, in ordine:

1. **composizione locale**: il testo è fatto solo di termini del vocabolario,
   parole da non tradurre e codici;
2. **memoria** (`translation_memory`, chiave lingue + testo protetto);
3. **motore**: i termini sono sostituiti dalla loro traduzione e, con le parole
   da non tradurre e i `{{placeholder}}`, avvolti in `<span translate="no">`.
   Le stringhe mancanti partono in una sola chiamata per lingua.

Il motore è `Wonder\Localization\Machine\Translator`; il driver del core è
`GoogleTranslator` (Cloud Translation v2, `format=html`, chiave
`gcp_api_key`). Task e driver ricevono store e trasporto per costruttore,
così i test non chiamano mai Google. Vedi [Cron job da codice](../piattaforma/cron-job.md).

## Vocabolario

Set-up → Traduzioni → Vocabolario (`TranslationTermResource`, tabella `translation_terms`):

- **Uso**: *Traduci sempre così* (`glossary`) o *Non tradurre* (`keep`).
- **Stato**: *Confermato*, *Proposto* (`candidate`) o *Scartato* (`rejected`).
  Solo i termini confermati entrano nella pipeline.
- Un campo per ogni lingua attiva: le traduzioni del termine stanno in
  `translations` (`field = term`), sempre `reviewed`.

**Termini proposti.** A ogni esecuzione il task conta i segmenti (separati da
`, ; / |` e a capo, fino a 4 parole, niente codici) non coperti dal
vocabolario. Quelli ripetuti almeno `threshold` volte diventano `candidate`,
con la traduzione della memoria se c'è. Un termine scartato non torna.

**Ritraduci.** La scheda di un termine dice in quanti contenuti tradotti
compare e quanti sono corretti a mano. «Ritraduci automatiche» (POST con
conferma, route `backend.resource.app-config-translation-terms.requeue`)
azzera `source_hash` delle sole righe `automatic`: si rifanno alla prossima
esecuzione del task. Quelle corrette a mano restano e sono elencate per la
revisione.

## Contenuti tradotti

Set-up → Traduzioni → Contenuti tradotti (`TranslatedContentResource`) elenca la tabella
`translations` con filtri per stato, lingua e tabella. Si modifica solo il
testo: il salvataggio la rende `reviewed` (motore `manual`) e ricalcola
`source_hash` sul testo base attuale, mostrato sopra il form. Le traduzioni non
si creano né si cancellano a mano.

## Fuori dal perimetro

Traduzione automatica dei JSON del sito, vocabolario condiviso fra siti, URL e
slug per lingua dell'ecommerce, DeepL e LLM (si aggiungono dietro lo stesso
contratto `Translator`).
