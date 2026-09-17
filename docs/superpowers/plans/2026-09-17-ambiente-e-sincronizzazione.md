# Ambiente e sincronizzazione — Piano di implementazione (1 di 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** dare al core una nozione di ambiente (`APP_ENV`), un sync tra locale e produzione con `id` stabili, tabelle modificabili solo in locale e righe precaricate dichiarate dai moduli ed eseguite da `forge update` in locale.

**Architecture:** classi pure e testabili senza database (`Environment`, `SyncImportPlan`, `ModuleDependencySorter`, `DefaultRows::missingRows`) più modifiche mirate alle classi esistenti (`SyncSchema`, `TableSync`, `UpdateRunner`, `Resource`, controller e presenter del backend, endpoint API generici). Tutto è aggiuntivo: le tabelle che non dichiarano `keepIds()` o `localOnly()` non cambiano comportamento.

**Tech Stack:** PHP 8.2, `wonder-image/app` (Composer, PSR-4 `Wonder\` → `class/`), test come script PHP con `tests/harness.php`.

**Spec:** `docs/superpowers/specs/2026-09-16-prerequisiti-moduli-gestionale-design.md` (parti A, B, C, D). Piani successivi: 2 — transazioni, "Guida", classi fiscali (F, G, H); 3 — sedi della società (E).

## Global Constraints

- Repo `wonder-image/app`, ramo `feature/prerequisiti-moduli-gestionale`. Non toccare `vendor-static/xml-sitemaps/data/generator.conf` (modifica preesistente non nostra).
- `APP_ENV` con valori `local` o `production`; se manca o non è valida vale `production`.
- Il `.env` appartiene ai boilerplate: il core legge `APP_ENV`, `forge start` la scrive, i boilerplate la dichiarano in `.env.example`.
- Tabelle senza `keepIds()` e senza `localOnly()`: comportamento attuale invariato.
- Righe cancellate delle tabelle con `keepIds()`: `deleted = 'true'`, mai eliminate.
- Avviso di sola lettura: `Si modifica in locale e si pubblica con il deploy.`
- In produzione i passi "defaults" ed "export" di `UpdateRunner` non partono mai.
- Test: `php tests/<percorso>.php` dalla radice del repo; ogni file PHP toccato passa `php -l`; `composer dump-autoload` dopo nuove classi.
- Commit in inglese, brevi, chiusi da `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- Documentazione in `docs/app/` aggiornata nello stesso lavoro (attività 11).

## Mappa dei file

| File | Azione | Responsabilità |
|---|---|---|
| `class/App/Environment.php` | nuovo | ambiente da `APP_ENV` |
| `class/Console/Commands/LocalEnvironmentCommand.php` | modifica | `APP_ENV=local` nel template `.env` |
| `class/Console/Commands/LocalStart.php` | modifica | completa `APP_ENV=local` se manca |
| `class/App/Support/SyncSchema.php` | modifica | opzioni `keepIds()` e `localOnly()` |
| `class/App/Support/SyncImportPlan.php` | nuovo | piano puro dell'import con `id` stabili |
| `class/App/Support/TableSync.php` | modifica | export ordinato, import con piano, `exportToFile()` |
| `class/App/Module/ModuleDependencySorter.php` | nuovo | ordine dei moduli per dipendenze |
| `class/App/Module/Contracts/ModuleDefaults.php` | nuovo | contratto delle righe precaricate |
| `class/App/Support/DefaultRows.php` | nuovo | inserimento idempotente delle righe precaricate |
| `class/App/Module/Manifest.php` | modifica | `defaultsClass()` |
| `class/App/Module/ManifestValidator.php` | modifica | validazione di `database.defaults` |
| `class/App/UpdateRunner.php` | modifica | passi import, defaults, export |
| `app/build/update/css.php` | modifica | tolto l'import del sync |
| `class/App/Resource.php` | modifica | `isReadonly()`, `readonlyNotice()`, `deleteRecord()`, `exportSyncData()` |
| `class/App/Support/SyncedTables.php` | nuovo | sola lettura ed export per nome di tabella |
| `class/App/ResourceRouteRegistrar.php` | modifica | niente route di modifica in sola lettura |
| `class/Backend/Support/ResourcePageController.php` | modifica | guardia, cancellazione, export |
| `class/Api/Support/ResourceApiController.php` | modifica | guardia, cancellazione, export |
| `app/http/api/backend/{delete,authority,change-boolean,delete-icon,file-move,file-delete,move}.php` | modifica | guardia di sola lettura (ed export dove si modifica la riga) |
| `class/Backend/Support/ResourcePagePresenter.php` | modifica | campi disabilitati, dati di sola lettura per le viste |
| `class/Backend/Support/ResourceTableRenderer.php` | modifica | niente "Aggiungi" né azioni di modifica in sola lettura |
| `app/view/pages/backend/resource/form.php`, `list.php` | modifica | avviso e nessun salvataggio |
| `class/App/Resources/Css/CssFontResource.php`, `CssColorResource.php`, `class/App/Resources/Support/CssSingleton.php` | modifica | tolto `autoExport()` manuale |
| `tests/App/EnvironmentTest.php`, `tests/App/Support/SyncSchemaTest.php`, `tests/App/Support/SyncImportPlanTest.php`, `tests/App/Module/ModuleDependencySorterTest.php`, `tests/App/Support/DefaultRowsTest.php`, `tests/App/ResourceReadonlyTest.php` | nuovi | test senza database |
| `docs/app/...` | modifica | documentazione (attività 11) |

---

### Task 1: Ambiente dell'applicazione

**Files:**
- Create: `class/App/Environment.php`
- Modify: `class/Console/Commands/LocalEnvironmentCommand.php` (template in `defaultEnvTemplate()`)
- Modify: `class/Console/Commands/LocalStart.php` (seconda chiamata a `completeEnvValues`)
- Test: `tests/App/EnvironmentTest.php`

**Interfaces:**
- Produces: `Wonder\App\Environment::current(): string`, `::isLocal(): bool`, `::isProduction(): bool`, `::reset(): void`, costanti `LOCAL = 'local'`, `PRODUCTION = 'production'`.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/App/EnvironmentTest.php`:

```php
<?php
/** php tests/App/EnvironmentTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Environment;

function withAppEnv(?string $value): void {
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');

    if ($value !== null) {
        $_ENV['APP_ENV'] = $value;
    }

    Environment::reset();
}

check('senza APP_ENV vale production', function () {
    withAppEnv(null);
    return Environment::current() === 'production' && Environment::isProduction() && !Environment::isLocal();
});

check('APP_ENV=local', function () {
    withAppEnv('local');
    return Environment::current() === 'local' && Environment::isLocal() && !Environment::isProduction();
});

check('maiuscole e spazi normalizzati', function () {
    withAppEnv('  LOCAL ');
    return Environment::isLocal();
});

check('valore non valido vale production', function () {
    withAppEnv('staging');
    return Environment::current() === 'production';
});

check('letto anche da $_SERVER', function () {
    withAppEnv(null);
    $_SERVER['APP_ENV'] = 'local';
    Environment::reset();
    return Environment::isLocal();
});

check('reset() rilegge il valore', function () {
    withAppEnv('local');
    $first = Environment::isLocal();
    $_ENV['APP_ENV'] = 'production';
    Environment::reset();
    return $first === true && Environment::isProduction();
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/App/EnvironmentTest.php`
Expected: errore fatale `Class "Wonder\App\Environment" not found`.

- [ ] **Step 3: Implementare la classe**

`class/App/Environment.php`:

```php
<?php

namespace Wonder\App;

/**
 * Ambiente dell'applicazione letto da `APP_ENV` (`local` o `production`).
 *
 * Se la variabile manca o non è valida vale `production`: nel dubbio le
 * tabelle modificabili solo in locale restano in sola lettura. Il `.env`
 * appartiene al sito (boilerplate); `forge start` scrive `APP_ENV=local`.
 */
final class Environment
{
    public const LOCAL = 'local';
    public const PRODUCTION = 'production';

    private static ?string $current = null;

    public static function current(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $value = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return self::$current = in_array($value, [self::LOCAL, self::PRODUCTION], true)
            ? $value
            : self::PRODUCTION;
    }

    public static function isLocal(): bool
    {
        return self::current() === self::LOCAL;
    }

    public static function isProduction(): bool
    {
        return self::current() === self::PRODUCTION;
    }

    /** Azzera la memoizzazione (uso nei test). */
    public static function reset(): void
    {
        self::$current = null;
    }
}
```

- [ ] **Step 4: Scrivere `APP_ENV` nel `.env` locale**

In `class/Console/Commands/LocalEnvironmentCommand.php`, dentro `defaultEnvTemplate()`, sostituire:

```
# App Info
APP_DEBUG=true
```

con:

```
# App Info
APP_ENV=local
APP_DEBUG=true
```

In `class/Console/Commands/LocalStart.php` sostituire:

```php
        $updatedKeys = array_merge($updatedKeys, $this->completeEnvValues($lines, $keyToIndex, [
            'APP_KEY' => bin2hex(random_bytes(32)),
```

con:

```php
        $updatedKeys = array_merge($updatedKeys, $this->completeEnvValues($lines, $keyToIndex, [
            'APP_ENV' => \Wonder\App\Environment::LOCAL,
            'APP_KEY' => bin2hex(random_bytes(32)),
```

(`completeEnvValues` senza `overwrite` scrive solo se la chiave manca o è vuota.)

- [ ] **Step 5: Eseguire test e lint**

Run: `composer dump-autoload -q && php tests/App/EnvironmentTest.php && php -l class/App/Environment.php && php -l class/Console/Commands/LocalEnvironmentCommand.php && php -l class/Console/Commands/LocalStart.php`
Expected: `6 test, 0 falliti` e `No syntax errors detected` per i tre file.

- [ ] **Step 6: Commit**

```bash
git add class/App/Environment.php class/Console/Commands/LocalEnvironmentCommand.php class/Console/Commands/LocalStart.php tests/App/EnvironmentTest.php
git commit -m "Add APP_ENV environment detection

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 2: Opzioni `keepIds()` e `localOnly()` di `SyncSchema`

**Files:**
- Modify: `class/App/Support/SyncSchema.php` (intero file)
- Test: `tests/App/Support/SyncSchemaTest.php`

**Interfaces:**
- Produces: `SyncSchema::$keepIds` (bool, readonly), `SyncSchema::$localOnly` (bool, readonly), `->keepIds(): self`, `->localOnly(): self`; `->exclude()` conserva i due flag.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/App/Support/SyncSchemaTest.php`:

```php
<?php
/** php tests/App/Support/SyncSchemaTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\SyncSchema;

check('multiRow di default senza flag', function () {
    $schema = SyncSchema::multiRow();
    return $schema->singleton === false && $schema->keepIds === false && $schema->localOnly === false;
});

check('keepIds() e localOnly() impostano i flag', function () {
    $schema = SyncSchema::multiRow()->keepIds()->localOnly();
    return $schema->keepIds === true && $schema->localOnly === true && $schema->singleton === false;
});

check('le opzioni restituiscono nuove istanze', function () {
    $base = SyncSchema::multiRow();
    $kept = $base->keepIds();
    return $base !== $kept && $base->keepIds === false;
});

check('exclude() conserva i flag e viceversa', function () {
    $a = SyncSchema::multiRow()->keepIds()->localOnly()->exclude(['secret']);
    $b = SyncSchema::multiRow()->exclude(['secret'])->keepIds()->localOnly();
    return $a->keepIds && $a->localOnly && $a->excludeColumns === ['secret']
        && $b->keepIds && $b->localOnly && $b->excludeColumns === ['secret'];
});

check('singleton conserva singleton con le opzioni', function () {
    $schema = SyncSchema::singleton()->localOnly();
    return $schema->singleton === true && $schema->localOnly === true;
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/App/Support/SyncSchemaTest.php`
Expected: errore `Undefined property: Wonder\App\Support\SyncSchema::$keepIds` o `Call to undefined method ...keepIds()`.

- [ ] **Step 3: Riscrivere `SyncSchema`**

Sostituire l'intero contenuto di `class/App/Support/SyncSchema.php` con:

```php
<?php

namespace Wonder\App\Support;

/**
 * Descrive il comportamento di sincronizzazione di una tabella.
 *
 * Ogni Model che vuole partecipare al sistema di export/import via
 * `forge export` / `forge import` deve restituire un'istanza di
 * `SyncSchema` dal metodo `syncSchema()`.
 *
 * Due modalita:
 * - `SyncSchema::singleton()` — la tabella ha una sola riga (id=1)
 * - `SyncSchema::multiRow()` — la tabella ha righe multiple
 *
 * Opzioni (nuove istanze immutabili, componibili):
 * - `->exclude([...])` — colonne escluse dall'export;
 * - `->keepIds()` — export con `id` e `deleted`; import che inserisce o
 *   aggiorna per `id` senza svuotare la tabella e segna `deleted = 'true'`
 *   le righe assenti dal file (per tabelle referenziate da chiavi esterne);
 * - `->localOnly()` — la tabella si modifica solo con `APP_ENV=local`;
 *   altrove le Resource dei suoi Model sono in sola lettura.
 */
final class SyncSchema
{
    public readonly bool $singleton;

    /** @var string[] Colonne escluse dall'export (oltre a quelle di sistema). */
    public readonly array $excludeColumns;

    public readonly bool $keepIds;

    public readonly bool $localOnly;

    private function __construct(
        bool $singleton,
        array $excludeColumns = [],
        bool $keepIds = false,
        bool $localOnly = false,
    ) {
        $this->singleton = $singleton;
        $this->excludeColumns = $excludeColumns;
        $this->keepIds = $keepIds;
        $this->localOnly = $localOnly;
    }

    /**
     * Tabella singleton: esporta solo la riga con id=1.
     */
    public static function singleton(): self
    {
        return new self(singleton: true);
    }

    /**
     * Tabella multi-row: esporta tutte le righe.
     */
    public static function multiRow(): self
    {
        return new self(singleton: false);
    }

    /**
     * Escludi colonne specifiche dall'export (oltre alle colonne di sistema
     * `id`, `last_modified`, `creation`, `deleted`).
     *
     * @param string[] $columns
     */
    public function exclude(array $columns): self
    {
        return new self($this->singleton, $columns, $this->keepIds, $this->localOnly);
    }

    /**
     * Mantieni gli `id` tra gli ambienti (solo tabelle multi-row).
     */
    public function keepIds(): self
    {
        return new self($this->singleton, $this->excludeColumns, true, $this->localOnly);
    }

    /**
     * Tabella modificabile solo con `APP_ENV=local`.
     */
    public function localOnly(): self
    {
        return new self($this->singleton, $this->excludeColumns, $this->keepIds, true);
    }
}
```

- [ ] **Step 4: Eseguire il test**

Run: `php tests/App/Support/SyncSchemaTest.php && php tests/App/Support/SyncTableSorterTest.php`
Expected: `5 test, 0 falliti` e il test esistente del sorter senza fallimenti.

- [ ] **Step 5: Commit**

```bash
git add class/App/Support/SyncSchema.php tests/App/Support/SyncSchemaTest.php
git commit -m "Add keepIds and localOnly sync schema options

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 3: `SyncImportPlan`, piano puro dell'import con `id` stabili

**Files:**
- Create: `class/App/Support/SyncImportPlan.php`
- Test: `tests/App/Support/SyncImportPlanTest.php`

**Interfaces:**
- Produces: `SyncImportPlan::make(array $fileRows, array $existingIds): SyncImportPlan` con proprietà readonly `inserts` (list di righe con `id`), `updates` (array `id => valori senza id`), `softDeletes` (list di `int`), `skipped` (`int`).

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/App/Support/SyncImportPlanTest.php`:

```php
<?php
/** php tests/App/Support/SyncImportPlanTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\SyncImportPlan;

check('righe nuove da inserire con il proprio id', function () {
    $plan = SyncImportPlan::make([['id' => 3, 'code' => 'cash', 'deleted' => 'false']], []);
    return $plan->inserts === [['id' => 3, 'code' => 'cash', 'deleted' => 'false']]
        && $plan->updates === [] && $plan->softDeletes === [] && $plan->skipped === 0;
});

check('righe esistenti da aggiornare senza la colonna id', function () {
    $plan = SyncImportPlan::make([['id' => 1, 'name' => 'Bonifico']], [1]);
    return $plan->inserts === [] && $plan->updates === [1 => ['name' => 'Bonifico']];
});

check('righe assenti dal file da segnare come cancellate', function () {
    $plan = SyncImportPlan::make([['id' => 1, 'name' => 'A']], [1, 2, '5']);
    return $plan->softDeletes === [2, 5];
});

check('righe cancellate nel file restano aggiornate con il loro deleted', function () {
    $plan = SyncImportPlan::make([['id' => 2, 'deleted' => 'true']], [2]);
    return $plan->updates === [2 => ['deleted' => 'true']] && $plan->softDeletes === [];
});

check('file vuoto: tutte le righe esistenti segnate come cancellate', function () {
    $plan = SyncImportPlan::make([], [1, 2]);
    return $plan->inserts === [] && $plan->updates === [] && $plan->softDeletes === [1, 2];
});

check('righe senza id valido o duplicate saltate', function () {
    $plan = SyncImportPlan::make([
        ['name' => 'senza id'],
        ['id' => 0, 'name' => 'zero'],
        ['id' => 4, 'name' => 'prima'],
        ['id' => 4, 'name' => 'duplicato'],
        'non array',
    ], []);
    return $plan->skipped === 4 && $plan->inserts === [['id' => 4, 'name' => 'prima']];
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/App/Support/SyncImportPlanTest.php`
Expected: `Class "Wonder\App\Support\SyncImportPlan" not found`.

- [ ] **Step 3: Implementare la classe**

`class/App/Support/SyncImportPlan.php`:

```php
<?php

namespace Wonder\App\Support;

/**
 * Piano dell'import di una tabella con `SyncSchema::keepIds()`.
 *
 * Classe pura: dato il contenuto del file e gli `id` presenti nel database
 * calcola cosa inserire, cosa aggiornare e cosa segnare come cancellato.
 * `TableSync` esegue il piano; nessuna riga viene mai eliminata.
 */
final class SyncImportPlan
{
    /**
     * @param list<array<string, mixed>> $inserts righe da inserire, con `id`
     * @param array<int, array<string, mixed>> $updates valori per `id`, senza `id`
     * @param list<int> $softDeletes `id` da segnare con `deleted = 'true'`
     */
    private function __construct(
        public readonly array $inserts,
        public readonly array $updates,
        public readonly array $softDeletes,
        public readonly int $skipped,
    ) {
    }

    /**
     * @param array<int, mixed> $fileRows righe del file già ripulite
     * @param array<int, int|string> $existingIds `id` presenti nel database
     */
    public static function make(array $fileRows, array $existingIds): self
    {
        $existing = [];

        foreach ($existingIds as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $existing[$id] = true;
            }
        }

        $inserts = [];
        $updates = [];
        $seen = [];
        $skipped = 0;

        foreach ($fileRows as $row) {
            $id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;

            if ($id <= 0 || isset($seen[$id])) {
                $skipped++;
                continue;
            }

            $seen[$id] = true;

            if (isset($existing[$id])) {
                $values = $row;
                unset($values['id']);
                $updates[$id] = $values;
                continue;
            }

            $inserts[] = $row;
        }

        $softDeletes = [];

        foreach (array_keys($existing) as $id) {
            if (!isset($seen[$id])) {
                $softDeletes[] = $id;
            }
        }

        return new self($inserts, $updates, $softDeletes, $skipped);
    }
}
```

- [ ] **Step 4: Eseguire il test**

Run: `composer dump-autoload -q && php tests/App/Support/SyncImportPlanTest.php`
Expected: `6 test, 0 falliti`.

- [ ] **Step 5: Commit**

```bash
git add class/App/Support/SyncImportPlan.php tests/App/Support/SyncImportPlanTest.php
git commit -m "Add pure sync import plan for stable ids

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 4: `TableSync` con export ordinato, import con `id` stabili ed `exportToFile()`

**Files:**
- Modify: `class/App/Support/TableSync.php`

**Interfaces:**
- Consumes: `SyncSchema::$keepIds` (Task 2), `SyncImportPlan::make()` (Task 3).
- Produces: `TableSync::exportToFile(string $root, ?string $file = null): bool` (usato dal Task 8); import delle tabelle `keepIds` senza `TRUNCATE`.

Nessun test senza database: `TableSync` legge e scrive tabelle. La logica pura è in `SyncImportPlan` (Task 3); la verifica sul database è nel Task 12.

- [ ] **Step 1: `cleanRow()` con colonne da mantenere**

Sostituire il metodo `cleanRow` con:

```php
    /**
     * Rimuove le colonne di sistema e quelle escluse dallo schema.
     *
     * @param string[] $extraExclude Colonne aggiuntive da escludere.
     * @param string[] $keep Colonne di sistema da mantenere (es. `id`, `deleted`).
     */
    private static function cleanRow(array $row, array $extraExclude = [], array $keep = []): array
    {
        $exclude = array_diff(array_merge(self::SYSTEM_COLUMNS, $extraExclude), $keep);
        $cleaned = [];

        foreach ($row as $column => $value) {
            if (in_array($column, $exclude, true)) {
                continue;
            }

            $cleaned[$column] = $value;
        }

        return $cleaned;
    }

    /**
     * Colonne di sistema mantenute per lo schema (`id` e `deleted` con `keepIds()`).
     *
     * @return string[]
     */
    private static function keptColumns(SyncSchema $schema): array
    {
        return $schema->keepIds && !$schema->singleton ? ['id', 'deleted'] : [];
    }
```

- [ ] **Step 2: Export ordinato per `id` e con `id` stabili**

In `exportConfig()` sostituire il ramo `else` multi-row:

```php
            } else {
                $result = sqlSelect($table);
                $rows = [];

                foreach ($result->row as $row) {
                    $rows[] = self::cleanRow($row, $schema->excludeColumns);
                }
            }
```

con:

```php
            } else {
                $result = sqlSelect($table, null, null, 'id', 'ASC');
                $rows = [];

                foreach ((array) $result->row as $row) {
                    $rows[] = self::cleanRow($row, $schema->excludeColumns, self::keptColumns($schema));
                }
            }
```

- [ ] **Step 3: Import delle tabelle `keepIds()` senza `TRUNCATE`**

In `importConfig()` sostituire:

```php
            } else {
                sqlTruncate($table);

                foreach ($config[$table] as $row) {
```

con:

```php
            } elseif ($schema->keepIds) {
                self::importKeepingIds($table, $config[$table], $schema);
            } else {
                sqlTruncate($table);

                foreach ($config[$table] as $row) {
```

e aggiungere, dopo `importIfExists()`, il metodo:

```php
    /**
     * Import di una tabella con `keepIds()`: inserisce o aggiorna per `id`,
     * segna `deleted = 'true'` le righe assenti dal file, non elimina nulla.
     */
    private static function importKeepingIds(string $table, array $rows, SyncSchema $schema): void
    {
        $fileRows = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $fileRows[] = self::cleanRow($row, $schema->excludeColumns, self::keptColumns($schema));
            }
        }

        $existing = sqlSelect($table, null, null, null, null, 'id');
        $existingIds = array_column((array) ($existing->row ?? []), 'id');
        $plan = SyncImportPlan::make($fileRows, $existingIds);

        foreach ($plan->inserts as $values) {
            sqlInsert($table, $values);
        }

        foreach ($plan->updates as $id => $values) {
            if ($values !== []) {
                sqlModify($table, $values, 'id', $id);
            }
        }

        foreach ($plan->softDeletes as $id) {
            sqlModify($table, ['deleted' => 'true'], 'id', $id);
        }
    }
```

- [ ] **Step 4: `exportToFile()` e `autoExport()` che lo riusa**

Aggiungere prima della sezione `Auto-export`:

```php
    /**
     * Scrive `shared/sync-data.json` (o `$file`) con le tabelle attive.
     * Non riscrive il file se il contenuto non cambia.
     */
    public static function exportToFile(string $root, ?string $file = null): bool
    {
        $root = rtrim($root, '/');

        if ($root === '' || !is_dir($root)) {
            return false;
        }

        $path = $file ?? $root.'/'.self::CONFIG_PATH;
        $json = json_encode(self::exportConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return false;
        }

        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }

        $content = $json."\n";

        if (file_exists($path) && file_get_contents($path) === $content) {
            return true;
        }

        return file_put_contents($path, $content) !== false;
    }
```

e sostituire il corpo di `autoExport()` con:

```php
    public static function autoExport(): void
    {
        try {
            if (!filter_var($_ENV['SYNC_AUTO_EXPORT'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            if (!function_exists('sqlSelect')) {
                return;
            }

            $root = $GLOBALS['ROOT'] ?? '';

            if (!is_string($root) || $root === '') {
                return;
            }

            self::exportToFile($root);
        } catch (\Throwable) {
            // Non-blocking: il salvataggio non deve mai fallire
            // per colpa dell'auto-export.
        }
    }
```

Aggiornare anche il docblock di `importIfExists()`: `Chiamato da UpdateRunner durante forge update.` al posto del riferimento a `build/update/css.php`.

- [ ] **Step 5: Lint e test esistenti**

Run: `php -l class/App/Support/TableSync.php && php tests/App/Support/SyncTableSorterTest.php && php tests/App/Support/SyncImportPlanTest.php`
Expected: `No syntax errors detected` e nessun test fallito.

- [ ] **Step 6: Commit**

```bash
git add class/App/Support/TableSync.php
git commit -m "Import keepIds tables without truncation and add exportToFile

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 5: Import del sync come passo di `UpdateRunner`

**Files:**
- Modify: `class/App/UpdateRunner.php`
- Modify: `app/build/update/css.php`

**Interfaces:**
- Consumes: `TableSync::importIfExists(string $root): bool`.
- Produces: `$result->stats->sync_import` (bool), `stats->defaults` (int), `stats->sync_export` (bool) inizializzati.

- [ ] **Step 1: Statistiche e `use`**

In `UpdateRunner.php` aggiungere dopo `use Wonder\Sql\Connection;`:

```php
use Wonder\App\Support\TableSync;
```

e sostituire il blocco `stats`:

```php
            'stats' => (object) [
                'tables' => 0,
                'rows' => 0,
                'update' => 0,
                'local' => 0,
            ],
```

con:

```php
            'stats' => (object) [
                'tables' => 0,
                'rows' => 0,
                'sync_import' => false,
                'update' => 0,
                'defaults' => 0,
                'sync_export' => false,
                'local' => 0,
            ],
```

- [ ] **Step 2: Passo di import dopo le righe legacy**

Sostituire:

```php
            $result->stats->rows = $this->runFiles($this->rowDirectories());
            $result->stats->update = $this->runFiles($this->updateDirectories());
```

con:

```php
            $result->stats->rows = $this->runFiles($this->rowDirectories());
            $result->stats->sync_import = $this->runSyncImport();
            $result->stats->update = $this->runFiles($this->updateDirectories());
```

e aggiungere dopo `runTables()`:

```php
    /**
     * Importa `shared/sync-data.json` se esiste nella radice del sito.
     */
    private function runSyncImport(): bool
    {
        global $ROOT;

        if (!is_string($ROOT ?? null) || trim($ROOT) === '') {
            return false;
        }

        return TableSync::importIfExists($ROOT);
    }
```

- [ ] **Step 3: Togliere l'import da `css.php`**

In `app/build/update/css.php` rimuovere:

```php
    // Se sync-data.json esiste nel root del progetto, importa la
    // configurazione nel DB prima di rigenerare i CSS. Questo garantisce
    // che ogni ambiente (locale, staging, produzione) generi gli stessi
    // dati partendo dallo stesso source of truth committato in git.
    \Wonder\App\Support\TableSync::importIfExists($ROOT);

```

(l'import avviene ora in `UpdateRunner` prima dei file di update, quindi prima della rigenerazione dei CSS).

- [ ] **Step 4: Lint**

Run: `php -l class/App/UpdateRunner.php && php -l app/build/update/css.php`
Expected: `No syntax errors detected` per entrambi.

- [ ] **Step 5: Commit**

```bash
git add class/App/UpdateRunner.php app/build/update/css.php
git commit -m "Run sync import as an explicit update runner step

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 6: `ModuleDependencySorter`

**Files:**
- Create: `class/App/Module/ModuleDependencySorter.php`
- Test: `tests/App/Module/ModuleDependencySorterTest.php`

**Interfaces:**
- Produces: `ModuleDependencySorter::sort(array $dependencies): array` (`slug => string[]` → `string[]`), `ModuleDependencySorter::sortManifests(array $manifests): array` (`Manifest[]` → `Manifest[]`); `RuntimeException` sui cicli.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/App/Module/ModuleDependencySorterTest.php`:

```php
<?php
/** php tests/App/Module/ModuleDependencySorterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Module\Manifest;
use Wonder\App\Module\ModuleDependencySorter;

check('le dipendenze vengono prima', function () {
    return ModuleDependencySorter::sort([
        'ecommerce' => ['gestionale'],
        'gestionale' => [],
    ]) === ['gestionale', 'ecommerce'];
});

check('moduli indipendenti mantengono l\'ordine', function () {
    return ModuleDependencySorter::sort([
        'rsvp' => [],
        'immobili' => [],
        'blog' => [],
    ]) === ['rsvp', 'immobili', 'blog'];
});

check('dipendenze non presenti nell\'elenco ignorate', function () {
    return ModuleDependencySorter::sort(['ecommerce' => ['gestionale']]) === ['ecommerce'];
});

check('catena a tre livelli', function () {
    return ModuleDependencySorter::sort([
        'c' => ['b'],
        'b' => ['a'],
        'a' => [],
    ]) === ['a', 'b', 'c'];
});

check('ciclo: eccezione leggibile', function () {
    try {
        ModuleDependencySorter::sort(['a' => ['b'], 'b' => ['a']]);
        return false;
    } catch (RuntimeException $exception) {
        return str_contains($exception->getMessage(), 'Dipendenze circolari tra i moduli');
    }
});

check('sortManifests ordina i manifest', function () {
    $manifest = fn (string $slug, array $deps) => Manifest::fromArray(
        '/tmp/'.$slug,
        '/tmp/'.$slug.'/module.json',
        ['slug' => $slug, 'dependencies' => ['modules' => $deps]],
        'test'
    );

    $sorted = ModuleDependencySorter::sortManifests([
        'ecommerce' => $manifest('ecommerce', ['gestionale']),
        'gestionale' => $manifest('gestionale', []),
    ]);

    return array_map(fn (Manifest $m) => $m->slug(), $sorted) === ['gestionale', 'ecommerce'];
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/App/Module/ModuleDependencySorterTest.php`
Expected: `Class "Wonder\App\Module\ModuleDependencySorter" not found`.

- [ ] **Step 3: Implementare la classe**

`class/App/Module/ModuleDependencySorter.php`:

```php
<?php

namespace Wonder\App\Module;

use RuntimeException;

/**
 * Ordina i moduli per dipendenze: le dipendenze prima dei moduli che le
 * richiedono. Tra moduli indipendenti conserva l'ordine di ingresso.
 */
final class ModuleDependencySorter
{
    /**
     * @param array<string, string[]> $dependencies slug => slug delle dipendenze
     * @return string[]
     */
    public static function sort(array $dependencies): array
    {
        $ordered = [];
        $state = [];

        $visit = function (string $slug, array $path) use (&$visit, &$ordered, &$state, $dependencies): void {
            $current = $state[$slug] ?? 0;

            if ($current === 2) {
                return;
            }

            if ($current === 1) {
                throw new RuntimeException(
                    'Dipendenze circolari tra i moduli: '.implode(' → ', [...$path, $slug])
                );
            }

            $state[$slug] = 1;

            foreach ((array) ($dependencies[$slug] ?? []) as $dependency) {
                $dependency = (string) $dependency;

                if (array_key_exists($dependency, $dependencies)) {
                    $visit($dependency, [...$path, $slug]);
                }
            }

            $state[$slug] = 2;
            $ordered[] = $slug;
        };

        foreach (array_keys($dependencies) as $slug) {
            $visit((string) $slug, []);
        }

        return $ordered;
    }

    /**
     * @param array<array-key, Manifest> $manifests
     * @return Manifest[]
     */
    public static function sortManifests(array $manifests): array
    {
        $dependencies = [];
        $bySlug = [];

        foreach ($manifests as $manifest) {
            $bySlug[$manifest->slug()] = $manifest;
            $dependencies[$manifest->slug()] = $manifest->dependencySlugs();
        }

        return array_map(
            static fn (string $slug): Manifest => $bySlug[$slug],
            self::sort($dependencies)
        );
    }
}
```

- [ ] **Step 4: Eseguire il test**

Run: `composer dump-autoload -q && php tests/App/Module/ModuleDependencySorterTest.php`
Expected: `6 test, 0 falliti`.

- [ ] **Step 5: Commit**

```bash
git add class/App/Module/ModuleDependencySorter.php tests/App/Module/ModuleDependencySorterTest.php
git commit -m "Add module dependency sorter

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 7: `DefaultRows`, contratto `ModuleDefaults` e `database.defaults` nel manifest

**Files:**
- Create: `class/App/Module/Contracts/ModuleDefaults.php`
- Create: `class/App/Support/DefaultRows.php`
- Modify: `class/App/Module/Manifest.php` (nuovo metodo dopo `resourcesPath()`)
- Modify: `class/App/Module/ManifestValidator.php`
- Test: `tests/App/Support/DefaultRowsTest.php`

**Interfaces:**
- Produces: `interface ModuleDefaults { public static function seed(DefaultRows $rows): void; }`; `DefaultRows->ensure(string $modelClass, string $keyColumn, array $rows): int`, `->ensureSingleton(string $modelClass, array $values): int`, `->total(): int`, `DefaultRows::missingRows(array $rows, string $keyColumn, array $existingKeys): array`; `Manifest::defaultsClass(): ?string`.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/App/Support/DefaultRowsTest.php`:

```php
<?php
/** php tests/App/Support/DefaultRowsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Module\Manifest;
use Wonder\App\Module\ManifestValidator;
use Wonder\App\Support\DefaultRows;

final class TestModuleDefaults implements ModuleDefaults
{
    public static function seed(DefaultRows $rows): void
    {
    }
}

final class TestNotModuleDefaults
{
}

function defaultsManifest(?string $defaults): Manifest
{
    $data = ['slug' => 'prova', 'database' => ['models' => 'src/Models']];

    if ($defaults !== null) {
        $data['database']['defaults'] = $defaults;
    }

    return Manifest::fromArray('/tmp/prova', '/tmp/prova/module.json', $data, 'test');
}

check('missingRows: solo le chiavi che non esistono', function () {
    $rows = [
        ['code' => 'bank-transfer', 'name' => 'Bonifico'],
        ['code' => 'cash', 'name' => 'Contanti'],
    ];
    return DefaultRows::missingRows($rows, 'code', ['bank-transfer']) === [['code' => 'cash', 'name' => 'Contanti']];
});

check('missingRows: chiavi esistenti confrontate come stringhe', function () {
    return DefaultRows::missingRows([['code' => '10'], ['code' => '22']], 'code', [10]) === [['code' => '22']];
});

check('missingRows: duplicati nello stesso elenco inseriti una volta', function () {
    return DefaultRows::missingRows([['code' => 'a'], ['code' => 'a']], 'code', []) === [['code' => 'a']];
});

check('missingRows: riga senza chiave rifiutata', function () {
    try {
        DefaultRows::missingRows([['name' => 'senza codice']], 'code', []);
        return false;
    } catch (RuntimeException $exception) {
        return str_contains($exception->getMessage(), 'code');
    }
});

check('total() parte da zero', function () {
    return (new DefaultRows())->total() === 0;
});

check('Manifest::defaultsClass()', function () {
    return defaultsManifest(TestModuleDefaults::class)->defaultsClass() === TestModuleDefaults::class
        && defaultsManifest(null)->defaultsClass() === null
        && defaultsManifest('  ')->defaultsClass() === null;
});

check('validator: classe defaults valida senza errori dedicati', function () {
    $errors = implode(' | ', ManifestValidator::errors(defaultsManifest(TestModuleDefaults::class)));
    return !str_contains($errors, 'database.defaults') && !str_contains($errors, 'ModuleDefaults');
});

check('validator: classe inesistente', function () {
    $errors = implode(' | ', ManifestValidator::errors(defaultsManifest('Nope\\Missing')));
    return str_contains($errors, 'Classe database.defaults non autoloadabile: Nope\\Missing');
});

check('validator: classe che non implementa il contratto', function () {
    $errors = implode(' | ', ManifestValidator::errors(defaultsManifest(TestNotModuleDefaults::class)));
    return str_contains($errors, 'TestNotModuleDefaults deve implementare');
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/App/Support/DefaultRowsTest.php`
Expected: `Interface "Wonder\App\Module\Contracts\ModuleDefaults" not found`.

- [ ] **Step 3: Contratto**

`class/App/Module/Contracts/ModuleDefaults.php`:

```php
<?php

namespace Wonder\App\Module\Contracts;

use Wonder\App\Support\DefaultRows;

/**
 * Righe precaricate di un modulo, dichiarate in `module.json` con
 * `database.defaults`. Eseguite da `forge update` solo con `APP_ENV=local`,
 * in ordine di dipendenza. Non devono mai modificare righe esistenti:
 * usare sempre `DefaultRows`.
 */
interface ModuleDefaults
{
    public static function seed(DefaultRows $rows): void;
}
```

- [ ] **Step 4: `DefaultRows`**

`class/App/Support/DefaultRows.php`:

```php
<?php

namespace Wonder\App\Support;

use RuntimeException;
use Wonder\App\Model;

/**
 * Inserimento idempotente delle righe precaricate dei moduli.
 *
 * Regola unica: una riga si inserisce solo se il suo valore chiave non
 * esiste, contando anche le righe cancellate; le righe esistenti non si
 * modificano mai.
 */
final class DefaultRows
{
    private int $total = 0;

    /**
     * @param class-string<Model> $modelClass
     * @param list<array<string, mixed>> $rows
     */
    public function ensure(string $modelClass, string $keyColumn, array $rows): int
    {
        self::assertModel($modelClass);
        self::assertColumn($keyColumn);

        $table = $modelClass::$table;
        $existing = $modelClass::query()->Select($table, null, null, null, null, $keyColumn)->row;
        $existingKeys = array_column(is_array($existing) ? $existing : [], $keyColumn);
        $inserted = 0;

        foreach (self::missingRows($rows, $keyColumn, $existingKeys) as $row) {
            $result = $modelClass::query()->Insert($table, $modelClass::prepare($row));

            if (!empty($result->success)) {
                $inserted++;
            }
        }

        $this->total += $inserted;

        return $inserted;
    }

    /**
     * Crea la riga `id = 1` di un Model a riga unica se manca.
     *
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $values
     */
    public function ensureSingleton(string $modelClass, array $values): int
    {
        self::assertModel($modelClass);

        $table = $modelClass::$table;

        if ($modelClass::query()->Select($table, ['id' => 1], 1)->exists ?? false) {
            return 0;
        }

        $result = $modelClass::query()->Insert($table, array_merge(['id' => 1], $modelClass::prepare($values)));
        $inserted = !empty($result->success) ? 1 : 0;
        $this->total += $inserted;

        return $inserted;
    }

    public function total(): int
    {
        return $this->total;
    }

    /**
     * Righe il cui valore chiave non è tra quelli esistenti (classe pura).
     *
     * @param array<int, mixed> $rows
     * @param array<int, mixed> $existingKeys
     * @return list<array<string, mixed>>
     */
    public static function missingRows(array $rows, string $keyColumn, array $existingKeys): array
    {
        $known = [];

        foreach ($existingKeys as $key) {
            if (is_scalar($key) && (string) $key !== '') {
                $known[(string) $key] = true;
            }
        }

        $missing = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = $row[$keyColumn] ?? null;

            if (!is_scalar($key) || (string) $key === '') {
                throw new RuntimeException("Riga precaricata senza {$keyColumn}.");
            }

            if (isset($known[(string) $key])) {
                continue;
            }

            $known[(string) $key] = true;
            $missing[] = $row;
        }

        return $missing;
    }

    private static function assertModel(string $modelClass): void
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException("{$modelClass} deve estendere ".Model::class.'.');
        }
    }

    private static function assertColumn(string $column): void
    {
        if (preg_match('/^[a-z0-9_]+$/', $column) !== 1) {
            throw new RuntimeException("Colonna chiave non valida: {$column}");
        }
    }
}
```

- [ ] **Step 5: `Manifest::defaultsClass()`**

In `class/App/Module/Manifest.php` aggiungere dopo il metodo `resourcesPath()`:

```php
    /**
     * Classe delle righe precaricate (`database.defaults`), se dichiarata.
     */
    public function defaultsClass(): ?string
    {
        $class = $this->get('database.defaults');

        return is_string($class) && trim($class) !== '' ? trim($class) : null;
    }
```

- [ ] **Step 6: Validazione nel `ManifestValidator`**

Aggiungere `use Wonder\App\Module\Contracts\ModuleDefaults;` dopo `use Wonder\App\Module\Contracts\ModuleInterface;` e, subito dopo il blocco `if ($entrypoint !== '') { ... }`, inserire:

```php
        $defaultsClass = $manifest->defaultsClass();

        if ($defaultsClass !== null) {
            if (!class_exists($defaultsClass)) {
                $errors[] = 'Classe database.defaults non autoloadabile: '.$defaultsClass;
            } elseif (!is_subclass_of($defaultsClass, ModuleDefaults::class)) {
                $errors[] = $defaultsClass.' deve implementare '.ModuleDefaults::class;
            }
        }
```

- [ ] **Step 7: Eseguire i test**

Run: `composer dump-autoload -q && php tests/App/Support/DefaultRowsTest.php && php -l class/App/Module/Manifest.php && php -l class/App/Module/ManifestValidator.php`
Expected: `9 test, 0 falliti` e nessun errore di sintassi.

- [ ] **Step 8: Commit**

```bash
git add class/App/Module/Contracts/ModuleDefaults.php class/App/Support/DefaultRows.php class/App/Module/Manifest.php class/App/Module/ManifestValidator.php tests/App/Support/DefaultRowsTest.php
git commit -m "Add module defaults contract and idempotent default rows

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 8: Passi "defaults" ed "export" di `UpdateRunner` in locale

**Files:**
- Modify: `class/App/UpdateRunner.php`

**Interfaces:**
- Consumes: `Environment::isLocal()` (Task 1), `ModuleDependencySorter::sortManifests()` (Task 6), `Manifest::defaultsClass()`, `ModuleDefaults`, `DefaultRows` (Task 7), `TableSync::exportToFile()` (Task 4), `Wonder\App\Module\Registry::enabled(): array<string, Manifest>`.

- [ ] **Step 1: `use`**

Aggiungere dopo `use Wonder\App\Support\TableSync;`:

```php
use RuntimeException;
use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Module\ModuleDependencySorter;
use Wonder\App\Module\Registry as ModuleRegistry;
use Wonder\App\Support\DefaultRows;
```

- [ ] **Step 2: Passi dopo i file di update**

Sostituire:

```php
            $result->stats->update = $this->runFiles($this->updateDirectories());

            if ($includeCliFiles) {
```

con:

```php
            $result->stats->update = $this->runFiles($this->updateDirectories());

            if (Environment::isLocal()) {
                $result->stats->defaults = $this->runModuleDefaults();

                if ($result->stats->defaults > 0) {
                    $result->stats->sync_export = $this->runSyncExport();
                }
            }

            if ($includeCliFiles) {
```

- [ ] **Step 3: Metodi**

Aggiungere dopo `runSyncImport()`:

```php
    /**
     * Righe precaricate dei moduli abilitati, in ordine di dipendenza.
     * Chiamato solo con APP_ENV=local.
     */
    private function runModuleDefaults(): int
    {
        $rows = new DefaultRows();

        foreach (ModuleDependencySorter::sortManifests(ModuleRegistry::enabled()) as $manifest) {
            $defaultsClass = $manifest->defaultsClass();

            if ($defaultsClass === null) {
                continue;
            }

            if (!is_subclass_of($defaultsClass, ModuleDefaults::class)) {
                throw new RuntimeException($defaultsClass.' deve implementare '.ModuleDefaults::class);
            }

            $defaultsClass::seed($rows);
        }

        return $rows->total();
    }

    /**
     * Scrive shared/sync-data.json dopo l'aggiunta di righe precaricate,
     * anche con SYNC_AUTO_EXPORT spento.
     */
    private function runSyncExport(): bool
    {
        global $ROOT;

        if (!is_string($ROOT ?? null) || trim($ROOT) === '') {
            return false;
        }

        return TableSync::exportToFile($ROOT);
    }
```

- [ ] **Step 4: Lint**

Run: `php -l class/App/UpdateRunner.php`
Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```bash
git add class/App/UpdateRunner.php
git commit -m "Seed module defaults and export sync data on local updates

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 9: Resource in sola lettura fuori dal locale: route e guardie

**Files:**
- Modify: `class/App/Resource.php`
- Create: `class/App/Support/SyncedTables.php`
- Modify: `class/App/ResourceRouteRegistrar.php`
- Modify: `class/Backend/Support/ResourcePageController.php`
- Modify: `class/Api/Support/ResourceApiController.php`
- Modify: `app/http/api/backend/delete.php`, `authority.php`, `change-boolean.php`, `delete-icon.php`, `file-move.php`, `file-delete.php`, `move.php`
- Test: `tests/App/ResourceReadonlyTest.php`

**Interfaces:**
- Consumes: `SyncSchema::$localOnly`, `SyncSchema::$keepIds` (Task 2), `Environment::isLocal()` (Task 1), `TableSync::autoExport()`.
- Produces: `Resource::isReadonly(): bool`, `Resource::readonlyNotice(): string`, `Resource::deleteRecord(int|string $id): object`, `Resource::exportSyncData(): void`; `SyncedTables::isReadonly(string $table): bool`, `SyncedTables::exportIfSynced(string $table): void`.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/App/ResourceReadonlyTest.php`:

```php
<?php
/** php tests/App/ResourceReadonlyTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Environment;
use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\Support\SyncSchema;

final class ReadonlyTestLocalOnlyModel extends Model
{
    public static string $table = 'readonly_test_local_only';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow()->keepIds()->localOnly();
    }

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class ReadonlyTestSyncedModel extends Model
{
    public static string $table = 'readonly_test_synced';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow();
    }

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class ReadonlyTestLocalOnlyResource extends Resource
{
    public static string $model = ReadonlyTestLocalOnlyModel::class;
}

final class ReadonlyTestSyncedResource extends Resource
{
    public static string $model = ReadonlyTestSyncedModel::class;
}

function withEnvironment(?string $value): void {
    unset($_ENV['APP_ENV'], $_SERVER['APP_ENV']);
    putenv('APP_ENV');

    if ($value !== null) {
        $_ENV['APP_ENV'] = $value;
    }

    Environment::reset();
}

check('localOnly in produzione: sola lettura', function () {
    withEnvironment('production');
    return ReadonlyTestLocalOnlyResource::isReadonly() === true;
});

check('localOnly senza APP_ENV: sola lettura', function () {
    withEnvironment(null);
    return ReadonlyTestLocalOnlyResource::isReadonly() === true;
});

check('localOnly in locale: modificabile', function () {
    withEnvironment('local');
    return ReadonlyTestLocalOnlyResource::isReadonly() === false;
});

check('tabella sincronizzata senza localOnly: sempre modificabile', function () {
    withEnvironment('production');
    return ReadonlyTestSyncedResource::isReadonly() === false;
});

check('avviso di sola lettura', function () {
    return ReadonlyTestLocalOnlyResource::readonlyNotice() === 'Si modifica in locale e si pubblica con il deploy.';
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/App/ResourceReadonlyTest.php`
Expected: `Call to undefined method ReadonlyTestLocalOnlyResource::isReadonly()`.

- [ ] **Step 3: Metodi in `Resource`**

In `class/App/Resource.php` aggiungere gli `use`:

```php
use Wonder\App\Support\SyncSchema;
use Wonder\App\Support\TableSync;
```

e subito dopo il metodo `isSingleton()`:

```php
    /**
     * Sola lettura: Model con `SyncSchema::localOnly()` fuori da APP_ENV=local.
     * Sovrascrivibile (es. un modulo che rende locale una Resource del core).
     */
    public static function isReadonly(): bool
    {
        $schema = static::syncSchemaOrNull();

        return $schema instanceof SyncSchema
            && $schema->localOnly
            && !Environment::isLocal();
    }

    public static function readonlyNotice(): string
    {
        return 'Si modifica in locale e si pubblica con il deploy.';
    }

    /**
     * Cancella un record: con `keepIds()` segna `deleted = 'true'`, così la
     * riga resta nel sync e le righe precaricate non vengono ricreate.
     */
    public static function deleteRecord(int|string $id): object
    {
        $modelClass = static::modelClass();
        $schema = $modelClass::syncSchema();

        if ($schema instanceof SyncSchema && $schema->keepIds) {
            $result = $modelClass::query()->Update($modelClass::$table, ['deleted' => 'true'], 'id', $id);

            return (object) [
                'success' => !empty($result->success),
                'table' => $modelClass::$table,
                'id' => $id,
            ];
        }

        return $modelClass::delete($id);
    }

    /**
     * Aggiorna shared/sync-data.json dopo una modifica di un Model
     * sincronizzato (rispetta SYNC_AUTO_EXPORT).
     */
    public static function exportSyncData(): void
    {
        if (static::syncSchemaOrNull() instanceof SyncSchema) {
            TableSync::autoExport();
        }
    }

    /** Resource senza Model (es. NavigationOnlyResource): nessuno schema. */
    private static function syncSchemaOrNull(): ?SyncSchema
    {
        try {
            return static::modelClass()::syncSchema();
        } catch (RuntimeException) {
            return null;
        }
    }
```

> Variante emersa in esecuzione: `NavigationOnlyResource::modelClass()` lancia un'eccezione e `ResourceRouteRegistrar` chiama `isReadonly()` su tutte le Resource; senza `syncSchemaOrNull()` la registrazione delle route si interromperebbe. Il test include il caso.

- [ ] **Step 4: Eseguire il test**

Run: `php tests/App/ResourceReadonlyTest.php`
Expected: `5 test, 0 falliti`.

- [ ] **Step 5: `SyncedTables` per gli endpoint generici**

`class/App/Support/SyncedTables.php`:

```php
<?php

namespace Wonder\App\Support;

use Wonder\App\Environment;
use Wonder\App\ModelRegistry;
use Wonder\App\ResourceRegistry;

/**
 * Sola lettura ed export per nome di tabella, per gli endpoint API
 * generici del backend (`api/backend/*`) che ricevono `table`.
 */
final class SyncedTables
{
    public static function isReadonly(string $table): bool
    {
        $resourceClass = ResourceRegistry::resolveByTable($table);

        if ($resourceClass !== null) {
            return $resourceClass::isReadonly();
        }

        $schema = self::schema($table);

        return $schema instanceof SyncSchema && $schema->localOnly && !Environment::isLocal();
    }

    public static function exportIfSynced(string $table): void
    {
        if (self::schema($table) instanceof SyncSchema) {
            TableSync::autoExport();
        }
    }

    private static function schema(string $table): ?SyncSchema
    {
        $modelClass = ModelRegistry::all()[$table] ?? null;

        return is_string($modelClass) ? $modelClass::syncSchema() : null;
    }
}
```

- [ ] **Step 6: Guardia ed export negli endpoint generici**

In ognuno di `app/http/api/backend/delete.php`, `authority.php`, `change-boolean.php`, `delete-icon.php`, `file-move.php`, `file-delete.php`, `move.php`, sostituire la riga:

```php
$table = ApiRequest::string('table');
```

con:

```php
$table = ApiRequest::string('table');

if ($table !== '' && \Wonder\App\Support\SyncedTables::isReadonly($table)) {
    ApiRequest::error('Tabella in sola lettura in questo ambiente.', 403);
}
```

In `delete.php`, `change-boolean.php` e `move.php` (che modificano righe di tabelle anche sincronizzate), inserire subito prima dell'unica chiamata `ApiRequest::success(`:

```php
\Wonder\App\Support\SyncedTables::exportIfSynced($table);

```

- [ ] **Step 7: Nessuna route di modifica in sola lettura**

In `class/App/ResourceRouteRegistrar.php`, in `registerBackend()`:

sostituire

```php
                    $path = trim((string) $resourceClass::path(), '/');
```

con

```php
                    $path = trim((string) $resourceClass::path(), '/');
                    $readonly = $resourceClass::isReadonly();
```

sostituire

```php
                        ->group(function () use ($rootApp, $slug, $pages, $permissions, $resourceClass) {
                            if (!empty($pages['list'])) {
```

con

```php
                        ->group(function () use ($rootApp, $slug, $pages, $permissions, $resourceClass, $readonly) {
                            if (!empty($pages['list'])) {
```

e aggiungere `&& !$readonly` alle quattro condizioni:

```php
                            if (!empty($pages['create']) && !$readonly && !$resourceClass::hasCustomBackendPage('create')) {
```

```php
                            if (!empty($pages['store']) && !$readonly && !$resourceClass::hasCustomBackendPage('store')) {
```

```php
                            if (!empty($pages['update']) && !$readonly && !$resourceClass::hasCustomBackendPage('update')) {
```

```php
                            if (!empty($pages['delete']) && !$readonly && !$resourceClass::hasCustomBackendPage('delete')) {
```

In `registerApi()`:

sostituire

```php
                    $permissions = (array) $resourceClass::permissionSchema()->get('api');

                    Route::name($slug.'.')
                        ->prefix('/'.$slug)
                        ->group(function () use ($rootApp, $slug, $routes, $permissions, $resourceClass) {
```

con

```php
                    $permissions = (array) $resourceClass::permissionSchema()->get('api');
                    $readonly = $resourceClass::isReadonly();

                    Route::name($slug.'.')
                        ->prefix('/'.$slug)
                        ->group(function () use ($rootApp, $slug, $routes, $permissions, $resourceClass, $readonly) {
```

e sostituire le tre condizioni:

```php
                            if (!empty($routes['store'])) {
```

```php
                            if (!empty($routes['update'])) {
```

```php
                            if (!empty($routes['destroy'])) {
```

rispettivamente con:

```php
                            if (!empty($routes['store']) && !$readonly) {
```

```php
                            if (!empty($routes['update']) && !$readonly) {
```

```php
                            if (!empty($routes['destroy']) && !$readonly) {
```

- [ ] **Step 8: Guardie, cancellazione ed export nel controller backend**

In `class/Backend/Support/ResourcePageController.php`:

1. In `store()`, subito dopo la riga `global $ALERT;`, aggiungere `$this->guardWritable();`.
2. In `update(int $id)`, subito dopo la riga `global $ALERT;`, aggiungere `$this->guardWritable();`.
3. Sostituire l'intero metodo `delete`:

```php
    private function delete(int $id): never
    {
        $this->guardWritable();
        $values = $this->resourceRow($id);
        $result = $this->resourceClass::deleteRecord($id);
        $this->resourceClass::afterDelete($id, $result, $values);
        $this->resourceClass::exportSyncData();

        $this->redirectToConfiguredPage('delete');
    }
```

4. In `store()`, sostituire `$this->redirectToConfiguredPage('store');` con:

```php
            $this->resourceClass::exportSyncData();
            $this->redirectToConfiguredPage('store');
```

5. In `update()`, sostituire:

```php
            $this->resourceClass::afterUpdate($id, $result, $values);
            $this->redirectToConfiguredPage('update');
```

con:

```php
            $this->resourceClass::afterUpdate($id, $result, $values);
            $this->resourceClass::exportSyncData();
            $this->redirectToConfiguredPage('update');
```

6. Aggiungere dopo `guardPositiveId()`:

```php
    private function guardWritable(): void
    {
        if ($this->resourceClass::isReadonly()) {
            throw new RuntimeException('Resource in sola lettura in questo ambiente: '.$this->resourceClass::slug());
        }
    }
```

- [ ] **Step 9: Guardie, cancellazione ed export nel controller API**

In `class/Api/Support/ResourceApiController.php`:

1. In `store(Endpoint $endpoint)`, come prima istruzione:

```php
        if ($this->resourceClass::isReadonly()) {
            return $endpoint->response(
                $this->presenter->validationErrorPayload(['readonly' => $this->resourceClass::readonlyNotice()]),
                403
            );
        }
```

2. Lo stesso blocco come prima istruzione di `update(Endpoint $endpoint, int $id)` e di `destroy(Endpoint $endpoint, int $id)`.
3. In `store()`: dopo `$this->resourceClass::afterUpdate($targetId, $result, $values);` aggiungere `$this->resourceClass::exportSyncData();`; dopo `$this->resourceClass::afterStore($result, $values);` aggiungere `$this->resourceClass::exportSyncData();`.
4. In `update()`: dopo `$this->resourceClass::afterUpdate($id, $result, $values);` aggiungere `$this->resourceClass::exportSyncData();`.
5. In `destroy()`, sostituire:

```php
        $values = $this->resourceRow($id);
        $modelClass = $this->resourceClass::modelClass();
        $result = $modelClass::delete($id);
        $this->resourceClass::afterDelete($id, $result, $values);
```

con:

```php
        $values = $this->resourceRow($id);
        $result = $this->resourceClass::deleteRecord($id);
        $this->resourceClass::afterDelete($id, $result, $values);
        $this->resourceClass::exportSyncData();
```

- [ ] **Step 10: Togliere gli `autoExport()` manuali delle Resource CSS**

- `class/App/Resources/Css/CssFontResource.php`: nel metodo `refreshCss()` rimuovere la riga `\Wonder\App\Support\TableSync::autoExport();` (l'export lo fanno ora i controller).
- `class/App/Resources/Css/CssColorResource.php`: stessa rimozione in `refreshCss()`.
- `class/App/Resources/Support/CssSingleton.php`: nel metodo `refreshCss()` rimuovere `TableSync::autoExport();` e, se `TableSync` non è più usato nel file, la riga `use Wonder\App\Support\TableSync;`.

- [ ] **Step 11: Lint e test**

Run: `composer dump-autoload -q && for f in class/App/Resource.php class/App/Support/SyncedTables.php class/App/ResourceRouteRegistrar.php class/Backend/Support/ResourcePageController.php class/Api/Support/ResourceApiController.php app/http/api/backend/delete.php app/http/api/backend/authority.php app/http/api/backend/change-boolean.php app/http/api/backend/delete-icon.php app/http/api/backend/file-move.php app/http/api/backend/file-delete.php app/http/api/backend/move.php class/App/Resources/Css/CssFontResource.php class/App/Resources/Css/CssColorResource.php class/App/Resources/Support/CssSingleton.php; do php -l $f; done && php tests/App/ResourceReadonlyTest.php`
Expected: `No syntax errors detected` per ogni file e `5 test, 0 falliti`.

- [ ] **Step 12: Commit**

```bash
git add class/App/Resource.php class/App/Support/SyncedTables.php class/App/ResourceRouteRegistrar.php class/Backend/Support/ResourcePageController.php class/Api/Support/ResourceApiController.php app/http/api/backend class/App/Resources/Css/CssFontResource.php class/App/Resources/Css/CssColorResource.php class/App/Resources/Support/CssSingleton.php tests/App/ResourceReadonlyTest.php
git commit -m "Make local-only resources read-only outside local and export after writes

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 10: Pagine del backend in sola lettura

**Files:**
- Modify: `class/Backend/Support/ResourcePagePresenter.php`
- Modify: `class/Backend/Support/ResourceTableRenderer.php`
- Modify: `app/view/pages/backend/resource/form.php`
- Modify: `app/view/pages/backend/resource/list.php`

**Interfaces:**
- Consumes: `Resource::isReadonly()`, `Resource::readonlyNotice()` (Task 9).
- Produces: variabili di vista `READONLY` (bool) e `READONLY_NOTICE` (string) per `list` e `form`.

- [ ] **Step 1: Presenter**

In `ResourcePagePresenter::list()` aggiungere all'array restituito:

```php
            'READONLY' => $this->resourceClass::isReadonly(),
            'READONLY_NOTICE' => $this->resourceClass::readonlyNotice(),
```

In `ResourcePagePresenter::form()` aggiungere all'array restituito le stesse due chiavi.

Sostituire l'inizio di `applyModelFieldState()`:

```php
    private function applyModelFieldState(object $field, string $mode): object
    {
        if ($mode !== 'edit' || !property_exists($field, 'name') || !method_exists($field, 'readonly')) {
            return $field;
        }
```

con:

```php
    private function applyModelFieldState(object $field, string $mode): object
    {
        if ($this->resourceClass::isReadonly() && method_exists($field, 'disabled')) {
            $field->disabled();
        }

        if ($mode !== 'edit' || !property_exists($field, 'name') || !method_exists($field, 'readonly')) {
            return $field;
        }
```

- [ ] **Step 2: Tabella senza "Aggiungi" né azioni di modifica**

In `ResourceTableRenderer::applyButtonAdd()` sostituire:

```php
        if (empty($this->pageSchema['pages']['create']) || empty($buttonAdd['enabled'])) {
```

con:

```php
        if ($this->resourceClass::isReadonly() || empty($this->pageSchema['pages']['create']) || empty($buttonAdd['enabled'])) {
```

In `ResourceTableRenderer::resolvedActions()` sostituire:

```php
        foreach ($actions as $action => $enabled) {
            if (!$enabled) {
                continue;
            }
```

con:

```php
        $readonly = $this->resourceClass::isReadonly();

        foreach ($actions as $action => $enabled) {
            if (!$enabled || ($readonly && in_array($action, ['delete', 'duplicate'], true))) {
                continue;
            }
```

- [ ] **Step 3: Vista del form**

Sostituire l'intero contenuto di `app/view/pages/backend/resource/form.php` con:

```php
<?php \Wonder\View\View::layout('backend.form'); ?>

<?php
    $readonly = (bool) ($READONLY ?? false);
    $readonlyNotice = htmlspecialchars((string) ($READONLY_NOTICE ?? ''), ENT_QUOTES, 'UTF-8');
    $noticeHtml = '
        <div class="col-12">
            <wi-card class="col-12">
                <div class="alert alert-warning mb-0">'.$readonlyNotice.'</div>
            </wi-card>
        </div>';
    $submitHtml = function (string $class = ''): string {
        if (!function_exists('submit')) {
            return '<button type="submit" class="btn btn-dark'.($class !== '' ? ' '.$class : '').'">Salva</button>';
        }

        return $class !== '' ? submit('Salva', 'upload', $class) : submit('Salva', 'upload');
    };
?>

<?php if (is_object($FORM_LAYOUT ?? null)) { ?>
    <?=
        \Wonder\Backend\Support\ResourceFormLayoutRenderer::render(
            $FORM_LAYOUT,
            [
                'id' => 'resource-layout-form',
                'method' => (string) ($FORM_METHOD ?? 'POST'),
                'enctype' => (string) ($FORM_ENCTYPE ?? 'multipart/form-data'),
                'action' => $readonly ? '' : (string) ($FORM_ACTION ?? ''),
                'footer' => $readonly
                    ? $noticeHtml
                    : '
                    <div class="col-12">
                        <wi-card class="col-12">
                            <div class="col-12">'.$submitHtml().'</div>
                        </wi-card>
                    </div>',
            ]
        )
    ?>
<?php } else { ?>
<form method="<?=htmlspecialchars((string) ($FORM_METHOD ?? 'POST'), ENT_QUOTES, 'UTF-8')?>"
      enctype="<?=htmlspecialchars((string) ($FORM_ENCTYPE ?? 'multipart/form-data'), ENT_QUOTES, 'UTF-8')?>"
      action="<?=$readonly ? '' : htmlspecialchars((string) ($FORM_ACTION ?? ''), ENT_QUOTES, 'UTF-8')?>"
      <?=$readonly ? 'onsubmit="return false"' : 'onsubmit="loadingSpinner()"'?>>
    <div class="row g-3">
        <?php if ($readonly) { echo $noticeHtml; } ?>
        <div class="<?=!empty($SIDEBAR_FIELDS) ? 'col-9' : 'col-12'?>">
            <wi-card class="col-12">
                <?php foreach ((array) ($FIELDS ?? []) as $field) { ?>
                    <?php
                        if (is_object($field) && method_exists($field, 'render')) {
                            echo $field->render();
                        }
                    ?>
                <?php } ?>
            </wi-card>
        </div>

        <?php if (!empty($SIDEBAR_FIELDS)) { ?>
        <div class="col-3">
            <wi-card class="col-12">
                <?php foreach ((array) ($SIDEBAR_FIELDS ?? []) as $field) { ?>
                    <?php
                        if (is_object($field) && method_exists($field, 'render')) {
                            echo $field->render();
                        }
                    ?>
                <?php } ?>
                <?php if (!$readonly) { ?>
                <div class="col-12">
                    <?=$submitHtml('w-100')?>
                </div>
                <?php } ?>
            </wi-card>
        </div>
        <?php } elseif (!$readonly) { ?>
        <div class="col-12">
            <wi-card class="col-12">
                <div class="col-12">
                    <?=$submitHtml()?>
                </div>
            </wi-card>
        </div>
        <?php } ?>
    </div>
</form>
<?php } ?>

<?php \Wonder\View\View::end(); ?>
```

- [ ] **Step 4: Vista dell'elenco**

Sostituire l'intero contenuto di `app/view/pages/backend/resource/list.php` con:

```php
<?php \Wonder\View\View::layout('backend.list'); ?>

<?php if (!empty($READONLY)) { ?>
<wi-card class="col-12">
    <div class="alert alert-warning mb-0"><?=htmlspecialchars((string) ($READONLY_NOTICE ?? ''), ENT_QUOTES, 'UTF-8')?></div>
</wi-card>
<?php } ?>

<?=$TABLE_HTML?>

<?php \Wonder\View\View::end(); ?>
```

- [ ] **Step 5: Lint**

Run: `for f in class/Backend/Support/ResourcePagePresenter.php class/Backend/Support/ResourceTableRenderer.php app/view/pages/backend/resource/form.php app/view/pages/backend/resource/list.php; do php -l $f; done && php tests/App/ResourceReadonlyTest.php`
Expected: `No syntax errors detected` per ogni file e nessun test fallito.

- [ ] **Step 6: Commit**

```bash
git add class/Backend/Support/ResourcePagePresenter.php class/Backend/Support/ResourceTableRenderer.php app/view/pages/backend/resource/form.php app/view/pages/backend/resource/list.php
git commit -m "Render read-only backend pages for local-only resources

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 11: Documentazione in `docs/app/`

**Files:**
- Modify: `docs/app/piattaforma/multi-ambiente.md`
- Modify: `docs/app/piattaforma/installazione-e-deploy.md`
- Modify: `docs/app/concetti/moduli/manifest.md`
- Modify: `docs/app/concetti/moduli/contratto.md`
- Modify: `docs/app/concetti/risorse/resource.md`

- [ ] **Step 1: `multi-ambiente.md`**

Aggiungere, prima di `## Media: proxy fallback in locale`:

````markdown
## Ambiente: `APP_ENV`

Il framework distingue locale e produzione con `APP_ENV` (`local` o `production`).
Se la variabile manca o non è valida vale `production`. `php forge start` scrive
`APP_ENV=local` nel `.env` locale; i boilerplate la dichiarano in `.env.example`.

```php
use Wonder\App\Environment;

Environment::isLocal();      // true solo con APP_ENV=local
Environment::isProduction();
```

````

Sostituire la sottosezione `#### Import automatico in \`forge update\`` (fino alla sottosezione successiva) con:

````markdown
#### Import automatico in `forge update`

`UpdateRunner` importa `shared/sync-data.json`, se esiste, subito dopo le tabelle e
prima dei file di update (quindi prima della rigenerazione dei CSS). Il deploy con
`forge update` allinea il DB ai dati committati senza `forge import` manuale.

#### Tabelle con `id` stabili: `keepIds()`

Per le tabelle referenziate da chiavi esterne (es. metodi di pagamento usati dagli
ordini) l'import non deve rinumerare gli `id`:

```php
public static function syncSchema(): ?SyncSchema
{
    return SyncSchema::multiRow()->keepIds();
}
```

- l'export include `id` e `deleted`, ordinato per `id`;
- l'import inserisce o aggiorna per `id`, senza `TRUNCATE`;
- le righe assenti dal file vengono segnate `deleted = 'true'`, mai eliminate;
- la cancellazione dal backend di queste tabelle è sempre logica.

#### Tabelle modificabili solo in locale: `localOnly()`

```php
return SyncSchema::multiRow()->keepIds()->localOnly();
```

Fuori da `APP_ENV=local` le Resource di questi Model sono in sola lettura: nessuna
route di creazione, salvataggio o eliminazione (anche API), campi disabilitati e
avviso "Si modifica in locale e si pubblica con il deploy". Anche gli endpoint
generici `api/backend/*` rifiutano le modifiche.

#### Righe precaricate dei moduli

Con `APP_ENV=local`, `forge update` esegue le classi `database.defaults` dei moduli
abilitati in ordine di dipendenza e, se aggiungono righe, riscrive
`shared/sync-data.json` da committare. In produzione non crea righe: arrivano dal
file. Vedi [Manifest](../concetti/moduli/manifest.md).

````

- [ ] **Step 2: `installazione-e-deploy.md`**

Nella sezione `### \`php forge update\``, aggiungere in fondo:

````markdown
**Passi di `UpdateRunner`:**

1. tabelle dai Model;
2. righe legacy di `build/row`, se presenti;
3. import di `shared/sync-data.json` (`stats.sync_import`);
4. file di `build/update`, se presenti;
5. solo con `APP_ENV=local`: righe precaricate dei moduli (`stats.defaults`);
6. se il passo 5 ha inserito righe: scrittura di `shared/sync-data.json` (`stats.sync_export`);
7. con `--local`: file di `build/cli`.
````

- [ ] **Step 3: `manifest.md`**

Nella sezione `## Campi letti dal Manifest` aggiungere:

````markdown
### `database.defaults`

Classe delle righe precaricate del modulo:

```json
"database": {
    "models": "src/Models",
    "defaults": "Wonder\\Plugin\\Gestionale\\Database\\Defaults"
}
```

La classe implementa `Wonder\App\Module\Contracts\ModuleDefaults` (vedi
[Contratto](contratto.md)). Il validator segnala classi inesistenti o che non
implementano il contratto. Eseguita solo da `forge update` con `APP_ENV=local`.
````

- [ ] **Step 4: `contratto.md`**

Aggiungere prima di `## Errori comuni`:

````markdown
## Righe precaricate (`ModuleDefaults`)

```php
namespace Wonder\Plugin\Gestionale\Database;

use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Support\DefaultRows;
use Wonder\Plugin\Gestionale\Models\Tax\Tax;
use Wonder\Plugin\Gestionale\Models\System\Settings;

final class Defaults implements ModuleDefaults
{
    public static function seed(DefaultRows $rows): void
    {
        $rows->ensure(Tax::class, 'code', [
            ['code' => 'vat-22', 'name' => '22% - Aliquota ordinaria', 'rate' => '22.00'],
        ]);

        $rows->ensureSingleton(Settings::class, ['return_days' => 14]);
    }
}
```

- `ensure()` inserisce solo le righe il cui valore chiave non esiste, contando anche
  le righe cancellate, e non modifica mai le righe esistenti;
- `ensureSingleton()` crea la riga `id = 1` se manca;
- i moduli vengono eseguiti in ordine di dipendenza (`dependencies.modules`).
````

- [ ] **Step 5: `resource.md`**

Aggiungere prima di `## Estendere oltre il CRUD`:

````markdown
## Sola lettura, cancellazione ed export

| Metodo | Default | Uso |
|---|---|---|
| `isReadonly(): bool` | `true` se il Model dichiara `SyncSchema::...->localOnly()` e `APP_ENV` non è `local` | sovrascrivibile, es. un modulo che rende locale una Resource del core |
| `readonlyNotice(): string` | `Si modifica in locale e si pubblica con il deploy.` | testo dell'avviso |
| `deleteRecord(int\|string $id): object` | cancellazione logica con `keepIds()`, altrimenti `Model::delete()` | usato dai controller backend e API |
| `exportSyncData(): void` | `TableSync::autoExport()` per i Model sincronizzati | chiamato dopo store, update e delete |

In sola lettura le route di modifica non vengono registrate, i campi sono
disabilitati e le pagine mostrano l'avviso.
````

- [ ] **Step 6: Commit**

```bash
git add docs/app/piattaforma/multi-ambiente.md docs/app/piattaforma/installazione-e-deploy.md docs/app/concetti/moduli/manifest.md docs/app/concetti/moduli/contratto.md docs/app/concetti/risorse/resource.md
git commit -m "Document APP_ENV, stable-id sync, local-only tables and module defaults

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 12: Verifica da un sito e `APP_ENV` nei boilerplate

**Files:**
- Sito di verifica temporaneo nello scratchpad (non committato)
- Modify: `.env.example` di `boilerplates/new-site`, `boilerplates/immobili-site`, `boilerplates/rsvp-site` (repository separati)

**Interfaces:**
- Consumes: tutto il piano.

- [ ] **Step 1: Tutti i test senza database**

Run: `for t in tests/App/EnvironmentTest.php tests/App/Support/SyncSchemaTest.php tests/App/Support/SyncImportPlanTest.php tests/App/Module/ModuleDependencySorterTest.php tests/App/Support/DefaultRowsTest.php tests/App/ResourceReadonlyTest.php tests/App/Support/SyncTableSorterTest.php tests/App/DebugTest.php; do php $t || exit 1; done`
Expected: ogni file termina con `0 falliti`.

- [ ] **Step 2: Preparare il sito di verifica**

Copiare `boilerplates/new-site` (senza `vendor/` e `node_modules/`) in una cartella dello scratchpad, poi in `composer.json` aggiungere:

```json
"repositories": [
    { "type": "path", "url": "/Users/andreamarinoni/Developer/packages/app", "options": { "symlink": true } }
],
```

e impostare `"wonder-image/app": "*@dev"`. Eseguire `composer update wonder-image/app` e verificare che `vendor/wonder-image/app` sia un symlink al repository.

- [ ] **Step 3: Database e `.env` locale**

Creare un database MySQL locale dedicato (es. `wi_verifica_prerequisiti`), impostare `DB_*` nel `.env` del sito di verifica e `APP_ENV=local`. Eseguire `php forge update --local`.
Expected: JSON con `"success": true`, `stats.sync_import` presente, `stats.defaults` = `0`.

- [ ] **Step 4: Modulo di prova con `database.defaults`**

Nel sito di verifica creare `modules/prova-defaults/` (sorgente locale di `Module\Discovery`) con questi file.

`modules/prova-defaults/module.json`:

```json
{
    "name": "Prova defaults",
    "slug": "prova-defaults",
    "version": "0.1.0",
    "description": "Modulo di verifica delle righe precaricate",
    "namespace": "Wonder\\Plugin\\ProvaDefaults\\",
    "entrypoint": "Wonder\\Plugin\\ProvaDefaults\\ProvaDefaults",
    "frameworkCompatibility": { "wonder-app": "*", "php": "^8.2" },
    "dependencies": { "modules": [] },
    "paths": { "src": "src" },
    "database": { "models": "src/Models", "defaults": "Wonder\\Plugin\\ProvaDefaults\\Defaults" }
}
```

`modules/prova-defaults/src/ProvaDefaults.php`:

```php
<?php

namespace Wonder\Plugin\ProvaDefaults;

use Wonder\App\Module\Contracts\ModuleInterface;

final class ProvaDefaults implements ModuleInterface
{
    public static function root(): string { return dirname(__DIR__); }
    public static function manifestPath(): string { return self::root().'/module.json'; }
    public static function handlerPath(string $path): string { return self::root().'/http/'.ltrim($path, '/'); }
    public static function viewPath(string $path): string { return self::root().'/view/'.ltrim($path, '/'); }
    public static function langPath(): string { return self::root().'/lang'; }
    public static function assetPath(string $path = ''): string { return self::root().'/resources/assets/'.ltrim($path, '/'); }
}
```

`modules/prova-defaults/src/Models/ProvaMetodo.php`:

```php
<?php

namespace Wonder\Plugin\ProvaDefaults\Models;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;

final class ProvaMetodo extends Model
{
    public static string $table = 'prova_metodi';
    public static string $folder = 'prova-metodi';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow()->keepIds()->localOnly();
    }

    public static function tableSchema(): array
    {
        return static::sqlColumnsFromDataSchema(['code', 'name']);
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('code')->text()->required(),
            Field::key('name')->text()->required(),
        ];
    }
}
```

`modules/prova-defaults/src/Resources/ProvaMetodoResource.php`:

```php
<?php

namespace Wonder\Plugin\ProvaDefaults\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\Plugin\ProvaDefaults\Models\ProvaMetodo;

final class ProvaMetodoResource extends Resource
{
    public static string $model = ProvaMetodo::class;

    public static function path(): string
    {
        return 'prova-metodi';
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('code')->text()->required(),
            FormField::key('name')->text()->required(),
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('code')->text()->link('edit'),
            TableColumn::key('name')->text(),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin'])
            ->apiCrud(['admin']);
    }
}
```

`modules/prova-defaults/src/Defaults.php`:

```php
<?php

namespace Wonder\Plugin\ProvaDefaults;

use Wonder\App\Module\Contracts\ModuleDefaults;
use Wonder\App\Support\DefaultRows;
use Wonder\Plugin\ProvaDefaults\Models\ProvaMetodo;

final class Defaults implements ModuleDefaults
{
    public static function seed(DefaultRows $rows): void
    {
        $rows->ensure(ProvaMetodo::class, 'code', [
            ['code' => 'a', 'name' => 'A'],
            ['code' => 'b', 'name' => 'B'],
        ]);
    }
}
```

Nel `composer.json` del sito aggiungere a `autoload.psr-4` la voce `"Wonder\\Plugin\\ProvaDefaults\\": "modules/prova-defaults/src/"`, eseguire `composer dump-autoload` e abilitare il modulo in `custom/config/modules.php` con la chiave `'prova-defaults' => ['enabled' => true]`.

- [ ] **Step 5: Verifiche**

1. `php forge update --local` → `stats.defaults` = `2`, `stats.sync_export` = `true`; `shared/sync-data.json` contiene `prova_metodi` con `id` e `deleted`.
2. Secondo `php forge update --local` → `stats.defaults` = `0`.
3. Con `APP_ENV=local` cancellare la riga `b` dalla tabella del backend (route `backend.resource.prova-metodi.list`) → nel database `deleted = 'true'`; `php forge update --local` → `stats.defaults` = `0` (la riga non viene ricreata).
4. Modificare in `shared/sync-data.json` il `name` della riga `a` e eseguire `php forge import` → stesso `id`, `name` aggiornato. Poi togliere dal file la riga `a` ed eseguire `php forge import` → riga `a` con `deleted = 'true'`, nessuna riga eliminata.
5. Impostare `APP_ENV=production` e aprire la lista della Resource → avviso "Si modifica in locale e si pubblica con il deploy.", nessun pulsante "Aggiungi", nessuna azione "Elimina"; la scheda di una riga ha i campi disabilitati e nessun "Salva"; `POST` a `/api/backend/delete/` con `table=prova_metodi` → HTTP 403; `__r('backend.resource.prova-metodi.store')` restituisce stringa vuota.
6. Con `APP_ENV=local` e `SYNC_AUTO_EXPORT=true` modificare un colore CSS dal backend → `shared/sync-data.json` aggiornato; `php forge import` importa le tabelle CSS come prima (svuotandole e reinserendole).

Annotare gli esiti nel riepilogo finale; se MySQL locale non è disponibile, dichiararlo e fermarsi a questo passo.

- [ ] **Step 6: `APP_ENV` nei boilerplate**

In ciascuno di `boilerplates/new-site`, `boilerplates/immobili-site`, `boilerplates/rsvp-site`: creare il ramo `feature/app-env`, in `.env.example` sostituire

```
# App Info
APP_DEBUG=true
```

con

```
# App Info
# local in sviluppo, production sul server (default se assente)
APP_ENV=local
APP_DEBUG=true
```

e fare commit:

```bash
git checkout -b feature/app-env
git add .env.example
git commit -m "Declare APP_ENV in env example

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

- [ ] **Step 7: Pulizia**

Eliminare il database di verifica e la cartella del sito di verifica nello scratchpad.
