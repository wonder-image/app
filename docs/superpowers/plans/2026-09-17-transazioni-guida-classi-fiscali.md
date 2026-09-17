# Transazioni, pulsante "Guida" e classi fiscali — Piano di implementazione (2 di 3)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** aggiungere al core transazioni annidabili, letture `FOR UPDATE` protette, lock nominali per i cron, il pulsante "Guida" nelle pagine del backend e le classi fiscali mancanti.

**Architecture:** la logica di annidamento delle transazioni e dei lock sta in classi che dipendono da piccole interfacce (`TransactionConnection`, `NamedLockConnection`), con adattatori mysqli; così si testa senza database. Il pulsante "Guida" nasce da `PageSchema::docs()` e da un descriptor puro (`DocsAction`), reso nei tre punti che disegnano l'header (tabella, form, scheda). Le classi fiscali seguono lo stile di `Custom\Fattura\Valori`.

**Tech Stack:** PHP 8.2, `wonder-image/app`, mysqli, test con `tests/harness.php`.

**Spec:** `docs/superpowers/specs/2026-09-16-prerequisiti-moduli-gestionale-design.md` (parti F, G, H). Piano precedente: `docs/superpowers/plans/2026-09-17-ambiente-e-sincronizzazione.md`.

## Global Constraints

- Repo `wonder-image/app`, ramo `feature/prerequisiti-moduli-gestionale`. Non toccare `vendor-static/xml-sitemaps/data/generator.conf`.
- I test sono in `.gitignore` (`/tests/*`): si aggiungono con `git add -f`, come quelli esistenti.
- `Transaction::run(callable $callback, ?string $database = null): mixed`; `null` = database `main`; annidamento con `SAVEPOINT wi_sp_<n>`.
- Letture `ForUpdate` fuori da una transazione: `RuntimeException`.
- `NamedLock::run(string $name, callable $callback, int $timeout = 0): mixed`; se il lock non si ottiene restituisce `NamedLock::NOT_ACQUIRED` senza eseguire il callback; rilascio anche in caso di eccezione.
- `PageSchema::docs(string $url, string|array|null $pages = null)`; `null` = `list`, `create`, `edit`, `view`; solo URL `http(s)` o relativi.
- Pulsante "Guida": icona `bi bi-question-circle`, stile secondario outline, nuova scheda con `rel="noopener noreferrer"`, etichetta `components.buttons.docs`.
- Classi fiscali in `Wonder\Plugin\Custom\Fattura\Valori`, costante `Valori`, `Natura::Valori` invariata.
- Commit in inglese chiusi da `Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>`.
- La verifica con database resta rimandata (TODO del gestionale).

## Mappa dei file

| File | Azione | Responsabilità |
|---|---|---|
| `class/Sql/TransactionConnection.php` | nuovo | contratto minimo per transazioni e savepoint |
| `class/Sql/MysqliTransactionConnection.php` | nuovo | adattatore mysqli |
| `class/Sql/Transaction.php` | nuovo | `run()`, annidamento, `active()` |
| `app/function/sql.php` | modifica | `sqlTransaction()`, `sqlSelectForUpdate()` |
| `class/Sql/Query.php` | modifica | `SelectForUpdate()` |
| `class/App/Model.php` | modifica | `findForUpdate()`, `findByIdForUpdate()` |
| `class/App/Support/NamedLockConnection.php` | nuovo | contratto dei lock nominali |
| `class/App/Support/MysqliNamedLockConnection.php` | nuovo | adattatore `GET_LOCK`/`RELEASE_LOCK` |
| `class/App/Support/NamedLock.php` | nuovo | `run()` con `NOT_ACQUIRED` |
| `class/App/ResourceSchema/PageSchema.php` | modifica | `docs()`, `docsUrl()` |
| `class/Backend/Support/DocsAction.php` | nuovo | descriptor puro del pulsante |
| `class/Backend/Support/ResourcePagePresenter.php` | modifica | pulsante in scheda e form |
| `class/Backend/Support/ResourceTableRenderer.php` | modifica | pulsante nell'elenco |
| `app/view/layout/backend/form.php` | modifica | pulsante nell'header del form |
| `resources/lang/{it,en,de,es,fr}/components.json` | modifica | `buttons.docs` |
| `class/Plugin/Custom/Fattura/Valori/AliquoteIva.php` | nuovo | aliquote italiane |
| `class/Plugin/Custom/Fattura/Valori/Natura.php` | modifica | `VALIDE`, `valide()` |
| `class/Plugin/Custom/Fattura/Valori/EsigibilitaIva.php` | nuovo | esigibilità IVA |
| `tests/Sql/TransactionTest.php`, `tests/Sql/SelectForUpdateTest.php`, `tests/App/Support/NamedLockTest.php`, `tests/App/ResourceSchema/PageSchemaDocsTest.php`, `tests/Plugin/FatturaValoriTest.php` | nuovi | test senza database |
| `docs/app/concetti/risorse/database.md`, `docs/app/concetti/risorse/resource.md`, `docs/app/servizi/fatturapa-valori.md`, `docs/app/SUMMARY.md` | modifica/nuovo | documentazione |

---

### Task 1: Transazioni annidabili

**Files:**
- Create: `class/Sql/TransactionConnection.php`, `class/Sql/MysqliTransactionConnection.php`, `class/Sql/Transaction.php`
- Modify: `app/function/sql.php` (dopo la funzione `sqlSelect`)
- Test: `tests/Sql/TransactionTest.php`

