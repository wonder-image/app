# Frontend Memory Guardrails — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dare visibilità al consumo di memoria del rendering frontend e introdurre guardrail a basso rischio, senza riscrivere la pipeline delle view.

**Architecture:** Un gate di debug condiviso (`Wonder\App\Debug`) alimenta un profiler dev-only (`Wonder\App\Diagnostics\MemoryProfiler`) agganciato allo shutdown della request frontend; `Model::all()` emette un evento "heavy fetch" verso il profiler; il renderer media (`HandlesMedia`) sostituisce la reflection per-slide con una cache per-classe. Tutto è dev-gated o a equivalenza di comportamento → zero rischio in produzione.

**Tech Stack:** PHP (framework `wonder-image/app`, autoload `Wonder\ => class/`); test come script PHP standalone via `tests/harness.php`.

## Global Constraints

- Package `wonder-image/app`; autoload PSR-4 `Wonder\\ => class/`. Il runtime reale vive nei siti che installano il framework sotto `vendor/wonder-image/app`.
- **Nessun cambiamento di comportamento in produzione.** Componenti profiler/hook inerti con debug spento; refactor `HandlesMedia` a equivalenza.
- Gate di debug (valori verbatim): `APP_DEBUG ∈ {1, true, on, yes}` (case-insensitive, trim) → true; altrimenti fallback localhost: `REMOTE_ADDR ∈ {127.0.0.1, ::1}` **oppure** `SERVER_NAME ∈ {127.0.0.1, localhost}`.
- Env con default: `MEMORY_PROFILE_THRESHOLD_MB` = `128`; `MEMORY_PROFILE_ROWS_THRESHOLD` = `500`.
- Sink di log: `error_log()` (iniettabile nei test via `MemoryProfiler::setSink()`).
- Test: script PHP standalone che fanno `require vendor/autoload.php` + `require tests/harness.php`, usano `check(nome, fn)` e chiudono con `summary()`; si eseguono con `php tests/.../XTest.php`.
- **`tests/` è in `.gitignore` ma i test sono tracciati:** ogni nuovo file di test va aggiunto con `git add -f`.
- Lint: `php -l` su ogni file PHP toccato. `composer dumpautoload` dopo aver aggiunto nuove classi.
- Branch di lavoro: `feat/frontend-memory-guardrails` (creato in Task 0). Commit per-task; l'utente ha chiesto che tutto atterri insieme → si valuta lo squash al merge (vedi Execution Handoff).

---

### Task 0: Setup branch e baseline

**Files:**
- Nessun sorgente. Versiona spec + piano già scritti.

- [ ] **Step 1: Crea il branch di lavoro dal main aggiornato**

Run:
```bash
git checkout main
git checkout -b feat/frontend-memory-guardrails
```

- [ ] **Step 2: Committa spec e piano come baseline**

```bash
git add docs/superpowers/specs/2026-09-04-frontend-view-memory-guardrails-design.md \
        docs/superpowers/plans/2026-09-04-frontend-memory-guardrails.md
git commit -m "docs: spec e piano guardrail memoria frontend

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 1: `Wonder\App\Debug` — gate di debug condiviso

Estrae la logica di `RouteDispatcher::debugEnabled()` in un helper unico, così profiler e dispatcher non la duplicano.

**Files:**
- Create: `class/App/Debug.php`
- Modify: `class/Http/RouteDispatcher.php:391-411` (metodo `debugEnabled()` → delega)
- Test: `tests/App/DebugTest.php`

**Interfaces:**
- Consumes: nulla.
- Produces:
  - `Wonder\App\Debug::enabled(): bool` — memoizzato per request.
  - `Wonder\App\Debug::reset(): void` — azzera la memoizzazione (uso nei test).

- [ ] **Step 1: Scrivi il test che fallisce**

Create `tests/App/DebugTest.php`:
```php
<?php
/** php tests/App/DebugTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Debug;

/** Prepara un ambiente deterministico e azzera la memoizzazione. */
function withEnv(array $env, array $server): void {
    foreach (['APP_DEBUG'] as $k) { unset($_ENV[$k], $_SERVER[$k]); }
    foreach (['REMOTE_ADDR', 'SERVER_NAME'] as $k) { unset($_SERVER[$k]); }
    foreach ($env as $k => $v) { $_ENV[$k] = $v; }
    foreach ($server as $k => $v) { $_SERVER[$k] = $v; }
    Debug::reset();
}

