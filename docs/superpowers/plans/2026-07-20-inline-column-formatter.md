# Inline Column Formatter Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettere `TableColumn::formatter(Closure)` inline nel `tableSchema()`, risolvendo la closure lato server tramite chiave derivata `{slug}.{colonna}`, e riscrivere la lista immobili con formatter inline + immagine in 2ª colonna.

**Architecture:** `Column::formatter` accetta `string|Closure`. `ColumnFormatterRegistry` acquisisce uno scan lazy per-request (`ensureResources()`) che registra le closure inline delle resource sotto `{slug}.{colonna}` (come `ColumnFunctionRegistry::fromResources`). `ResourceTableRenderer::legacyColumnFormat()` emette la chiave derivata (stringa) per le closure, così sul giro AJAX viaggia solo la stringa; il dispatch in `Field` è invariato.

**Tech Stack:** PHP 8.2, framework `wonder-image/app`, modulo `wonder-image/immobili` (path-symlinked nel sito `immobili-site`), DataTables SSP.

## Global Constraints

- Test = **script PHP semplici** (no PHPUnit): `require vendor/autoload.php`, helper `eq()`, `exit(0)` se passano; si eseguono con `php tests/<path>Test.php` dalla root del repo. Output pristine (niente warning/notice).
- **Le closure non attraversano il giro AJAX**: `legacyColumnFormat` deve emettere una **stringa** (chiave derivata), mai la closure. La chiave derivata è **`{slug}.{colonna}`** (`$resourceClass::slug().'.'.$columnName`).
- Retro-compatibilità: `->formatter('nome')` (stringa) + `boot.files` continua a funzionare identico.
- Modulo `immobili`: modifiche **non committate** (le committa l'utente). Framework: commit su branch dedicato → merge `main` → push (outward-facing, conferma utente) → `composer update` nel sito.
- `php -l` su ogni file PHP toccato; `composer dump-autoload` per classi nuove.

---

### Task 1: `Column::formatter(string|Closure)` (framework)

**Files:**
- Modify: `class/Elements/Table/Column.php` (metodo `formatter`)
- Test: `tests/App/ResourceSchema/TableColumnFormatterClosureTest.php`

**Interfaces:**
- Produces: `Column::formatter(string|Closure $formatter): self` — salva in `schema['formatter']` (stringa trimmata, oppure la Closure così com'è). Ereditato da `TableColumn`.

- [ ] **Step 1: Write the failing test**

Create `tests/App/ResourceSchema/TableColumnFormatterClosureTest.php`:

```php
<?php
/** php tests/App/ResourceSchema/TableColumnFormatterClosureTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) { $fail++; echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  got: " . var_export($got, true) . "\n"; }
    else { echo "ok: $label\n"; }
}

// stringa: invariato
$s = TableColumn::key('prezzo')->formatter('immobili.prezzo')->toArray();
eq('string stored', $s['formatter'] ?? null, 'immobili.prezzo');

// closure: memorizzata così com'è
$fn = static fn (array $row): string => 'X';
$c = TableColumn::key('prezzo')->formatter($fn)->toArray();
eq('closure stored', ($c['formatter'] ?? null) instanceof Closure, true);
eq('closure is same', $c['formatter'] === $fn, true);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/App/ResourceSchema/TableColumnFormatterClosureTest.php`
Expected: FAIL — `TypeError` (formatter(): Argument #1 must be of type string, Closure given).

- [ ] **Step 3: Widen the signature in `class/Elements/Table/Column.php`**

Sostituire il metodo esistente:

```php
    public function formatter(string $name): self
    {
        return $this->schema('formatter', trim($name));
    }
```

con:

```php
    public function formatter(string|\Closure $formatter): self
    {
        return $this->schema('formatter', is_string($formatter) ? trim($formatter) : $formatter);
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/App/ResourceSchema/TableColumnFormatterClosureTest.php`
Expected: PASS — `ALL PASS`.

- [ ] **Step 5: Commit**

```bash
git add class/Elements/Table/Column.php tests/App/ResourceSchema/TableColumnFormatterClosureTest.php
git commit -m "feat(table): Column::formatter accetta anche una Closure inline"
```

---

### Task 2: `ColumnFormatterRegistry` — scan resource per closure inline (framework)

**Files:**
- Modify: `class/Backend/Table/ColumnFormatterRegistry.php`
- Test: `tests/Backend/Table/ColumnFormatterFromResourcesTest.php`

**Interfaces:**
- Consumes: `Column::formatter(Closure)` (Task 1), `Wonder\App\ResourceRegistry::classes()`.
- Produces: `ColumnFormatterRegistry::registerFromResource(string $resourceClass): void` (registra le closure inline della resource sotto `{slug}.{colonna}`); `has()`/`call()` ora innescano uno scan lazy per-request di tutte le resource; `reset()` azzera anche lo stato di scan.

- [ ] **Step 1: Write the failing test**

Create `tests/Backend/Table/ColumnFormatterFromResourcesTest.php`:

```php
<?php
/** php tests/Backend/Table/ColumnFormatterFromResourcesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;
use Wonder\Backend\Table\ColumnFormatterRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got: $g\n"; }
    else { echo "ok: $label\n"; }
}

// resource fittizia duck-typed (slug + tableSchema con formatter closure)
final class WiTestFmtResource {
    public static function slug(): string { return 'testfmt'; }
    public static function tableSchema(): array {
        return [
            TableColumn::key('prezzo')->formatter(static fn (array $row): string => 'X-' . ($row['prezzo'] ?? '')),
            TableColumn::key('nome')->text(), // niente formatter: ignorata
        ];
    }
}

ColumnFormatterRegistry::reset();

// la registrazione esplicita per nome resta
ColumnFormatterRegistry::register('x.named', static fn (array $r): string => 'N');
eq('named still works', ColumnFormatterRegistry::call('x.named', []), 'N');

// scan di una resource: closure inline registrata sotto {slug}.{colonna}
ColumnFormatterRegistry::registerFromResource(WiTestFmtResource::class);
eq('inline registered under derived key', ColumnFormatterRegistry::has('testfmt.prezzo'), true);
eq('inline runs with row', ColumnFormatterRegistry::call('testfmt.prezzo', ['prezzo' => 100]), 'X-100');
eq('column without formatter not registered', ColumnFormatterRegistry::has('testfmt.nome'), false);
eq('unknown derived key -> empty', ColumnFormatterRegistry::call('testfmt.mancante', []), '');

// reset azzera tutto
ColumnFormatterRegistry::reset();
eq('reset clears', ColumnFormatterRegistry::has('testfmt.prezzo'), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/Backend/Table/ColumnFormatterFromResourcesTest.php`
Expected: FAIL — `Call to undefined method ...::registerFromResource()`.

- [ ] **Step 3: Modify `class/Backend/Table/ColumnFormatterRegistry.php`**

Sostituire l'intero file con:

```php
<?php

namespace Wonder\Backend\Table;

use Wonder\App\ResourceRegistry;

/**
 * Registry dei formatter di colonna invocabili via TableColumn::formatter().
 * Il nome viaggia nel POST di list-table (giro DataTables): il registry è la
 * whitelist — un nome non registrato non viene mai invocato. Le callable vivono
 * solo server-side (mai serializzate).
 *
 * Oltre alla registrazione esplicita per nome (boot.files), acquisisce lazy le
 * closure inline dichiarate nei tableSchema() delle resource, sotto la chiave
 * derivata `{slug}.{colonna}` (come ColumnFunctionRegistry::fromResources).
 */
final class ColumnFormatterRegistry
{
    /** @var array<string, callable> */
    private static array $formatters = [];

    private static bool $scanned = false;

    public static function register(string $name, callable $formatter): void
    {
        $name = trim($name);

        if ($name !== '') {
            self::$formatters[$name] = $formatter;
        }
    }

    /**
     * Registra le closure inline (->formatter(fn)) di una resource sotto la
     * chiave derivata `{slug}.{colonna}`. Duck-typed: usa solo slug() e
     * tableSchema(). Difensivo su schema malformati.
     */
    public static function registerFromResource(string $resourceClass): void
    {
        try {
            $slug = (string) $resourceClass::slug();
            $columns = $resourceClass::tableSchema();
        } catch (\Throwable) {
            return;
        }

        if ($slug === '' || !is_iterable($columns)) {
            return;
        }

        foreach ($columns as $column) {
            if (!is_object($column) || !method_exists($column, 'toArray')) {
                continue;
            }

            $schema = (array) $column->toArray();
            $formatter = $schema['formatter'] ?? null;
            $name = (string) ($schema['name'] ?? '');

            if ($formatter instanceof \Closure && $name !== '') {
                self::register($slug.'.'.$name, $formatter);
            }
        }
    }

    public static function has(string $name): bool
    {
        self::ensureResources();

        return isset(self::$formatters[trim($name)]);
    }

    /** Invoca il formatter registrato; '' se il nome non è registrato. */
    public static function call(string $name, array $row): string
    {
        self::ensureResources();

        $name = trim($name);

        if (!isset(self::$formatters[$name])) {
            return '';
        }

        return (string) (self::$formatters[$name])($row);
    }

    public static function reset(): void
    {
        self::$formatters = [];
        self::$scanned = false;
    }

    /**
     * Scan lazy per-request: registra le closure inline di tutte le resource.
     * `$scanned` è impostato PRIMA dello scan per evitare ricorsione se una
     * tableSchema() tocca il registry.
     */
    private static function ensureResources(): void
    {
        if (self::$scanned) {
            return;
        }

        self::$scanned = true;

        try {
            $classes = ResourceRegistry::classes();
        } catch (\Throwable) {
            return;
        }

        foreach ($classes as $resourceClass) {
            self::registerFromResource((string) $resourceClass);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/Backend/Table/ColumnFormatterFromResourcesTest.php`
Expected: PASS — `ALL PASS`.

- [ ] **Step 5: Verifica nessuna regressione sui formatter per nome esistenti**

Run: `php tests/Backend/Table/ColumnFormatterRegistryTest.php`
Expected: `ALL PASS` (register/has/call/reset per nome invariati).

- [ ] **Step 6: Commit**

```bash
git add class/Backend/Table/ColumnFormatterRegistry.php tests/Backend/Table/ColumnFormatterFromResourcesTest.php
git commit -m "feat(table): ColumnFormatterRegistry scandisce le closure inline delle resource"
```

---

### Task 3: `ResourceTableRenderer` emette la chiave derivata + docs (framework)

**Files:**
- Modify: `class/Backend/Support/ResourceTableRenderer.php` (metodo `legacyColumnFormat`)
- Modify: `docs/app/backend/table-columns.md`
- Test: `tests/Backend/Support/LegacyColumnFormatFormatterTest.php`

**Interfaces:**
- Consumes: `$this->slug` (private), `$config['name']`, `$config['formatter']` (string|Closure).
- Produces: `$format['formatter']` è **sempre una stringa** — la chiave derivata `{slug}.{colonna}` se il formatter era una Closure; la stringa trimmata se era già un nome.

- [ ] **Step 1: Write the failing test**

Create `tests/Backend/Support/LegacyColumnFormatFormatterTest.php`:

```php
<?php
/** php tests/Backend/Support/LegacyColumnFormatFormatterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Support\ResourceTableRenderer;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    if ($got !== $expected) { $fail++; echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  got: " . var_export($got, true) . "\n"; }
    else { echo "ok: $label\n"; }
}

$rc = new ReflectionClass(ResourceTableRenderer::class);
$renderer = $rc->newInstanceWithoutConstructor();
$slugProp = $rc->getProperty('slug'); $slugProp->setAccessible(true); $slugProp->setValue($renderer, 'testfmt');
$m = $rc->getMethod('legacyColumnFormat'); $m->setAccessible(true);

// closure -> chiave derivata (stringa), mai la closure
$outClosure = $m->invoke($renderer, ['name' => 'prezzo', 'type' => 'text', 'formatter' => static fn (array $r): string => 'X']);
eq('closure -> derived key string', $outClosure['formatter'] ?? null, 'testfmt.prezzo');
eq('formatter is string not closure', is_string($outClosure['formatter'] ?? null), true);

// stringa -> invariata
$outString = $m->invoke($renderer, ['name' => 'prezzo', 'type' => 'text', 'formatter' => 'immobili.prezzo']);
eq('string formatter unchanged', $outString['formatter'] ?? null, 'immobili.prezzo');

// nessun formatter -> chiave assente
$outNone = $m->invoke($renderer, ['name' => 'prezzo', 'type' => 'text']);
eq('no formatter key', array_key_exists('formatter', $outNone), false);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/Backend/Support/LegacyColumnFormatFormatterTest.php`
Expected: FAIL — `closure -> derived key string` fallisce (il ramo attuale gestisce solo `is_string`, quindi con una Closure `formatter` non viene impostato → `null`). Se `legacyColumnFormat` usa proprietà `$this` non inizializzate oltre `slug`, riportarlo come NEEDS_CONTEXT.

- [ ] **Step 3: Modify `legacyColumnFormat` in `class/Backend/Support/ResourceTableRenderer.php`**

Sostituire il blocco esistente:

```php
        if (isset($config['formatter']) && is_string($config['formatter']) && trim($config['formatter']) !== '') {
            $format['formatter'] = trim($config['formatter']);
        }
```

con:

```php
        if (isset($config['formatter'])) {
            if ($config['formatter'] instanceof \Closure) {
                // Chiave derivata: sul giro AJAX viaggia solo la stringa, mai la
                // closure (che è risolta server-side da ColumnFormatterRegistry).
                $format['formatter'] = $this->slug.'.'.((string) ($config['name'] ?? ''));
            } elseif (is_string($config['formatter']) && trim($config['formatter']) !== '') {
                $format['formatter'] = trim($config['formatter']);
            }
        }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/Backend/Support/LegacyColumnFormatFormatterTest.php`
Expected: PASS — `ALL PASS`.

- [ ] **Step 5: Verifica regressioni formatter/badge**

Run:
```bash
php tests/Backend/Table/FieldFormatterTest.php
php tests/Backend/Table/FieldBadgeLegacyRemapTest.php
```
Expected: entrambi `ALL PASS`.

- [ ] **Step 6: Documentare la forma inline**

In `docs/app/backend/table-columns.md`, dopo la sezione "Vincolo: nomi, non closure", aggiungere una sezione "Forma inline" che spiega: `->formatter(fn (array $row): string => …)` è supportato; la closure NON viaggia sul filo — il framework la registra sotto la chiave derivata `{slug}.{colonna}` (scan lazy delle resource, come le function), e sul client viaggia solo quella chiave. Aggiornare la frase del vincolo che dice "mai una closure diretta" per chiarire che ora la closure inline è ammessa e risolta server-side.

- [ ] **Step 7: Commit**

```bash
git add class/Backend/Support/ResourceTableRenderer.php docs/app/backend/table-columns.md tests/Backend/Support/LegacyColumnFormatFormatterTest.php
git commit -m "feat(table): renderer emette la chiave derivata per i formatter closure + docs"
```

---

### Task 4: Modulo `immobili` — formatter inline + immagine 2ª colonna

**Files:**
- Modify: `src/Resources/ImmobileResource.php` (private static `formatImage`/`formatNome`, `tableSchema` inline)
- Modify: `module.json` (rimuovere `boot.files`)
- Delete: `config/formatters.php`

**Interfaces:**
- Consumes: framework `Column::formatter(Closure)` (Task 1-3, da consegnare nel sito — Task 5), helper `immobiliListPrice`/`immobiliFormatSurface` (già in `src/helpers.php`), `ImmobilePresenter::coverImage`, `__r`.

- [ ] **Step 1: Aggiungere i formatter privati in `ImmobileResource`**

Aggiungere due metodi privati statici (spostando il markup da `config/formatters.php`). Nota: `Immobili` è già importato; il markup usa `\Wonder\Plugin\Immobili\Services\ImmobilePresenter` fully-qualified (o aggiungere l'`use` in cima):

```php
    /** Miniatura copertina (colonna `image`). '' se assente. */
    private static function formatImage(array $row): string
    {
        $src = (new \Wonder\Plugin\Immobili\Services\ImmobilePresenter())->coverImage($row);
        if ($src === '') {
            return '';
        }
        $src = htmlspecialchars($src, ENT_QUOTES);
        $alt = htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES);
        return "<img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" "
            . "style=\"width:56px;height:42px;object-fit:cover;border-radius:6px;flex:0 0 auto\">";
    }

    /** Nome (riferimento) + sottotitolo + link modifica/visualizza. */
    private static function formatNome(array $row): string
    {
        $id   = (int) ($row['id'] ?? 0);
        $edit = htmlspecialchars((string) __r('backend.resource.immobili.edit', ['id' => $id]), ENT_QUOTES);
        $nome = htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES);

        $tipologia = trim((string) ($row['tipologia_nome'] ?? ''));
        $strada    = ucwords(strtolower(trim((string) ($row['strada'] ?? ''))));
        $indirizzo = trim((string) ($row['indirizzo'] ?? ''));
        $comune    = trim((string) ($row['comune_nome'] ?? ''));

        $via = trim($strada.' '.$indirizzo);
        $loc = trim($via.($comune !== '' ? ', '.$comune : ''), ', ');
        $sub = $tipologia;
        if ($loc !== '') {
            $sub = $sub !== '' ? $sub.' | '.$loc : $loc;
        }
        $sub = htmlspecialchars($sub, ENT_QUOTES);

        $dir  = trim((string) ($row['dir'] ?? ''));
        $view = $dir !== '' ? htmlspecialchars((string) __r('immobili.detail', ['slug' => $dir]), ENT_QUOTES) : '';
        $viewLink = $view !== ''
            ? " <a href=\"{$view}\" target=\"_blank\" rel=\"noopener\" title=\"Visualizza sul sito\""
              . " class=\"text-muted text-decoration-none\"><i class=\"bi bi-box-arrow-up-right\"></i></a>"
            : '';

        return "<span class=\"d-inline-flex align-items-center\">"
            . "<a href=\"{$edit}\" class=\"fw-semibold text-dark text-decoration-none\">{$nome}</a>"
            . $viewLink
            . "</span>"
            . ($sub !== '' ? "<span class=\"d-block text-muted small\">{$sub}</span>" : '');
    }
```

- [ ] **Step 2: Riscrivere `tableSchema()` con formatter inline + immagine 2ª colonna**

Sostituire il corpo di `tableSchema()` con:

```php
    public static function tableSchema(): array
    {
        return [
            TableColumn::key('evidence')->evidenceBadge(true)->badgeVariant('badgeIcon')->label('')->size('little'),
            // Immagine: 2ª colonna (virtuale; il formatter usa $row['id'] per la copertina).
            TableColumn::key('image')->formatter(static fn (array $row): string => self::formatImage($row))->label('')->size('little'),
            TableColumn::key('nome')->formatter(static fn (array $row): string => self::formatNome($row)),
            TableColumn::key('comune_nome')->text()->size('medium'),
            TableColumn::key('prezzo')->formatter(static fn (array $row): string => immobiliListPrice($row)),
            TableColumn::key('superficie')->formatter(static fn (array $row): string => immobiliFormatSurface($row['superficie'] ?? 0))->size('little'),
            TableColumn::key('creation')->date()->sortable(),
            TableColumn::key('sold')->booleanBadge('sold')
                ->badgeOff('In vendita', 'bi bi-tag', 'primary')
                ->badgeOn('Venduto', 'bi bi-check2-circle', 'dark')
                ->size('little'),
            TableColumn::key('visible')->visibleBadge(true)->size('little'),
            TableColumn::key('actions')->button()->actions(['view', 'edit', 'visible', 'evidence', 'delete']),
        ];
    }
```

Note: rimossa la riga `->image()->link('view')` rotta e l'azione `detail_page` (fuori scope). `view` resta (usa la show custom via `customShowViewPath`).

- [ ] **Step 3: Rimuovere `boot.files` da `module.json`**

Togliere la chiave `boot` (il blocco `"boot": { "files": ["config/formatters.php"] }` e la virgola che lo precede).

- [ ] **Step 4: Eliminare il boot file**

```bash
rm /Users/andreamarinoni/Developer/packages/immobili/config/formatters.php
```

- [ ] **Step 5: Lint + validazione**

```bash
php -l /Users/andreamarinoni/Developer/packages/immobili/src/Resources/ImmobileResource.php
php -r '$d=json_decode(file_get_contents("/Users/andreamarinoni/Developer/packages/immobili/module.json"),true); echo (json_last_error()===JSON_ERROR_NONE && !isset($d["boot"]))?"module.json ok, boot rimosso\n":"PROBLEMA\n";'
```
Expected: `No syntax errors detected` + `module.json ok, boot rimosso`.

- [ ] **Step 6: NON committare** (le modifiche modulo le committa l'utente). Riportare i file cambiati come deliverable.

---

### Task 5: Consegna framework + verifica end-to-end

**Files:** operazioni git/composer + harness di verifica.

**Interfaces:**
- Consumes: Task 1-3 committati su branch `packages/app`; Task 4 nel working tree di `packages/immobili`.

- [ ] **Step 1: Merge in `main` e push (CONFERMA UTENTE)**

Il lavoro framework (Task 1-3) sta su un branch dedicato di `packages/app` (es. `feat/inline-column-formatter`). Il push è outward-facing: chiedere conferma esplicita. Se `packages/app` ha WIP non committato (`buttons_custom`), usare stash come nel precedente feature (backup patch → stash → merge → stash pop → verifica identico).
```bash
cd /Users/andreamarinoni/Developer/packages/app
git switch main
git merge --no-ff feat/inline-column-formatter
git push origin main
```

- [ ] **Step 2: Aggiornare il sito**

```bash
cd /Users/andreamarinoni/Developer/boilerplates/immobili-site
composer update wonder-image/app --no-interaction
```

- [ ] **Step 3: Verifica a livello PHP nel contesto del sito**

Harness (bootstrap come wonder-image.php fino a service.php, senza routing) che verifica che i formatter inline immobili si risolvano via chiave derivata e producano output:
```php
<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
$ROOT='/Users/andreamarinoni/Developer/boilerplates/immobili-site'; $GLOBALS['ROOT']=$ROOT; $APP_VERSION='2.1.0';
$ROOT_APP="$ROOT/vendor/wonder-image/app/app"; $ROOT_RESOURCES="$ROOT/vendor/wonder-image/app/resources";
require_once "$ROOT/vendor/autoload.php";
$lg=\Wonder\App\LegacyGlobals::scope(); if (is_array($lg)&&$lg!==[]) extract($lg, EXTR_SKIP);
require_once "$ROOT_APP/function/function.php"; require_once "$ROOT_APP/config/config.php";
\Wonder\App\LegacyGlobals::capture(get_defined_vars()); require_once "$ROOT_APP/service/service.php";
use Wonder\Backend\Table\ColumnFormatterRegistry;
use Wonder\Plugin\Immobili\Models\Immobile;
$row = Immobile::find(['provider'=>'getrix'], 1); $row = is_array($row)&&isset($row['id'])?$row:(is_array($row)?reset($row):[]);
foreach (['immobili.image','immobili.nome','immobili.prezzo','immobili.superficie'] as $k) {
    echo "$k has=".(ColumnFormatterRegistry::has($k)?'1':'0')." out=".substr(strip_tags(ColumnFormatterRegistry::call($k,$row)),0,40)."\n";
}
```
Expected: tutte `has=1`; `immobili.prezzo`/`immobili.superficie` producono `€ …`/`… mq`; `immobili.image` un `<img>`; `immobili.nome` il riferimento. (Le chiavi derivate si risolvono via `ensureResources()` senza `boot.files`.)

- [ ] **Step 4: Verifica VISIVA (utente)**

L'utente ricarica la lista immobili backend loggato e conferma: immagine in 2ª colonna, nome senza miniatura, prezzo/mq formattati, e che l'aggiornamento AJAX (ordina «Inserimento» / cerca) mantenga i formatter inline.

---

## Self-Review

**Spec coverage:** `Column::formatter(string|Closure)` (T1) ✓; `ColumnFormatterRegistry::registerFromResource`/scan lazy (T2) ✓; `legacyColumnFormat` emette chiave derivata (T3) ✓; dispatch `Field` invariato (usa la stringa, coperto da T3 regressione) ✓; docs (T3) ✓; immobili inline + immagine 2ª colonna + rimozione boot.files/config (T4) ✓; consegna + e2e (T5) ✓. Retro-compat nome (T2 step 5) ✓.

**Placeholder scan:** nessun TBD/TODO; codice completo in ogni step. Il branch name del framework (`feat/inline-column-formatter`) è indicato in T5.

**Type consistency:** `formatter(string|\Closure): self`; `registerFromResource(string): void`; chiave derivata `{slug}.{colonna}` identica in T2 (registrazione) e T3 (emissione); `ensureResources()`/`$scanned` coerenti; `has()/call()` firme invariate.