**Interfaces:**
- Produces: `interface TransactionConnection { id(): string; begin(): void; commit(): void; rollback(): void; savepoint(string $name): void; rollbackToSavepoint(string $name): void; releaseSavepoint(string $name): void; }`; `Transaction::run(callable, ?string $database = null): mixed`, `Transaction::runOn(TransactionConnection, callable): mixed`, `Transaction::active(?string $database = null): bool`, `Transaction::activeOn(TransactionConnection): bool`, `Transaction::activeForMysqli(mysqli): bool`; `MysqliTransactionConnection::idFor(mysqli): string`; funzione `sqlTransaction(callable $callback, string $database = 'main')`.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/Sql/TransactionTest.php`:

```php
<?php
/** php tests/Sql/TransactionTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Sql\Transaction;
use Wonder\Sql\TransactionConnection;

final class FakeTransactionConnection implements TransactionConnection
{
    /** @var string[] */
    public array $log = [];

    public function __construct(private string $key = 'fake:1') {}

    public function id(): string { return $this->key; }
    public function begin(): void { $this->log[] = 'begin'; }
    public function commit(): void { $this->log[] = 'commit'; }
    public function rollback(): void { $this->log[] = 'rollback'; }
    public function savepoint(string $name): void { $this->log[] = 'savepoint '.$name; }
    public function rollbackToSavepoint(string $name): void { $this->log[] = 'rollback to '.$name; }
    public function releaseSavepoint(string $name): void { $this->log[] = 'release '.$name; }
}

check('commit e valore restituito', function () {
    $connection = new FakeTransactionConnection();
    $value = Transaction::runOn($connection, fn () => 42);
    return $value === 42 && $connection->log === ['begin', 'commit'] && !Transaction::activeOn($connection);
});

check('eccezione: rollback e rilancio', function () {
    $connection = new FakeTransactionConnection();
    try {
        Transaction::runOn($connection, function () { throw new RuntimeException('boom'); });
        return false;
    } catch (RuntimeException $exception) {
        return $exception->getMessage() === 'boom'
            && $connection->log === ['begin', 'rollback']
            && !Transaction::activeOn($connection);
    }
});

check('attiva durante il callback', function () {
    $connection = new FakeTransactionConnection();
    $inside = null;
    Transaction::runOn($connection, function () use ($connection, &$inside) { $inside = Transaction::activeOn($connection); });
    return $inside === true;
});

check('annidamento con savepoint e rilascio', function () {
    $connection = new FakeTransactionConnection();
    Transaction::runOn($connection, function () use ($connection) {
        Transaction::runOn($connection, fn () => null);
    });
    return $connection->log === ['begin', 'savepoint wi_sp_1', 'release wi_sp_1', 'commit'];
});

check('errore interno gestito: torna al savepoint, esterno conferma', function () {
    $connection = new FakeTransactionConnection();
    Transaction::runOn($connection, function () use ($connection) {
        try {
            Transaction::runOn($connection, function () { throw new LogicException('interno'); });
        } catch (LogicException) {
        }
    });
    return $connection->log === ['begin', 'savepoint wi_sp_1', 'rollback to wi_sp_1', 'commit'];
});

check('errore interno non gestito: annulla tutto', function () {
    $connection = new FakeTransactionConnection();
    try {
        Transaction::runOn($connection, function () use ($connection) {
            Transaction::runOn($connection, function () { throw new LogicException('interno'); });
        });
    } catch (LogicException) {
    }
    return $connection->log === ['begin', 'savepoint wi_sp_1', 'rollback to wi_sp_1', 'rollback'];
});

check('connessioni diverse indipendenti', function () {
    $a = new FakeTransactionConnection('fake:a');
    $b = new FakeTransactionConnection('fake:b');
    Transaction::runOn($a, function () use ($b) {
        Transaction::runOn($b, fn () => null);
    });
    return $a->log === ['begin', 'commit'] && $b->log === ['begin', 'commit'];
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/Sql/TransactionTest.php`
Expected: `Interface "Wonder\Sql\TransactionConnection" not found`.

- [ ] **Step 3: Contratto e adattatore**

`class/Sql/TransactionConnection.php`:

```php
<?php

namespace Wonder\Sql;

/**
 * Operazioni minime per transazioni e savepoint su una connessione.
 * `id()` identifica la connessione fisica (stessa connessione = stesso id).
 */
interface TransactionConnection
{
    public function id(): string;

    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function savepoint(string $name): void;

    public function rollbackToSavepoint(string $name): void;

    public function releaseSavepoint(string $name): void;
}
```

`class/Sql/MysqliTransactionConnection.php`:

```php
<?php

namespace Wonder\Sql;

use mysqli;
use RuntimeException;

final class MysqliTransactionConnection implements TransactionConnection
{
    public function __construct(private readonly mysqli $mysqli)
    {
    }

    public static function idFor(mysqli $mysqli): string
    {
        return 'mysqli:'.spl_object_id($mysqli);
    }

    public function id(): string
    {
        return self::idFor($this->mysqli);
    }

    public function begin(): void
    {
        if (!$this->mysqli->begin_transaction()) {
            throw new RuntimeException('Impossibile aprire la transazione: '.$this->mysqli->error);
        }
    }

    public function commit(): void
    {
        if (!$this->mysqli->commit()) {
            throw new RuntimeException('Impossibile confermare la transazione: '.$this->mysqli->error);
        }
    }

    public function rollback(): void
    {
        $this->mysqli->rollback();
    }

    public function savepoint(string $name): void
    {
        $this->query('SAVEPOINT '.$this->identifier($name));
    }

    public function rollbackToSavepoint(string $name): void
    {
        $this->query('ROLLBACK TO SAVEPOINT '.$this->identifier($name));
    }

    public function releaseSavepoint(string $name): void
    {
        $this->query('RELEASE SAVEPOINT '.$this->identifier($name));
    }

    private function query(string $sql): void
    {
        if (!$this->mysqli->query($sql)) {
            throw new RuntimeException('Errore SQL ('.$sql.'): '.$this->mysqli->error);
        }
    }

    private function identifier(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException('Nome savepoint non valido: '.$name);
        }

        return '`'.$name.'`';
    }
}
```

- [ ] **Step 4: `Transaction`**

`class/Sql/Transaction.php`:

```php
<?php

namespace Wonder\Sql;

use mysqli;
use Throwable;

/**
 * Transazioni annidabili.
 *
 * Il livello esterno apre, conferma o annulla la transazione; i livelli
 * interni usano `SAVEPOINT wi_sp_<n>`. Un errore annulla il proprio livello
 * e viene rilanciato: se nessuno lo gestisce, annulla tutto.
 */
final class Transaction
{
    /** @var array<string, int> profondità per connessione */
    private static array $depth = [];

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function run(callable $callback, ?string $database = null): mixed
    {
        return self::runOn(new MysqliTransactionConnection(Connection::Connect($database ?? 'main')), $callback);
    }

    public static function runOn(TransactionConnection $connection, callable $callback): mixed
    {
        $key = $connection->id();
        $depth = self::$depth[$key] ?? 0;
        $savepoint = 'wi_sp_'.$depth;

        if ($depth === 0) {
            $connection->begin();
        } else {
            $connection->savepoint($savepoint);
        }

        self::$depth[$key] = $depth + 1;

        try {
            $result = $callback();
        } catch (Throwable $throwable) {
            self::restoreDepth($key, $depth);

            if ($depth === 0) {
                $connection->rollback();
            } else {
                $connection->rollbackToSavepoint($savepoint);
            }

            throw $throwable;
        }

        self::restoreDepth($key, $depth);

        if ($depth === 0) {
            $connection->commit();
        } else {
            $connection->releaseSavepoint($savepoint);
        }

        return $result;
    }

    public static function active(?string $database = null): bool
    {
        return self::activeForMysqli(Connection::Connect($database ?? 'main'));
    }

    public static function activeOn(TransactionConnection $connection): bool
    {
        return (self::$depth[$connection->id()] ?? 0) > 0;
    }

    public static function activeForMysqli(mysqli $mysqli): bool
    {
        return (self::$depth[MysqliTransactionConnection::idFor($mysqli)] ?? 0) > 0;
    }

    private static function restoreDepth(string $key, int $depth): void
    {
        if ($depth === 0) {
            unset(self::$depth[$key]);
            return;
        }

        self::$depth[$key] = $depth;
    }
}
```

- [ ] **Step 5: Funzione legacy**

In `app/function/sql.php`, subito dopo la funzione `sqlSelect(...)` (dopo la riga `return $SQL->Select( $table, $condition, $limit, $order, $orderDirection, $attributes );` e la sua `}`), aggiungere:

```php

    function sqlTransaction(callable $callback, string $database = 'main') {

        return \Wonder\Sql\Transaction::run($callback, $database);

    }
