# Coda dei ridimensionamenti

Caricare un'immagine in un campo responsive vuol dire generare una copia per
ogni larghezza di `RESPONSIVE_IMAGE_SIZES`, più le copie `.webp`. Fatto dentro
la richiesta, il salvataggio aspetta. La coda sposta quel lavoro sullo
scheduler: `uploadFiles()` scrive l'originale e una riga in `image_resizes`,
il cron genera le misure entro un minuto.

Non c'è niente da configurare: funziona per ogni campo file, non solo per la
libreria Media, e si spegne da sola dove lo scheduler non gira.

## Cosa va in coda e cosa no

| Il campo dichiara | Esempio di `resize` | Quando si generano le misure |
| --- | --- | --- |
| Larghezze responsive (lista di numeri) | `[240, 480, 960]`, le misure del sito prese da sole da un campo immagine | in coda |
| Solo la copia webp | `[]` con `webp` vero | in coda |
| Misure fisse dichiarate con `width` | `['width' => 600, 'height' => 400]`, `resizeToWidth(600)`, `resizeToWidths([...])`, `responsive()` | subito |
| `convertTo('png')` | qualunque | subito |
| `deferResize()` | nessuna misura | mai: le genera chi ha dichiarato il campo |

La regola è `ResizeQueue::queueable($resize)` più l'assenza di `convert`. Le
misure dichiarate con `width` restano immediate perché chi le dichiara di
solito legge il file per nome (`nome-600.jpg` in una mail, in un PDF, in un
`og:image`) e si aspetta di trovarlo appena salvato. Le larghezze responsive
passano dai renderer, che controllano il disco.

{% hint style="info" %}
`uploadFiles()` non ha mai generato file `600x400-nome.jpg`: di una misura
`['width' => 600, 'height' => 400]` usa solo la larghezza e scrive
`nome-600.jpg`. La coda non cambia questo comportamento.
{% endhint %}

## Quando la coda non si usa

`ResizeQueue::enqueue()` ritorna `false`, e `uploadFiles()` ridimensiona
subito come prima, se:

- la tabella `image_resizes` non esiste ancora (prima di `forge update`);
- lo scheduler non ha battuto un colpo negli ultimi 5 minuti
  (`Outbox::alive()`, lo stesso controllo della coda email);
- la scrittura della riga solleva un'eccezione.

Un sito senza cron si comporta quindi esattamente come prima.

## Cosa si vede mentre la riga è in coda

- **Sito.** `Image`, `Gallery` e `Swiper` mettono nel `srcset` solo le misure
  presenti su disco (`RendersResponsiveImage::keepExistingVariants()`), e
  `Image::url()` fa lo stesso: finché la coda non gira si vede l'originale.
- **Backend.** L'anteprima del campo file e la miniatura nelle liste usano
  l'originale finché la misura manca (`Wonder\App\Media\Variants`).
- **Codice del sito.** Chi compone a mano `nome-480.jpg` senza controllare
  che esista vedrà un 404 per qualche decina di secondi: usare
  `Image::src($url)->size(480)->url()`, oppure dichiarare la misura con
  `width` per averla subito.

## La tabella

`Wonder\App\Models\System\ImageResize`, tabella `image_resizes`, creata da
`forge update`, fuori da `TableSync`. Una riga per file.

| Colonna | Contenuto |
| --- | --- |
| `path`, `path_hash` | percorso relativo a `ROOT` e il suo `sha1` (unico) |
| `widths` | JSON delle larghezze, salvate al caricamento: lo scheduler non conosce `RESPONSIVE_IMAGE_SIZES` |
| `webp`, `quality` | copia webp e sua qualità (`null` = 80) |
| `delete_when_ready` | JSON dei file da cancellare quando la riga diventa `ready` |
| `status` | `pending`, `ready`, `failed` |
| `attempts`, `error` | tentativi fatti e ultimo errore |
| `queued_at`, `next_attempt_at`, `finished_at` | date UTC |
| `claim_token`, `claimed_until` | presa in carico del worker |

Riaccodare lo stesso percorso non crea una seconda riga: la riporta a
`pending` con le nuove larghezze e i tentativi a zero.

## Il worker