check('APP_DEBUG=1 abilita', function () {
    withEnv(['APP_DEBUG' => '1'], []);
    return Debug::enabled() === true;
});

check('APP_DEBUG=true abilita (case-insensitive)', function () {
    withEnv(['APP_DEBUG' => 'TRUE'], []);
    return Debug::enabled() === true;
});

check('APP_DEBUG=on/yes abilitano', function () {
    withEnv(['APP_DEBUG' => 'on'], []);
    $a = Debug::enabled();
    withEnv(['APP_DEBUG' => 'yes'], []);
    $b = Debug::enabled();
    return $a === true && $b === true;
});

check('APP_DEBUG bool true abilita', function () {
    withEnv(['APP_DEBUG' => true], []);
    return Debug::enabled() === true;
});

check('APP_DEBUG=0 senza localhost disabilita', function () {
    withEnv(['APP_DEBUG' => '0'], ['REMOTE_ADDR' => '203.0.113.5', 'SERVER_NAME' => 'example.test']);
    return Debug::enabled() === false;
});

check('nessun APP_DEBUG + REMOTE_ADDR 127.0.0.1 abilita', function () {
    withEnv([], ['REMOTE_ADDR' => '127.0.0.1']);
    return Debug::enabled() === true;
});

check('nessun APP_DEBUG + SERVER_NAME localhost abilita', function () {
    withEnv([], ['SERVER_NAME' => 'localhost']);
    return Debug::enabled() === true;
});

check("reset() rilegge l'ambiente", function () {
    withEnv(['APP_DEBUG' => '1'], []);
    $first = Debug::enabled();
    $_ENV['APP_DEBUG'] = '0';
    unset($_SERVER['REMOTE_ADDR'], $_SERVER['SERVER_NAME']);
    Debug::reset();
    $second = Debug::enabled();
    return $first === true && $second === false;
});

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/App/DebugTest.php`
Expected: FAIL — `Error: Class "Wonder\App\Debug" not found` (o riga `✗`), exit code 1.

- [ ] **Step 3: Implementa `Wonder\App\Debug`**

Create `class/App/Debug.php`:
```php
<?php

namespace Wonder\App;

/**
 * Gate di debug condiviso: unica fonte di verità per APP_DEBUG + fallback
 * localhost. Estratto da RouteDispatcher::debugEnabled() per evitare la
 * duplicazione tra dispatcher e diagnostica.
 */
final class Debug
{
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        $env = $_ENV['APP_DEBUG'] ?? ($_SERVER['APP_DEBUG'] ?? null);

        if (is_bool($env)) {
            return self::$enabled = $env;
        }

        $envValue = strtolower(trim((string) $env));

        if (in_array($envValue, ['1', 'true', 'on', 'yes'], true)) {
            return self::$enabled = true;
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $serverName = trim((string) ($_SERVER['SERVER_NAME'] ?? ''));

        return self::$enabled =
            in_array($remoteAddr, ['127.0.0.1', '::1'], true)
            || in_array($serverName, ['127.0.0.1', 'localhost'], true);
    }

    /** Azzera la memoizzazione (uso nei test). */
    public static function reset(): void
    {
        self::$enabled = null;
    }
}
```

- [ ] **Step 4: Rigenera l'autoload**

Run: `composer dumpautoload`
Expected: `Generated autoload files ...`

- [ ] **Step 5: Esegui il test e verifica che passi**

Run: `php tests/App/DebugTest.php`
Expected: PASS — `8 test, 0 falliti`, exit code 0.

- [ ] **Step 6: Fai delegare `RouteDispatcher::debugEnabled()` a `Debug`**

In `class/Http/RouteDispatcher.php`, sostituisci l'intero corpo del metodo `debugEnabled()` (righe ~391-411) con:
```php
    private function debugEnabled(): bool
    {
        return \Wonder\App\Debug::enabled();
    }
```

- [ ] **Step 7: Lint dei file toccati**

Run:
```bash
php -l class/App/Debug.php
php -l class/Http/RouteDispatcher.php
```
Expected: `No syntax errors detected` per entrambi.

- [ ] **Step 8: Commit**

```bash
git add -f tests/App/DebugTest.php
git add class/App/Debug.php class/Http/RouteDispatcher.php
git commit -m "feat: estrai gate di debug condiviso in Wonder\\App\\Debug

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: `MemoryProfiler` + registrazione nel bootstrap frontend