```

- [ ] **Step 6: Test e lint**

Run: `composer dump-autoload -q && php tests/Sql/TransactionTest.php && php -l app/function/sql.php`
Expected: `7 test, 0 falliti` e nessun errore di sintassi.

- [ ] **Step 7: Commit**

```bash
git add class/Sql/TransactionConnection.php class/Sql/MysqliTransactionConnection.php class/Sql/Transaction.php app/function/sql.php
git add -f tests/Sql/TransactionTest.php
git commit -m "Add nestable transactions with savepoints

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 2: Letture `FOR UPDATE` solo dentro una transazione

**Files:**
- Modify: `class/Sql/Query.php` (`Select`)
- Modify: `app/function/sql.php`
- Modify: `class/App/Model.php` (dopo `findById`)
- Test: `tests/Sql/SelectForUpdateTest.php`

**Interfaces:**
- Consumes: `Transaction::activeForMysqli()`, `MysqliTransactionConnection::idFor()` (Task 1).
- Produces: `Query::SelectForUpdate(...)` (stessa firma di `Select`), `sqlSelectForUpdate(...)`, `Model::findForUpdate(...)`, `Model::findByIdForUpdate(int|string $id)`.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/Sql/SelectForUpdateTest.php`:

```php
<?php
/** php tests/Sql/SelectForUpdateTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Sql\Query;

check('SelectForUpdate fuori da una transazione: eccezione prima di interrogare', function () {
    $mysqli = mysqli_init();
    $query = new Query($mysqli);

    try {
        $query->SelectForUpdate('document_sequences', ['id' => 1], 1);
        return false;
    } catch (RuntimeException $exception) {
        return str_contains($exception->getMessage(), 'transazione attiva');
    }
});

check('Select resta disponibile con la stessa firma', function () {
    $method = new ReflectionMethod(Query::class, 'Select');
    $forUpdate = new ReflectionMethod(Query::class, 'SelectForUpdate');
    $names = fn (ReflectionMethod $m) => array_map(fn ($p) => $p->getName(), $m->getParameters());
    return $method->isPublic() && $forUpdate->isPublic() && $names($method) === $names($forUpdate);
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/Sql/SelectForUpdateTest.php`
Expected: `2 test, 2 falliti` (metodo `SelectForUpdate` inesistente).

- [ ] **Step 3: `Query::SelectForUpdate()`**

In `class/Sql/Query.php`:

1. Aggiungere `use RuntimeException;` dopo `use mysqli;`.
2. Rinominare la firma esistente

```php
        public function Select( string | array $table, string | array | null $condition = null, string | int | null $limit = null, ?string $order = null, ?string $orderDirection = null, string | array $attributes = '*' ) 
```

in

```php
        private function runSelect( string | array $table, string | array | null $condition, string | int | null $limit, ?string $order, ?string $orderDirection, string | array $attributes, bool $forUpdate ) 
```

3. Nel corpo di `runSelect`, subito dopo la riga `$query .= ($safeLimit === '') ? "" : " LIMIT $safeLimit";`, aggiungere:

```php
            $query .= $forUpdate ? " FOR UPDATE" : "";
```

4. Subito prima di `private function runSelect(`, aggiungere:

```php
        public function Select( string | array $table, string | array | null $condition = null, string | int | null $limit = null, ?string $order = null, ?string $orderDirection = null, string | array $attributes = '*' ) 
        {

            return $this->runSelect($table, $condition, $limit, $order, $orderDirection, $attributes, false);

        }

        /**
         * Come `Select()` con `FOR UPDATE`: blocca le righe lette fino alla fine
         * della transazione. Consentito solo dentro `Transaction::run()`.
         */
        public function SelectForUpdate( string | array $table, string | array | null $condition = null, string | int | null $limit = null, ?string $order = null, ?string $orderDirection = null, string | array $attributes = '*' ) 
        {

            if (!Transaction::activeForMysqli($this->mysqli)) {
                throw new RuntimeException('SelectForUpdate richiede una transazione attiva (Transaction::run).');
            }

            return $this->runSelect($table, $condition, $limit, $order, $orderDirection, $attributes, true);

        }

