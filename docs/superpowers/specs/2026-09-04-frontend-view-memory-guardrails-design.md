# Guardrail e misura della memoria nel rendering frontend

- **Data:** 2026-09-04
- **Repo:** `wonder-image/app` (framework)
- **Stato:** design approvato, in attesa di piano di implementazione
- **Approccio scelto:** A — Guardrail + misura (posizione preventiva, nessun sintomo concreto)

## Contesto e problema

Domanda di partenza: *il frontend, tramite le view, può causare un enorme consumo di RAM? Possiamo alleggerirlo?*

Non c'è ancora un errore concreto (`memory_limit exhausted`): è una revisione **preventiva** del layer view/render prima che le tabelle crescano.

### Cosa NON è il problema

Il layer view in sé è leggero. `class/View/View.php` è un sistema a `include` + output buffering; il renderer immagini responsive (`class/Themes/Concerns/RendersResponsiveImage.php`) costruisce `srcset`/`sizes`/`<picture>` come **stringhe** e non decodifica pixel (nessun `getimagesize`, `imagecreate`, `file_get_contents`). Il lavoro pesante sulle immagini è all'upload, non a video.

### Cosa È il rischio (la catena di picco)

Il consumo *enorme* nasce a monte del template, dove ogni stadio tiene viva l'intera collezione contemporaneamente:

| Stadio | Cosa fa | Evidenza | Costo RAM |
|---|---|---|---|
| Query | `Model::all()` non passa `LIMIT` e usa `fetch_all(MYSQLI_ASSOC)` → intero result set in un array | `class/App/Model.php:524`, `class/Sql/Query.php:445` | N righe in RAM |
| Decorazione | `decorateRows()` avvolge ogni riga con `array_map` in un oggetto/modello | `class/App/Model.php:603` | +N oggetti (raddoppio) |
| Albero Element | Gallery/Swiper/Table creano un oggetto PHP per item, tutti compresenti | `class/Elements/Media/Gallery.php` | +N Element |
| Reflection | `new ReflectionMethod` per slide dentro il loop | `class/Themes/Concerns/HandlesMedia.php:96` | overhead ×N |
| Buffer annidati | ogni `View::component()` tiene la sua stringa HTML accumulata | `class/View/View.php:33` | copie parziali coesistenti |

**Picco ≈ righe grezze + oggetti decorati + albero Element + buffer HTML, tutti vivi insieme.** Il rischio scala col **volume dei dati**, non con la complessità del template: invisibile con poche righe, critico a decine di migliaia.

## Obiettivo

Dare visibilità al consumo di memoria del rendering frontend e introdurre guardrail a basso rischio, **senza** riscrivere la pipeline, così che ogni ottimizzazione futura sia guidata da evidenza e non da ipotesi.

## Non-obiettivi (fuori scope, rimandati)

- **Approccio B (render in streaming):** `echo` incrementale con flush a blocchi. Rimandato: si valuta solo se la misura mostra un hotspot nel rendering stesso.
- **Approccio C (dati lazy + iteratori):** query con generatore/unbuffered e Element renderizzati da iteratore. Rimandato: cambia il contratto Model/Sql/Query, da fare solo su evidenza.
- Nessun cambiamento di comportamento in produzione. Nessuna paginazione forzata automatica.

## Decisioni prese

1. **Gate di debug condiviso.** La logica di `RouteDispatcher::debugEnabled()` (`class/Http/RouteDispatcher.php:391`) viene estratta in `Wonder\App\Debug::enabled()`; `RouteDispatcher` vi delega. Una sola fonte di verità per `APP_DEBUG` (`1/true/on/yes`) + fallback localhost (`REMOTE_ADDR ∈ {127.0.0.1, ::1}` o `SERVER_NAME ∈ {127.0.0.1, localhost}`).
2. **Sink di log:** `error_log()` (sempre disponibile; nessun logger stringa generico esiste — `__log` richiede un `Throwable`). Riga singola strutturata.
3. **Soglie via env**, con default:
   - `MEMORY_PROFILE_THRESHOLD_MB` (default `128`) — sopra → livello `WARN`, sotto → `INFO`.
   - `MEMORY_PROFILE_ROWS_THRESHOLD` (default `500`) — un fetch oltre questo conteggio viene annotato come "heavy".

## Design

Tre componenti, tutti **dev-gated o behavior-neutral** → zero rischio di regressione sui siti che consumano il framework.

### Componente 1 — `Wonder\App\Debug` (helper condiviso)

- **Cosa fa:** espone `Debug::enabled(): bool`, con il corpo estratto fedelmente da `debugEnabled()` (incluso il fallback localhost). Risultato memoizzato in una static per evitare rivalutazioni.
- **Come si usa:** `Debug::enabled()` da qualunque punto del framework.
- **Dipende da:** `$_ENV`/`$_SERVER` (`APP_DEBUG`, `REMOTE_ADDR`, `SERVER_NAME`).
- **Integrazione:** `RouteDispatcher::debugEnabled()` diventa un semplice `return Debug::enabled();` — comportamento identico.
- **File:** nuovo `class/App/Debug.php`; modifica `class/Http/RouteDispatcher.php`.

### Componente 2 — `Wonder\App\Diagnostics\MemoryProfiler` (osservabilità dev-only)

- **Cosa fa:** a fine di ogni request frontend, se `Debug::enabled()`, emette una riga:
  `[MEM][WARN] /prodotti peak=182.4MB heaviest=Wonder\App\Models\...\Product::all():12043`