Profiler dev-only: annota i fetch pesanti e, a fine request, scrive una riga di report memoria.

**Files:**
- Create: `class/App/Diagnostics/MemoryProfiler.php`
- Modify: `app/bootstrap/frontend.php` (aggiunge `MemoryProfiler::register();` in coda)
- Test: `tests/App/Diagnostics/MemoryProfilerTest.php`

**Interfaces:**
- Consumes: `Wonder\App\Debug::enabled()` (Task 1).
- Produces:
  - `MemoryProfiler::register(): void` — aggancia `report()` allo shutdown, una volta, se debug attivo.
  - `MemoryProfiler::noteQuery(string $model, int $rows): void` — annota se debug attivo e `rows >= MEMORY_PROFILE_ROWS_THRESHOLD`.
  - `MemoryProfiler::report(): void` — emette una riga via sink; no-op se debug spento.
  - `MemoryProfiler::formatReport(float $peakBytes, string $uri, ?array $heaviest, float $thresholdMb): string` — pura.
  - `MemoryProfiler::setSink(?callable $sink): void` — inietta un sink di cattura (test); `null` = `error_log`.
  - `MemoryProfiler::reset(): void` — azzera note, flag di registrazione e sink (test).

- [ ] **Step 1: Scrivi il test che fallisce**

Create `tests/App/Diagnostics/MemoryProfilerTest.php`:
```php
<?php
/** php tests/App/Diagnostics/MemoryProfilerTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Debug;
use Wonder\App\Diagnostics\MemoryProfiler;

function debugOn(): void {
    $_ENV['APP_DEBUG'] = '1';
    unset($_ENV['MEMORY_PROFILE_ROWS_THRESHOLD'], $_SERVER['MEMORY_PROFILE_ROWS_THRESHOLD']);
    Debug::reset();
    MemoryProfiler::reset();
}

function debugOff(): void {
    $_ENV['APP_DEBUG'] = '0';
    unset($_SERVER['REMOTE_ADDR'], $_SERVER['SERVER_NAME']);
    Debug::reset();
    MemoryProfiler::reset();
}

check('noteQuery ignora sotto soglia righe', function () {
    debugOn();
    MemoryProfiler::noteQuery('App\\Models\\Product', 499); // default soglia 500
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    return count($captured) === 1 && str_contains($captured[0], 'heaviest=-');
});

check('noteQuery registra sopra soglia e report nomina il fetch', function () {
    debugOn();
    MemoryProfiler::noteQuery('App\\Models\\Product', 12043);
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    return count($captured) === 1
        && str_contains($captured[0], 'App\\Models\\Product::all():12043');
});

check('report sceglie il fetch con piu righe', function () {
    debugOn();
    MemoryProfiler::noteQuery('App\\Models\\A', 800);
    MemoryProfiler::noteQuery('App\\Models\\B', 5000);
    MemoryProfiler::noteQuery('App\\Models\\C', 1200);
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    return str_contains($captured[0], 'App\\Models\\B::all():5000');
});

check('soglia righe configurabile via env', function () {
    $_ENV['APP_DEBUG'] = '1';
    $_ENV['MEMORY_PROFILE_ROWS_THRESHOLD'] = '100';
    Debug::reset();
    MemoryProfiler::reset();
    MemoryProfiler::noteQuery('App\\Models\\Small', 150);
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    unset($_ENV['MEMORY_PROFILE_ROWS_THRESHOLD']);
    return str_contains($captured[0], 'App\\Models\\Small::all():150');
});

check('report no-op con debug spento', function () {
    debugOff();
    MemoryProfiler::noteQuery('App\\Models\\Product', 99999);
    $captured = [];
    MemoryProfiler::setSink(function (string $l) use (&$captured) { $captured[] = $l; });
    MemoryProfiler::report();
    return $captured === [];
});

check('formatReport livello WARN sopra soglia MB', function () {
    $line = MemoryProfiler::formatReport(200 * 1048576, '/prodotti', null, 128.0);
    return str_contains($line, '[MEM][WARN]')
        && str_contains($line, '/prodotti')
        && str_contains($line, 'peak=200.0MB')
        && str_contains($line, 'heaviest=-');
});

check('formatReport livello INFO sotto soglia MB', function () {
    $line = MemoryProfiler::formatReport(64 * 1048576, '/home', ['model' => 'App\\Models\\X', 'rows' => 700], 128.0);
    return str_contains($line, '[MEM][INFO]')
        && str_contains($line, 'App\\Models\\X::all():700');
});

check('formatReport uri vuoto diventa trattino', function () {
    $line = MemoryProfiler::formatReport(10 * 1048576, '', null, 128.0);
    return str_contains($line, '] - peak=');
});

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/App/Diagnostics/MemoryProfilerTest.php`
Expected: FAIL — `Class "Wonder\App\Diagnostics\MemoryProfiler" not found`, exit code 1.