```

- [ ] **Step 4: Funzione legacy**

In `app/function/sql.php`, subito prima di `function sqlTransaction(` (Task 1), aggiungere:

```php
    function sqlSelectForUpdate($table, $condition = null, $limit = null, $order = null, $orderDirection = null, $attributes = '*') {

        global $mysqli;

        $SQL = new Wonder\Sql\Query($mysqli);

        return $SQL->SelectForUpdate( $table, $condition, $limit, $order, $orderDirection, $attributes );

    }

```

- [ ] **Step 5: Model**

In `class/App/Model.php`, subito dopo il metodo `findById()`:

```php
    /**
     * Come `find()` con `SELECT ... FOR UPDATE`: solo dentro `Transaction::run()`.
     */
    public static function findForUpdate(
        string|array|null $condition = null,
        string|int|null $limit = null,
        ?string $order = null,
        ?string $orderDirection = null,
        string|array $columns = '*'
    ): mixed {
        $rows = static::query()->SelectForUpdate(
            static::$table,
            static::queryCondition($condition),
            $limit,
            $order,
            $orderDirection,
            $columns
        )->row;

        return static::decorateRows($rows);
    }

    /**
     * Come `findById()` con `SELECT ... FOR UPDATE`: solo dentro `Transaction::run()`.
     */
    public static function findByIdForUpdate(int|string $id): mixed
    {
        $row = static::query()->SelectForUpdate(
            static::$table,
            static::queryCondition(['id' => $id]),
            1
        )->row;

        return static::decorateRows($row);
    }
```

- [ ] **Step 6: Test e lint**

Run: `php tests/Sql/SelectForUpdateTest.php && php tests/Sql/TransactionTest.php && php -l class/Sql/Query.php && php -l class/App/Model.php && php -l app/function/sql.php`
Expected: `2 test, 0 falliti`, `7 test, 0 falliti`, nessun errore di sintassi.

- [ ] **Step 7: Commit**

```bash
git add class/Sql/Query.php class/App/Model.php app/function/sql.php
git add -f tests/Sql/SelectForUpdateTest.php
git commit -m "Add FOR UPDATE reads guarded by an active transaction

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 3: Lock nominali per i cron

**Files:**
- Create: `class/App/Support/NamedLockConnection.php`, `class/App/Support/MysqliNamedLockConnection.php`, `class/App/Support/NamedLock.php`
- Test: `tests/App/Support/NamedLockTest.php`

**Interfaces:**
- Produces: `interface NamedLockConnection { acquire(string $name, int $timeout): bool; release(string $name): void; }`; `NamedLock::run(string $name, callable $callback, int $timeout = 0, ?string $database = null): mixed`, `NamedLock::runOn(NamedLockConnection, string $name, callable $callback, int $timeout = 0): mixed`, `NamedLock::NOT_ACQUIRED`, `NamedLock::lockName(string $name): string`.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/App/Support/NamedLockTest.php`:

```php
<?php
/** php tests/App/Support/NamedLockTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\NamedLock;
use Wonder\App\Support\NamedLockConnection;

final class FakeNamedLockConnection implements NamedLockConnection
{
    /** @var array<string, bool> */
    public array $held = [];
    /** @var string[] */
    public array $log = [];

    public function acquire(string $name, int $timeout): bool
    {
        $this->log[] = 'acquire '.$name.' '.$timeout;

        if (isset($this->held[$name])) {
            return false;
        }

        $this->held[$name] = true;
        return true;
    }

    public function release(string $name): void
    {
        $this->log[] = 'release '.$name;
        unset($this->held[$name]);
    }
}

check('esegue il callback e rilascia', function () {
    $connection = new FakeNamedLockConnection();
    $value = NamedLock::runOn($connection, 'gestionale:invoices', fn () => 'fatto', 5);
    return $value === 'fatto'
        && $connection->log === ['acquire gestionale:invoices 5', 'release gestionale:invoices']
        && $connection->held === [];
});

check('lock già preso: NOT_ACQUIRED senza eseguire', function () {
    $connection = new FakeNamedLockConnection();
    $connection->held['gestionale:invoices'] = true;
    $executed = false;
    $value = NamedLock::runOn($connection, 'gestionale:invoices', function () use (&$executed) { $executed = true; });
    return $value === NamedLock::NOT_ACQUIRED && $executed === false;
});

check('eccezione: rilascio e rilancio', function () {
    $connection = new FakeNamedLockConnection();
    try {
        NamedLock::runOn($connection, 'cron', function () { throw new RuntimeException('boom'); });
        return false;
    } catch (RuntimeException) {
        return $connection->held === [] && end($connection->log) === 'release cron';
    }
});

check('nomi lunghi ridotti a 64 caratteri al massimo e stabili', function () {
    $long = str_repeat('gestionale:', 10);
    $short = NamedLock::lockName($long);
    return strlen($short) <= 64 && $short === NamedLock::lockName($long) && NamedLock::lockName('breve') === 'breve';
});