Il task `wonder.media-resize` («Ridimensionamento immagini»,
`Tasks\MediaResizeTask`) è sempre attivo, gira ogni minuto e lavora per 40
secondi al massimo, 50 righe per giro, sotto il `NamedLock`
`wonder.media-resize`.

- **Presa in carico.** Un `UPDATE` condizionato scrive un token sulla riga:
  due processi non lavorano mai lo stesso file. Il tentativo si conta qui.
- **Worker ucciso.** Una presa in carico più vecchia di 10 minuti torna
  libera. Se è già al terzo tentativo la riga chiude `failed`: un'immagine
  che esaurisce la memoria non riparte all'infinito.
- **Errori.** Si riprova dopo 1 e 5 minuti; al terzo errore la riga è
  `failed` con il messaggio in `error`. Un formato non gestito chiude subito.
- **File sparito.** Se l'originale non c'è più (cancellato o sostituito
  prima del giro) la riga chiude `failed` con `File sorgente non trovato.`,
  senza riprovare e senza comparire tra i guasti della home.
- **Riaccodata a metà.** Se il file viene ricaricato mentre lo si lavora, la
  chiusura non trova più il suo token: la riga resta `pending` e il giro
  dopo rifà le misure.

Il risultato del task riporta `released`, `ready`, `retried`, `failed` e
`missing`; `skipped: image_resizes_missing` se la tabella non c'è.

### Da terminale

```bash
php forge media:resize            # svuota la coda adesso
php forge media:resize --failed   # prima rimette in coda i falliti
```

Il comando non ha limite di tempo e usa lo stesso lock del task. Le righe
che aspettano un nuovo tentativo restano al loro orario.

### Nella home del backend

La voce `media_resize` (`Wonder\Backend\Home\MediaResizeStatus`) mostra la
card **Immagini** solo se c'è qualcosa da guardare: ridimensionamenti falliti
negli ultimi 7 giorni, oppure una riga in coda da più di 15 minuti. La vede
chi può aprire l'elenco delle pianificazioni
(`ScheduleResource::allows($authorities, 'list')`), a cui porta il link. Si
sostituisce o si toglie come le altre voci di
[`HomeStatus`](moduli/sistema.md).

## Da codice

```php
use Wonder\App\Media\ResizeQueue;

// Accoda; false = coda non utilizzabile, ridimensiona tu.
if (!ResizeQueue::enqueue($path, [480, 960, 1440], webp: true)) {
    imageResize($path, [480, 960, 1440], true);
}

ResizeQueue::status($path); // 'pending' | 'ready' | 'failed' | null (mai accodato)
ResizeQueue::error($path);  // ultimo errore, o null
```

| Metodo | Cosa fa |
| --- | --- |
| `enqueue($absolutePath, $widths, $webp, $quality = null, $deleteWhenReady = [])` | Accoda il file (percorso assoluto sotto `ROOT`). Ritorna `false` se la coda non è utilizzabile |
| `status($absolutePath)` / `error($absolutePath)` | Stato e ultimo errore della riga |
| `process($store, $resize, $context, $now, $limit, $seconds)` | Un giro della coda, con i contatori |
| `drain($context, $seconds, $limit)` | `process()` sotto lock, usato dal task e dal comando |
| `queueable($resize)` | Vero se il `resize` di un campo può aspettare la coda |
| `resize($absolutePath, $widths, $webp, $quality)` | Genera le misure adesso; solleva un'eccezione se non riesce |

`$deleteWhenReady` è una lista di percorsi assoluti sotto
`ROOT/assets/upload/` da cancellare quando la riga diventa `ready`: serve a
togliere le misure del file vecchio solo dopo che quelle del nuovo esistono.
I percorsi fuori dalla cartella degli upload si scartano all'accodamento e si
ricontrollano, anche con `realpath`, prima di cancellare. Se la riga fallisce
i file restano dove sono.

La coda sta dietro l'interfaccia `ResizeStore`: `DatabaseResizeStore` per il
sito, `MemoryResizeStore` per i test.

```php
$store = new MemoryResizeStore(gmdate('Y-m-d H:i:s')); // scheduler vivo
ResizeQueue::useStore($store);
ResizeQueue::useRoot($cartellaTemporanea);
```

Test: `php tests/App/Media/ResizeQueueTest.php`.