- [ ] **Step 3: Implementa `MemoryProfiler`**

Create `class/App/Diagnostics/MemoryProfiler.php`:
```php
<?php

namespace Wonder\App\Diagnostics;

use Wonder\App\Debug;

/**
 * Osservabilità dev-only del consumo di memoria per request frontend.
 * Inerte in produzione (gate Debug::enabled()).
 * Vedi docs/app/concetti/performance-memoria.md.
 */
final class MemoryProfiler
{
    /** @var list<array{model: string, rows: int}> */
    private static array $queryNotes = [];

    private static bool $registered = false;

    /** @var (callable(string): void)|null Sink iniettabile; null => error_log. */
    private static $sink = null;

    /** Aggancia report() allo shutdown, una sola volta, se debug attivo. */
    public static function register(): void
    {
        if (self::$registered || !Debug::enabled()) {
            return;
        }

        self::$registered = true;

        register_shutdown_function([self::class, 'report']);
    }

    /** Annota un fetch pesante (>= soglia righe). No-op se debug spento. */
    public static function noteQuery(string $model, int $rows): void
    {
        if (!Debug::enabled() || $rows < self::rowsThreshold()) {
            return;
        }

        self::$queryNotes[] = ['model' => $model, 'rows' => $rows];
    }

    /** Emette una riga di report memoria. No-op se debug spento. */
    public static function report(): void
    {
        if (!Debug::enabled()) {
            return;
        }

        $line = self::formatReport(
            (float) memory_get_peak_usage(true),
            (string) ($_SERVER['REQUEST_URI'] ?? ''),
            self::heaviest(),
            self::memThresholdMb()
        );

        $sink = self::$sink ?? static fn (string $l): mixed => error_log($l);

        $sink($line);
    }

    /**
     * Formattazione pura (testabile senza stato globale).
     *
     * @param array{model: string, rows: int}|null $heaviest
     */
    public static function formatReport(
        float $peakBytes,
        string $uri,
        ?array $heaviest,
        float $thresholdMb
    ): string {
        $peakMb = $peakBytes / 1048576;
        $level = $peakMb >= $thresholdMb ? 'WARN' : 'INFO';
        $heaviestLabel = $heaviest === null
            ? '-'
            : sprintf('%s::all():%d', $heaviest['model'], $heaviest['rows']);

        return sprintf(
            '[MEM][%s] %s peak=%.1fMB heaviest=%s',
            $level,
            $uri === '' ? '-' : $uri,
            $peakMb,
            $heaviestLabel
        );
    }

    /** Inietta un sink di cattura nei test; null ripristina error_log. */
    public static function setSink(?callable $sink): void
    {
        self::$sink = $sink;
    }

    /** Azzera lo stato (uso nei test). */
    public static function reset(): void
    {
        self::$queryNotes = [];
        self::$registered = false;
        self::$sink = null;
    }

    /** @return array{model: string, rows: int}|null */
    private static function heaviest(): ?array
    {
        $heaviest = null;

        foreach (self::$queryNotes as $note) {
            if ($heaviest === null || $note['rows'] > $heaviest['rows']) {
                $heaviest = $note;
            }
        }

        return $heaviest;
    }

    private static function rowsThreshold(): int
    {
        $raw = $_ENV['MEMORY_PROFILE_ROWS_THRESHOLD']
            ?? ($_SERVER['MEMORY_PROFILE_ROWS_THRESHOLD'] ?? null);
        $value = (int) $raw;

        return $value > 0 ? $value : 500;
    }

    private static function memThresholdMb(): float
    {
        $raw = $_ENV['MEMORY_PROFILE_THRESHOLD_MB']
            ?? ($_SERVER['MEMORY_PROFILE_THRESHOLD_MB'] ?? null);
        $value = (float) $raw;

        return $value > 0 ? $value : 128.0;
    }
}
```

- [ ] **Step 4: Rigenera l'autoload**

Run: `composer dumpautoload`
Expected: `Generated autoload files ...`