check('nome vuoto rifiutato', function () {
    try {
        NamedLock::runOn(new FakeNamedLockConnection(), '  ', fn () => null);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/App/Support/NamedLockTest.php`
Expected: `Interface "Wonder\App\Support\NamedLockConnection" not found`.

- [ ] **Step 3: Contratto, adattatore e `NamedLock`**

`class/App/Support/NamedLockConnection.php`:

```php
<?php

namespace Wonder\App\Support;

interface NamedLockConnection
{
    public function acquire(string $name, int $timeout): bool;

    public function release(string $name): void;
}
```

`class/App/Support/MysqliNamedLockConnection.php`:

```php
<?php

namespace Wonder\App\Support;

use mysqli;

/**
 * Lock nominali MySQL (`GET_LOCK` / `RELEASE_LOCK`), come `UpdateLock`.
 */
final class MysqliNamedLockConnection implements NamedLockConnection
{
    public function __construct(private readonly mysqli $mysqli)
    {
    }

    public function acquire(string $name, int $timeout): bool
    {
        $escaped = $this->mysqli->real_escape_string($name);
        $result = $this->mysqli->query("SELECT GET_LOCK('{$escaped}', ".max(0, $timeout).") AS acquired");

        if (!$result) {
            return false;
        }

        $row = $result->fetch_assoc();

        return (string) ($row['acquired'] ?? '0') === '1';
    }

    public function release(string $name): void
    {
        $escaped = $this->mysqli->real_escape_string($name);
        $this->mysqli->query("SELECT RELEASE_LOCK('{$escaped}')");
    }
}
```

`class/App/Support/NamedLock.php`:

```php
<?php

namespace Wonder\App\Support;

use InvalidArgumentException;
use Wonder\Sql\Connection;

/**
 * Esegue un callback solo se un lock nominale è libero (es. cron che non
 * deve partire due volte in parallelo). Il lock viene sempre rilasciato.
 */
final class NamedLock
{
    public const NOT_ACQUIRED = '__wonder_named_lock_not_acquired__';

    public static function run(string $name, callable $callback, int $timeout = 0, ?string $database = null): mixed
    {
        return self::runOn(
            new MysqliNamedLockConnection(Connection::Connect($database ?? 'main')),
            $name,
            $callback,
            $timeout
        );
    }

    public static function runOn(NamedLockConnection $connection, string $name, callable $callback, int $timeout = 0): mixed
    {
        $lockName = self::lockName($name);

        if (!$connection->acquire($lockName, $timeout)) {
            return self::NOT_ACQUIRED;
        }

        try {
            return $callback();
        } finally {
            $connection->release($lockName);
        }
    }

    /**
     * Nome valido per MySQL (massimo 64 caratteri): i nomi lunghi diventano
     * un hash stabile.
     */
    public static function lockName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Nome del lock vuoto.');
        }

        return strlen($name) <= 64 ? $name : 'wi_lock_'.sha1($name);
    }
}
```

- [ ] **Step 4: Test**

Run: `composer dump-autoload -q && php tests/App/Support/NamedLockTest.php`
Expected: `5 test, 0 falliti`.

- [ ] **Step 5: Commit**

```bash
git add class/App/Support/NamedLockConnection.php class/App/Support/MysqliNamedLockConnection.php class/App/Support/NamedLock.php
git add -f tests/App/Support/NamedLockTest.php
git commit -m "Add named locks for cron jobs

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 4: `PageSchema::docs()` e descriptor del pulsante

**Files:**
- Modify: `class/App/ResourceSchema/PageSchema.php`
- Create: `class/Backend/Support/DocsAction.php`
- Test: `tests/App/ResourceSchema/PageSchemaDocsTest.php`

**Interfaces:**
- Produces: `PageSchema::docs(string $url, string|array|null $pages = null): self`, `PageSchema::docsUrl(string $page): string` (stringa vuota se assente), `PageSchema::isAllowedDocsUrl(string $url): bool`; `DocsAction::descriptor(string $url, string $label): array`, `DocsAction::label(): string`.

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/App/ResourceSchema/PageSchemaDocsTest.php`:

```php
<?php
/** php tests/App/ResourceSchema/PageSchemaDocsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\Backend\Support\DocsAction;

final class DocsTestModel extends Model
{
    public static string $table = 'docs_test';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class DocsTestResource extends Resource
{
    public static string $model = DocsTestModel::class;
}

check('senza docs(): URL vuoto', function () {
    return PageSchema::for(DocsTestResource::class)->docsUrl('list') === '';
});

check('docs() senza pagine vale per list, create, edit e view', function () {
    $schema = PageSchema::for(DocsTestResource::class)->docs('https://guide.example.test/catalogo');
    foreach (['list', 'create', 'edit', 'view'] as $page) {
        if ($schema->docsUrl($page) !== 'https://guide.example.test/catalogo') {
            return false;
        }
    }
    return true;
});

check('docs() su pagine specifiche', function () {
    $schema = PageSchema::for(DocsTestResource::class)
        ->docs('/guida/elenco', 'list')
        ->docs('https://guide.example.test/scheda', ['edit', 'view']);
    return $schema->docsUrl('list') === '/guida/elenco'
        && $schema->docsUrl('edit') === 'https://guide.example.test/scheda'
        && $schema->docsUrl('create') === '';
});

check('URL non ammessi ignorati', function () {
    $schema = PageSchema::for(DocsTestResource::class)
        ->docs('javascript:alert(1)')
        ->docs('ftp://example.test/guida', 'list');
    return $schema->docsUrl('list') === '' && $schema->docsUrl('view') === '';
});

check('URL ammessi', function () {
    return PageSchema::isAllowedDocsUrl('https://guide.example.test/a')
        && PageSchema::isAllowedDocsUrl('http://guide.example.test/a')
        && PageSchema::isAllowedDocsUrl('/guida/a')
        && PageSchema::isAllowedDocsUrl('guida/a')
        && !PageSchema::isAllowedDocsUrl('//example.test/a')
        && !PageSchema::isAllowedDocsUrl('data:text/html,x')
        && !PageSchema::isAllowedDocsUrl('');
});

check('descriptor del pulsante', function () {
    return DocsAction::descriptor('https://guide.example.test/a', 'Guida') === [
        'label' => 'Guida',
        'href' => 'https://guide.example.test/a',
        'target' => '_blank',
        'class' => 'btn-outline-secondary',
        'icon' => 'bi bi-question-circle',
    ];
});

check('etichetta di ripiego senza traduzioni', function () {
    return DocsAction::label() === 'Guida';
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/App/ResourceSchema/PageSchemaDocsTest.php`
Expected: fallimenti per `docsUrl()` inesistente.

- [ ] **Step 3: `PageSchema`**

In `class/App/ResourceSchema/PageSchema.php`:

1. Nel costruttore, dopo la chiave `'actions' => [],` aggiungere `'docs' => [],`.
2. Subito prima del metodo `view(string $slot, ?string $view)`, aggiungere:

```php
    /**
     * Pulsante "Guida" nell'header: URL completo della pagina della guida.
     * Senza `$pages` vale per `list`, `create`, `edit` e `view`. Sono
     * ammessi solo URL `http(s)` o relativi; gli altri vengono ignorati.
     */
    public function docs(string $url, string|array|null $pages = null): self
    {
        $url = trim($url);

        if (!self::isAllowedDocsUrl($url)) {
            return $this;
        }

        $pages = $pages === null ? ['list', 'create', 'edit', 'view'] : $this->normalizeKeys($pages);

        foreach ($pages as $page) {
            $this->schema['docs'][$page] = $url;
        }

        return $this;
    }

    public function docsUrl(string $page): string
    {
        return (string) ($this->schema['docs'][trim($page)] ?? '');
    }

    public static function isAllowedDocsUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }

        if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1) {
            return preg_match('#^https?://#i', $url) === 1;
        }

        return true;
    }