- **API:**
  - `register(): void` — se debug attivo e non già registrato, aggancia `report()` con `register_shutdown_function`. Idempotente.
  - `noteQuery(string $model, int $rows): void` — registra un fetch solo se `rows >= MEMORY_PROFILE_ROWS_THRESHOLD` (limita anche la memoria del profiler stesso). No-op se debug spento.
  - `report(): void` — legge `memory_get_peak_usage(true)`, sceglie il fetch "heaviest" (max righe), determina il livello confrontando con `MEMORY_PROFILE_THRESHOLD_MB`, scrive via `error_log()` con `REQUEST_URI`.
- **Come si usa:** `APP_DEBUG=1` (o localhost) → si legge il log (PHP error log). Soglie regolabili via env.
- **Confine:** tutto lo stato di misura vive qui; gli altri componenti si limitano a emettere eventi.
- **Dipende da:** `Debug`, funzioni memoria PHP, `$_SERVER['REQUEST_URI']`, `$_ENV`.
- **Integrazione:** `MemoryProfiler::register()` chiamato alla fine di `app/bootstrap/frontend.php`.
- **File:** nuovo `class/App/Diagnostics/MemoryProfiler.php`; modifica `app/bootstrap/frontend.php`.

### Componente 3 — Warning "heavy fetch" in `Model::all()`

- **Cosa fa:** subito dopo il fetch in `Model::all()`, se `Debug::enabled()`, chiama `MemoryProfiler::noteQuery(static::class, count($rows))`. In produzione: un solo check booleano memoizzato, **nessun** cambiamento di comportamento né di valore di ritorno.
- **Perché:** fa emergere il pattern "carica tutto" nominando model + conteggio, senza alterare il contratto.
- **Confine:** il Model emette un evento; la decisione su soglia/log è del profiler.
- **File:** modifica `class/App/Model.php` (metodo `all()`, riga ~524; `getAll()` delega già ad `all()`).

### Componente 4 — Cache reflection in `HandlesMedia::renderSlideContent`

- **Cosa fa:** sostituisce `new ReflectionMethod($slide, 'render')` per-slide con una cache statica per-classe di `['public' => bool, 'params' => bool]`, keyed su `get_class($slide)`. La reflection dipende dalla classe, non dall'istanza, quindi il risultato è riusabile.
- **Perché:** elimina N allocazioni di `ReflectionMethod` per ogni gallery/swiper. Guadagno di overhead per-item (CPU + allocazioni minori), **non** la leva grossa sulla memoria — ma corretto, gratuito ed è nel path media già in lavorazione.
- **Comportamento:** identico (refactor a equivalenza).
- **File:** modifica `class/Themes/Concerns/HandlesMedia.php` (`renderSlideContent`, riga ~93).

### Componente 5 — Convenzione documentata

- **Regola:** il codice frontend usa `find(..., $limit)`/paginazione per liste rivolte all'utente, **mai** `all()`/`getAll()` non limitato.
- **Dove:** `docs/app/*` (sezione performance/memoria) e rimando nelle guide skill wi-app/wi-site.
- **File:** nuovo/aggiornato doc sotto `docs/app/`.

## Criteri di successo

1. Su una request dev (`APP_DEBUG=1` o localhost) compare **una riga di report memoria** per pagina frontend nel PHP error log.
2. Un `all()` non limitato su tabella oltre soglia **fa emergere un warning** che nomina model + conteggio righe.
3. Gallery/swiper **non allocano più** una `ReflectionMethod` per slide (verificabile: rendering di N slide dello stesso tipo → 1 sola reflection per classe).
4. Con `APP_DEBUG` spento e non-localhost, i componenti 2–3 sono **inerti** (nessun `error_log`, un solo check booleano).
5. La regola anti-`all()` è documentata in `docs/` e nelle skill.

## Testing

- **`Debug::enabled()`** — unit: matrice `APP_DEBUG` (`1/true/on/yes/0/vuoto/bool`) × `REMOTE_ADDR`/`SERVER_NAME` localhost e non. Verifica parità con il vecchio `debugEnabled()`.
- **`MemoryProfiler`** — unit: `noteQuery` ignora sotto-soglia e registra sopra-soglia; `report()` sceglie il fetch con più righe e sceglie `WARN`/`INFO` rispetto a `MEMORY_PROFILE_THRESHOLD_MB`; no-op con debug spento (nessuna scrittura). Sink iniettabile/catturabile nei test invece di `error_log()` diretto.
- **`Model::all()` hook** — unit: con debug spento nessuna chiamata a `noteQuery` e ritorno invariato; con debug acceso e righe > soglia, `noteQuery` invocato con `static::class` e conteggio corretto.
- **`HandlesMedia`** — unit: due render dello stesso tipo di slide → una sola costruzione di reflection (spia via contatore o classe di test); parità di output con il comportamento attuale per slide public/non-public e con/senza parametro `$theme`.
- **Regressione framework:** `php -l` su ogni file toccato; `composer dumpautoload` per le nuove classi; validazione da un sito con `php forge update --local` e `php forge start` (i componenti toccano bootstrap e Model).

## Elenco file

**Nuovi**
- `class/App/Debug.php`
- `class/App/Diagnostics/MemoryProfiler.php`
- doc performance/memoria sotto `docs/app/`

**Modificati**
- `class/Http/RouteDispatcher.php` — `debugEnabled()` delega a `Debug::enabled()`
- `app/bootstrap/frontend.php` — `MemoryProfiler::register()`
- `class/App/Model.php` — hook `noteQuery` in `all()`
- `class/Themes/Concerns/HandlesMedia.php` — cache reflection in `renderSlideContent`

## Note di integrazione

- Il runtime reale vive nei **siti** che installano il framework sotto `vendor/wonder-image/app`; la registrazione dello shutdown avviene nel bootstrap frontend eseguito dal sito.
- Nessuna delle modifiche richiede migrazioni DB o cambi di config lato sito. `MEMORY_PROFILE_*` sono env opzionali con default sicuri.