- [ ] **Step 5: Esegui il test e verifica che passi**

Run: `php tests/App/Diagnostics/MemoryProfilerTest.php`
Expected: PASS — `8 test, 0 falliti`, exit code 0.

- [ ] **Step 6: Registra il profiler nel bootstrap frontend**

In `app/bootstrap/frontend.php`, aggiungi in coda al file (dopo il blocco `Wonder\App\Dependencies::...`):
```php

\Wonder\App\Diagnostics\MemoryProfiler::register();
```

- [ ] **Step 7: Lint dei file toccati**

Run:
```bash
php -l class/App/Diagnostics/MemoryProfiler.php
php -l app/bootstrap/frontend.php
```
Expected: `No syntax errors detected` per entrambi.

- [ ] **Step 8: Commit**

```bash
git add -f tests/App/Diagnostics/MemoryProfilerTest.php
git add class/App/Diagnostics/MemoryProfiler.php app/bootstrap/frontend.php
git commit -m "feat: profiler memoria dev-only per request frontend

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Hook "heavy fetch" in `Model::all()`

Emette un evento verso il profiler quando `all()` materializza molte righe. Il gating (debug + soglia) vive interamente in `noteQuery`, quindi la modifica al Model è una singola riga incondizionata.

> **Nota di testing:** `Model::all()` richiede una connessione DB (`static::query()` → `new Query(static::connection())`), mentre l'harness del repo è DB-free by design. La correttezza dell'evento è garantita dagli unit test di `MemoryProfiler::noteQuery` (Task 2); qui si verifica che la chiamata sia presente, non alteri il ritorno, e superi `php -l`. La validazione end-to-end è manuale da un sito (Step 5).

**Files:**
- Modify: `class/App/Model.php` (import + una riga nel metodo `all()`, righe ~524-536)

**Interfaces:**
- Consumes: `MemoryProfiler::noteQuery(string, int)` (Task 2).
- Produces: nessuna nuova firma pubblica.

- [ ] **Step 1: Aggiungi l'import**

In `class/App/Model.php`, nel blocco `use` in cima (dopo `use Wonder\App\Support\SyncSchema;`, riga ~9), aggiungi:
```php
use Wonder\App\Diagnostics\MemoryProfiler;
```

- [ ] **Step 2: Emetti l'evento in `all()`**

In `class/App/Model.php`, metodo `all()`, tra l'assegnazione di `$rows` e il `return`. Il metodo diventa:
```php
    public static function all(string|array $columns = '*'): array
    {
        $rows = static::query()->Select(
            static::$table,
            static::queryCondition(),
            null,
            null,
            null,
            $columns
        )->row;

        MemoryProfiler::noteQuery(static::class, is_array($rows) ? count($rows) : 0);

        return (array) static::decorateRows($rows);
    }