```

- [ ] **Step 4: `DocsAction`**

`class/Backend/Support/DocsAction.php`:

```php
<?php

namespace Wonder\Backend\Support;

use Throwable;

/**
 * Pulsante "Guida" dell'header delle pagine backend.
 */
final class DocsAction
{
    private const LABEL_KEY = 'components.buttons.docs';

    /**
     * Descriptor compatibile con `PageActionNormalizer` e con il layout `show`.
     *
     * @return array<string, string>
     */
    public static function descriptor(string $url, string $label): array
    {
        return [
            'label' => $label,
            'href' => $url,
            'target' => '_blank',
            'class' => 'btn-outline-secondary',
            'icon' => 'bi bi-question-circle',
        ];
    }

    public static function label(): string
    {
        if (function_exists('__t')) {
            try {
                $label = __t(self::LABEL_KEY);

                if (is_string($label) && trim($label) !== '' && $label !== self::LABEL_KEY) {
                    return trim($label);
                }
            } catch (Throwable) {
            }
        }

        return 'Guida';
    }
}
```

- [ ] **Step 5: Test**

Run: `composer dump-autoload -q && php tests/App/ResourceSchema/PageSchemaDocsTest.php`
Expected: `7 test, 0 falliti`.

- [ ] **Step 6: Commit**

```bash
git add class/App/ResourceSchema/PageSchema.php class/Backend/Support/DocsAction.php
git add -f tests/App/ResourceSchema/PageSchemaDocsTest.php
git commit -m "Add docs link option to page schema

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 5: Pulsante "Guida" in elenco, form e scheda

**Files:**
- Modify: `class/Backend/Support/ResourcePagePresenter.php` (`form()`, `show()`)
- Modify: `class/Backend/Support/ResourceTableRenderer.php` (`toTable()`)
- Modify: `app/view/layout/backend/form.php`
- Modify: `resources/lang/{it,en,de,es,fr}/components.json`

**Interfaces:**
- Consumes: `PageSchema::docsUrl()`, `DocsAction::descriptor()`, `DocsAction::label()` (Task 4).
- Produces: variabili di vista `DOCS_URL`, `DOCS_LABEL` per il form; azione "Guida" in coda ad `ACTIONS` nella scheda.

- [ ] **Step 1: Scheda (`show`)**

In `ResourcePagePresenter::show()` sostituire:

```php
            'ACTIONS' => $this->pageActions('view', $item),
```

con:

```php
            'ACTIONS' => $this->withDocsAction('view', $this->pageActions('view', $item)),
```

e aggiungere dopo il metodo `pageActions()`:

```php
    /**
     * Aggiunge in coda il pulsante "Guida" se la pagina ha un URL della guida.
     *
     * @param array<int, array<string, mixed>> $actions
     * @return array<int, array<string, mixed>>
     */
    private function withDocsAction(string $page, array $actions): array
    {
        $url = $this->resourceClass::pageSchema()->docsUrl($page);

        if ($url === '') {
            return $actions;
        }

        return array_merge(
            $actions,
            PageActionNormalizer::normalize([DocsAction::descriptor($url, DocsAction::label())])
        );
    }
```

- [ ] **Step 2: Form**

In `ResourcePagePresenter::form()` aggiungere all'array restituito:

```php
            'DOCS_URL' => $this->resourceClass::pageSchema()->docsUrl($mode),
            'DOCS_LABEL' => DocsAction::label(),
```

In `app/view/layout/backend/form.php` sostituire:

```php
    <wi-card class="col-12">
        <h3>
            <?php if (!empty($BACK_URL)) { ?>
            <a href="<?=htmlspecialchars((string) ($BACK_URL ?? ''), ENT_QUOTES, 'UTF-8')?>" class="text-dark text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
            <?php } ?>
            <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
        </h3>
    </wi-card>
```

con:

```php
    <wi-card class="col-12">
        <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap">
            <h3 class="mb-0">
                <?php if (!empty($BACK_URL)) { ?>
                <a href="<?=htmlspecialchars((string) ($BACK_URL ?? ''), ENT_QUOTES, 'UTF-8')?>" class="text-dark text-decoration-none"><i class="bi bi-arrow-left-short"></i></a>
                <?php } ?>
                <?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?>
            </h3>
            <?php if (!empty($DOCS_URL)) { ?>
            <a href="<?=htmlspecialchars((string) $DOCS_URL, ENT_QUOTES, 'UTF-8')?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary">
                <i class="bi bi-question-circle"></i> <?=htmlspecialchars((string) ($DOCS_LABEL ?? ''), ENT_QUOTES, 'UTF-8')?>
            </a>
            <?php } ?>
        </div>
    </wi-card>
```

- [ ] **Step 3: Elenco**

In `ResourceTableRenderer::toTable()` sostituire:

```php
        $this->applyButtonsCustom($table);
```

con:

```php
        $this->applyButtonsCustom($table);
        $this->applyButtonDocs($table);
```

e aggiungere dopo il metodo `applyButtonsCustom()`:

```php
    private function applyButtonDocs(Table $table): void
    {
        $url = $this->resourceClass::pageSchema()->docsUrl('list');

        if ($url === '') {
            return;
        }

        $button = Button::to($url, DocsAction::label())
            ->variant('secondary')
            ->outline()
            ->blank();

        $table->addButtonCustom($this->renderButtonCustom($button), true);
    }
```

(`Button` è già importato; `DocsAction` è nello stesso namespace.)

- [ ] **Step 4: Traduzioni**

In ciascun `resources/lang/<lingua>/components.json`, subito dopo la riga `    "buttons" : {`, inserire la riga (con la virgola finale):

| Lingua | Riga |
|---|---|
| `it` | `        "docs" : "Guida",` |
| `en` | `        "docs" : "Guide",` |
| `de` | `        "docs" : "Anleitung",` |
| `es` | `        "docs" : "Guía",` |
| `fr` | `        "docs" : "Guide",` |

- [ ] **Step 5: Verifica**

Run: `for f in class/Backend/Support/ResourcePagePresenter.php class/Backend/Support/ResourceTableRenderer.php app/view/layout/backend/form.php; do php -l $f; done && for l in it en de es fr; do php -r "json_decode(file_get_contents('resources/lang/$l/components.json'), true, 512, JSON_THROW_ON_ERROR); echo '$l ok'.PHP_EOL;"; done && php tests/App/ResourceSchema/PageSchemaDocsTest.php && php tests/Backend/Table/ColumnFormatterFromResourcesTest.php`
Expected: nessun errore di sintassi, `ok` per le cinque lingue, test verdi.

- [ ] **Step 6: Commit**

```bash
git add class/Backend/Support/ResourcePagePresenter.php class/Backend/Support/ResourceTableRenderer.php app/view/layout/backend/form.php resources/lang
git commit -m "Render docs button in backend list, form and show pages

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 6: Classi fiscali

**Files:**
- Create: `class/Plugin/Custom/Fattura/Valori/AliquoteIva.php`, `class/Plugin/Custom/Fattura/Valori/EsigibilitaIva.php`
- Modify: `class/Plugin/Custom/Fattura/Valori/Natura.php`
- Test: `tests/Plugin/FatturaValoriTest.php`

**Interfaces:**
- Produces: `AliquoteIva::Valori` (`'22.00' => 'Aliquota ordinaria'`, …), `EsigibilitaIva::Valori` (`I`, `D`, `S`), `Natura::VALIDE` (list di codici), `Natura::valide(): array` (codice → descrizione).

- [ ] **Step 1: Scrivere il test che fallisce**

`tests/Plugin/FatturaValoriTest.php`:

```php
<?php
/** php tests/Plugin/FatturaValoriTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Plugin\Custom\Fattura\Valori\AliquoteIva;
use Wonder\Plugin\Custom\Fattura\Valori\EsigibilitaIva;
use Wonder\Plugin\Custom\Fattura\Valori\Natura;

check('aliquote italiane con due decimali', function () {
    return array_keys(AliquoteIva::Valori) === ['22.00', '10.00', '5.00', '4.00']
        && AliquoteIva::Valori['22.00'] === 'Aliquota ordinaria'
        && AliquoteIva::Valori['4.00'] === 'Aliquota minima';
});

check('esigibilità I, D, S', function () {
    return array_keys(EsigibilitaIva::Valori) === ['I', 'D', 'S'];
});

check('nature valide contenute in Valori, senza N2, N3 e N6', function () {
    foreach (Natura::VALIDE as $code) {
        if (!array_key_exists($code, Natura::Valori)) {
            return false;
        }
    }
    return !in_array('N2', Natura::VALIDE, true)
        && !in_array('N3', Natura::VALIDE, true)
        && !in_array('N6', Natura::VALIDE, true)
        && count(Natura::VALIDE) === 21;
});

check('valide() restituisce codice e descrizione nell\'ordine di Valori', function () {
    $valide = Natura::valide();
    return array_keys($valide) === Natura::VALIDE && $valide['N2.2'] === Natura::Valori['N2.2'];
});

check('Valori di Natura invariati (compatibilità)', function () {
    return array_key_exists('N2', Natura::Valori) && array_key_exists('N6', Natura::Valori);
});

summary();
```

- [ ] **Step 2: Eseguire il test e verificare che fallisce**

Run: `php tests/Plugin/FatturaValoriTest.php`
Expected: `Class "Wonder\Plugin\Custom\Fattura\Valori\AliquoteIva" not found`.

- [ ] **Step 3: Classi**

`class/Plugin/Custom/Fattura/Valori/AliquoteIva.php`:

```php
<?php

    namespace Wonder\Plugin\Custom\Fattura\Valori;

    class AliquoteIva {

        public const Valori = [
            "22.00" => "Aliquota ordinaria",
            "10.00" => "Aliquota ridotta",
            "5.00" => "Aliquota ridotta",
            "4.00" => "Aliquota minima"
        ];

    }
```

`class/Plugin/Custom/Fattura/Valori/EsigibilitaIva.php`:

```php
<?php

    namespace Wonder\Plugin\Custom\Fattura\Valori;

    class EsigibilitaIva {

        public const Valori = [
            "I" => "Esigibilità immediata",
            "D" => "Esigibilità differita",
            "S" => "Scissione dei pagamenti"
        ];

    }
```

In `class/Plugin/Custom/Fattura/Valori/Natura.php`, subito dopo la chiusura dell'array `Valori` (riga `        ];`), aggiungere:

```php

        /** Codici validi dal 1 gennaio 2021 (N2, N3 e N6 generici esclusi). */
        public const VALIDE = [
            "N1",
            "N2.1", "N2.2",
            "N3.1", "N3.2", "N3.3", "N3.4", "N3.5", "N3.6",
            "N4",
            "N5",
            "N6.1", "N6.2", "N6.3", "N6.4", "N6.5", "N6.6", "N6.7", "N6.8", "N6.9",
            "N7"
        ];

        /** Nature valide con descrizione, nell'ordine di `Valori`. */
        public static function valide(): array {

            return array_intersect_key(self::Valori, array_flip(self::VALIDE));

        }