```

- [ ] **Step 3: Lint**

Run: `php -l class/App/Model.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Riesegui il test del profiler (nessuna regressione sul contratto dell'evento)**

Run: `php tests/App/Diagnostics/MemoryProfilerTest.php`
Expected: PASS — `8 test, 0 falliti`.

- [ ] **Step 5: Validazione integrazione da un sito (manuale)**

Da un sito che installa il framework, con `APP_DEBUG=1`:
```bash
php forge update --local
php forge start
```
Apri una pagina frontend che lista molti record e verifica nel PHP error log una riga `[MEM][...] <uri> peak=... heaviest=<Model>::all():<N>`.
Expected: la riga compare; nessun errore/notice nuovo. (Se non hai un sito a portata, salta e annota che la validazione E2E è pendente.)

- [ ] **Step 6: Commit**

```bash
git add class/App/Model.php
git commit -m "feat: Model::all() segnala i fetch pesanti al profiler

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Cache reflection in `HandlesMedia::renderSlideContent`

Sostituisce `new ReflectionMethod($slide, 'render')` per-slide con una cache statica per-classe. Refactor a equivalenza.

**Files:**
- Modify: `class/Themes/Concerns/HandlesMedia.php` (nuova proprietà statica + metodo `renderSlideContent`, righe ~93-119)
- Test: `tests/Themes/Concerns/HandlesMediaReflectionCacheTest.php`

**Interfaces:**
- Consumes: nulla.
- Produces: nessuna nuova firma pubblica (comportamento identico di `renderSlideContent`).

- [ ] **Step 1: Scrivi il test che fallisce**

Create `tests/Themes/Concerns/HandlesMediaReflectionCacheTest.php`:
```php
<?php
/** php tests/Themes/Concerns/HandlesMediaReflectionCacheTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

/** Espone il metodo protetto e la dimensione della cache per-classe. */
final class HandlesMediaProbe
{
    use \Wonder\Themes\Concerns\HandlesMedia;

    public function callRenderSlideContent(mixed $slide, string $theme): string
    {
        return $this->renderSlideContent($slide, $theme);
    }

    public static function cacheSize(): int
    {
        return count(self::$renderSignatureCache);
    }
}

final class SlidePublicNoParam
{
    public function render(): string { return 'NOPARAM'; }
}

final class SlidePublicWithParam
{
    public function render(string $theme): string { return 'THEME:' . $theme; }
}

final class SlidePrivateRender
{
    private function render(): string { return 'SEGRETO'; }
}

$probe = new HandlesMediaProbe();

check('slide public senza parametro', function () use ($probe) {
    return $probe->callRenderSlideContent(new SlidePublicNoParam(), 'wonder') === 'NOPARAM';
});

check('slide public con parametro theme', function () use ($probe) {
    return $probe->callRenderSlideContent(new SlidePublicWithParam(), 'wonder') === 'THEME:wonder';
});

check('render privato produce stringa vuota', function () use ($probe) {
    return $probe->callRenderSlideContent(new SlidePrivateRender(), 'wonder') === '';
});

check('stringa passa inalterata', function () use ($probe) {
    return $probe->callRenderSlideContent('ciao', 'wonder') === 'ciao';
});

check('int/float diventano stringa', function () use ($probe) {
    return $probe->callRenderSlideContent(42, 'wonder') === '42'
        && $probe->callRenderSlideContent(3.5, 'wonder') === '3.5';
});

check('cache deduplica per classe (2 render stessa classe = 1 entry)', function () use ($probe) {
    // Classi già viste finora: SlidePublicNoParam, SlidePublicWithParam,
    // SlidePrivateRender = 3 classi distinte con render().
    $probe->callRenderSlideContent(new SlidePublicNoParam(), 'wonder'); // ri-render, no nuova entry
    $probe->callRenderSlideContent(new SlidePublicWithParam(), 'bootstrap');
    return HandlesMediaProbe::cacheSize() === 3;
});

summary();
```

- [ ] **Step 2: Esegui il test e verifica che fallisca**

Run: `php tests/Themes/Concerns/HandlesMediaReflectionCacheTest.php`
Expected: FAIL — errore su `self::$renderSignatureCache` inesistente (`Access to undeclared static property`), o `cacheSize` che non torna 3, exit code 1.

- [ ] **Step 3: Aggiungi la proprietà di cache**

In `class/Themes/Concerns/HandlesMedia.php`, subito dopo `use HasAttributes;` (riga ~11), aggiungi:
```php

        /** @var array<class-string, array{public: bool, params: bool}> */
        private static array $renderSignatureCache = [];
```

- [ ] **Step 4: Refactor di `renderSlideContent` per usare la cache**

In `class/Themes/Concerns/HandlesMedia.php`, sostituisci il metodo `renderSlideContent` (righe ~93-119) con:
```php
        protected function renderSlideContent(mixed $slide, string $theme): string
        {
            if (is_object($slide) && method_exists($slide, 'render')) {
                $class = $slide::class;

                if (!isset(self::$renderSignatureCache[$class])) {
                    $method = new ReflectionMethod($slide, 'render');

                    self::$renderSignatureCache[$class] = [
                        'public' => $method->isPublic(),
                        'params' => $method->getNumberOfParameters() > 0,
                    ];
                }

                $signature = self::$renderSignatureCache[$class];

                if (!$signature['public']) {
                    return '';
                }

                $rendered = $signature['params']
                    ? $slide->render($theme)
                    : $slide->render();

                return is_string($rendered) || $rendered instanceof Stringable
                    ? (string) $rendered
                    : '';
            }

            if (is_string($slide) || is_int($slide) || is_float($slide) || $slide instanceof Stringable) {
                return (string) $slide;
            }

            return '';
        }
```

- [ ] **Step 5: Esegui il test e verifica che passi**

Run: `php tests/Themes/Concerns/HandlesMediaReflectionCacheTest.php`
Expected: PASS — `6 test, 0 falliti`, exit code 0.

- [ ] **Step 6: Non-regressione dei media esistenti**

Run: `php tests/Elements/Media/GalleryTest.php && php tests/Elements/Media/SwiperTest.php`
Expected: entrambi `PASS` / exit code 0 (l'output degli slide non cambia).

- [ ] **Step 7: Lint**

Run: `php -l class/Themes/Concerns/HandlesMedia.php`
Expected: `No syntax errors detected`.

- [ ] **Step 8: Commit**

```bash
git add -f tests/Themes/Concerns/HandlesMediaReflectionCacheTest.php
git add class/Themes/Concerns/HandlesMedia.php
git commit -m "perf: cache per-classe della reflection negli slide media

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Documentazione — regola anti-`all()` e lettura del profiler

**Files:**
- Create: `docs/app/concetti/performance-memoria.md`

**Interfaces:**
- Consumes: i comportamenti dei Task 1-4.
- Produces: convenzione documentata per siti e moduli.

- [ ] **Step 1: Scrivi il documento**

Create `docs/app/concetti/performance-memoria.md`:
```markdown
# Memoria e performance del rendering frontend

## La regola: mai `Model::all()` non limitato sul frontend

`Model::all()` / `Model::getAll()` NON applicano `LIMIT` e caricano l'intero
result set in memoria (`fetch_all`), poi `decorateRows()` avvolge ogni riga in
un oggetto: il picco di memoria raddoppia col numero di righe. Su tabelle che
crescono è la causa #1 di `Allowed memory size exhausted`.

Per liste rivolte all'utente usa sempre un limite/paginazione:

    // ❌ evita sul frontend
    $prodotti = Product::all();

    // ✅ preferisci
    $prodotti = Product::find($condizione, $limit, $order, $direction);

## Il profiler di memoria (dev-only)

Con `APP_DEBUG=1` (o su localhost) ogni request frontend scrive nel PHP error
log una riga:

    [MEM][WARN] /prodotti peak=182.4MB heaviest=App\Models\Product::all():12043

- `WARN` quando il picco supera `MEMORY_PROFILE_THRESHOLD_MB` (default 128), `INFO` sotto.
- `heaviest` nomina il `Model::all()` che ha restituito più righe oltre
  `MEMORY_PROFILE_ROWS_THRESHOLD` (default 500), oppure `-` se nessuno.

Il profiler è inerte con debug spento: nessun log, un solo check booleano.

## Env

| Variabile | Default | Effetto |
|---|---|---|
| `APP_DEBUG` | (spento) | Attiva profiler e warning heavy-fetch. |
| `MEMORY_PROFILE_THRESHOLD_MB` | `128` | Soglia MB per il livello `WARN`. |
| `MEMORY_PROFILE_ROWS_THRESHOLD` | `500` | Righe oltre cui un fetch è "pesante". |
```

- [ ] **Step 2: Commit**

```bash
git add docs/app/concetti/performance-memoria.md
git commit -m "docs: regola anti-all() e uso del profiler memoria

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review (svolta dall'autore del piano)

**1. Copertura spec:**
- Componente 1 (Debug helper) → Task 1 ✅
- Componente 2 (MemoryProfiler) → Task 2 ✅
- Componente 3 (hook `Model::all()`) → Task 3 ✅
- Componente 4 (cache reflection HandlesMedia) → Task 4 ✅
- Componente 5 (convenzione documentata) → Task 5 ✅
- Criteri di successo 1-5 → coperti da Task 2 (report per request), Task 3 (warning heavy-fetch), Task 4 (cache per-classe provata da `cacheSize()===3`), Task 2/3 (inerte con debug off), Task 5 (doc). ✅
- Testing spec: Debug matrix, MemoryProfiler unit, HandlesMedia equivalenza+cache → presenti. Il test DB del wiring `all()` è dichiarato non applicabile (harness DB-free) con validazione manuale da sito — deviazione motivata, non una lacuna. ✅

**2. Placeholder scan:** nessun TBD/TODO; ogni step di codice mostra codice reale; comandi con output atteso. ✅

**3. Coerenza dei tipi:** `Debug::enabled()/reset()`, `MemoryProfiler::noteQuery(string,int)/report()/formatReport(float,string,?array,float)/setSink(?callable)/reset()`, la proprietà `self::$renderSignatureCache` con forma `array{public:bool,params:bool}` — usate in modo coerente tra Task e test. La firma `formatReport` nei test (Task 2) combacia con l'implementazione. ✅
```