```

- [ ] **Step 4: Test**

Run: `composer dump-autoload -q && php tests/Plugin/FatturaValoriTest.php`
Expected: `5 test, 0 falliti`.

- [ ] **Step 5: Commit**

```bash
git add class/Plugin/Custom/Fattura/Valori/AliquoteIva.php class/Plugin/Custom/Fattura/Valori/EsigibilitaIva.php class/Plugin/Custom/Fattura/Valori/Natura.php
git add -f tests/Plugin/FatturaValoriTest.php
git commit -m "Add Italian VAT rates, VAT chargeability and valid natures

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 7: Documentazione e verifica finale

**Files:**
- Modify: `docs/app/concetti/risorse/database.md` (prima di `## Soft-delete`)
- Modify: `docs/app/concetti/risorse/resource.md` (prima di `## Estendere oltre il CRUD`)
- Create: `docs/app/servizi/fatturapa-valori.md`
- Modify: `docs/app/SUMMARY.md` (dopo la voce Google Maps dei servizi)

- [ ] **Step 1: `database.md`**

Inserire prima di `## Soft-delete`:

````markdown
## Transazioni e lock

```php
use Wonder\Sql\Transaction;

$order = Transaction::run(function () use ($orderId) {
    $sequence = DocumentSequence::findForUpdate(['document_type' => 'order', 'year' => 2026, 'month' => 9], 1);
    // ... scarico del magazzino, righe, numero ...
    return Order::findById($orderId);
});
```

- `Transaction::run(callable, ?string $database = null)`: conferma a fine callback,
  annulla e rilancia su qualunque eccezione. Le transazioni annidate usano
  `SAVEPOINT wi_sp_<n>`: un errore interno gestito annulla solo il proprio livello.
- Legacy: `sqlTransaction(fn () => ..., 'main')`.
- Letture con lock: `Model::findForUpdate()`, `Model::findByIdForUpdate()`,
  `Query::SelectForUpdate()`, `sqlSelectForUpdate()`. Fuori da una transazione
  lanciano `RuntimeException`.
- `sql*()` e Model condividono la connessione di `Connection::Connect()`, quindi
  finiscono nella stessa transazione.

### Lock nominali per i cron

```php
use Wonder\App\Support\NamedLock;

$result = NamedLock::run('gestionale:invoices', fn () => sendInvoices());

if ($result === NamedLock::NOT_ACQUIRED) {
    return; // un'altra esecuzione è in corso
}
```

Basati su `GET_LOCK` / `RELEASE_LOCK`; il lock viene rilasciato anche in caso di
eccezione. I nomi oltre 64 caratteri diventano un hash stabile.

````

- [ ] **Step 2: `resource.md`**

Inserire prima di `## Estendere oltre il CRUD`:

````markdown
## Pulsante "Guida"

```php
public static function pageSchema(): PageSchema
{
    return PageSchema::for(static::class)
        ->docs('https://guide.example.it/catalogo/prodotti');             // list, create, edit, view
        // ->docs('https://guide.example.it/catalogo/scheda', ['edit']);  // solo alcune pagine
}
```

Il pulsante compare nell'header di elenco, form e scheda, si apre in una nuova
scheda ed è tradotto con `components.buttons.docs`. Sono ammessi solo URL
`http(s)` o relativi; ogni modulo compone l'URL dalla propria configurazione.

````

- [ ] **Step 3: Nuova pagina `servizi/fatturapa-valori.md`**

```markdown
# Valori FatturaPA

Classi con i codici ufficiali della fattura elettronica, in
`Wonder\Plugin\Custom\Fattura\Valori`. Ogni classe espone la costante `Valori`
(codice → descrizione).

| Classe | Contenuto |
|---|---|
| `TipiDocumento` | TD01–TD28 (TD24 e TD25 per la fattura differita) |
| `Natura` | N1–N7; `Natura::VALIDE` e `Natura::valide()` escludono N2, N3 e N6, non più validi dal 2021 |
| `AliquoteIva` | aliquote italiane `22.00`, `10.00`, `5.00`, `4.00` |
| `EsigibilitaIva` | `I` immediata, `D` differita, `S` scissione dei pagamenti |
| `RegimiFiscali` | RF01–RF19 |
| `Pagamento` | modalità di pagamento MP01–MP23 |
| `CondizioniPagamento` | TP01 a rate, TP02 completo, TP03 anticipo |

```php
use Wonder\Plugin\Custom\Fattura\Valori\Natura;

foreach (Natura::valide() as $code => $description) {
    // opzioni di una select per le operazioni a 0%
}
```
```

In `docs/app/SUMMARY.md`, subito dopo la riga `  * [Google Maps — mappe frontend](servizi/google-maps.md)`, aggiungere:

```markdown
  * [Valori FatturaPA](servizi/fatturapa-valori.md)
```

- [ ] **Step 4: Tutti i test**

Run: `fail=0; for t in $(git ls-files tests | grep -E '\.php$' | grep -v harness) tests/Sql/TransactionTest.php tests/Sql/SelectForUpdateTest.php tests/App/Support/NamedLockTest.php tests/App/ResourceSchema/PageSchemaDocsTest.php tests/Plugin/FatturaValoriTest.php; do r=$(php $t 2>&1 | tail -1); echo "$t → $r"; echo "$r" | grep -qiE " 0 falliti|tutti i test|all pass|test ok" || fail=1; done; echo "fail=$fail"`
Expected: `fail=0`.

- [ ] **Step 5: Commit**

```bash
git add docs/app/concetti/risorse/database.md docs/app/concetti/risorse/resource.md docs/app/servizi/fatturapa-valori.md docs/app/SUMMARY.md
git commit -m "Document transactions, named locks, docs button and FatturaPA values

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

- [ ] **Step 6: Verifiche con database rimandate**

Aggiungere alla TODO del gestionale, sotto la verifica del piano 1: transazione con `sqlInsert()` e `Model::create()` annullati insieme, `findForUpdate()` dentro e fuori transazione, `NamedLock` con due processi, pulsante "Guida" visibile in elenco, form e scheda.
