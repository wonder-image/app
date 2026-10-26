<?php
/** php tests/App/Support/RequestMemoTest.php */
declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/../../harness.php';
require __DIR__.'/../../../app/function/sql.php';
require __DIR__.'/../../../app/function/user/user.php';

use Wonder\App\Debug;
use Wonder\App\Support\Asset;
use Wonder\App\Support\RequestMemo;
use Wonder\Sql\Query;
use Wonder\Sql\QueryCounter;
use Wonder\Sql\Transaction;
use Wonder\Sql\TransactionConnection;

/** mysqli senza connessione: registra le query e risponde con righe preparate. */
final class MemoFakeMysqli extends mysqli
{
    /** @var string[] */
    public array $log = [];

    /** @var array<string, list<array<string, mixed>>> prefisso query => righe */
    public array $rows = [];

    public function __construct() {}

    public function real_escape_string(string $string): string
    {
        return addslashes($string);
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, int $result_mode = MYSQLI_STORE_RESULT)
    {
        $this->log[] = $query;

        foreach ($this->rows as $prefix => $rows) {
            if (str_starts_with($query, $prefix)) {
                return new MemoFakeResult($rows);
            }
        }

        // Scritture (o letture senza righe preparate).
        return str_starts_with($query, 'SELECT') || str_starts_with($query, 'SHOW') ? new MemoFakeResult([]) : true;
    }
}

final class MemoFakeResult
{
    public int $num_rows;

    /** @param list<array<string, mixed>> $rows */
    public function __construct(private array $rows)
    {
        $this->num_rows = count($rows);
    }

    public function fetch_assoc(): ?array { return $this->rows[0] ?? null; }

    public function fetch_all(int $mode = MYSQLI_NUM): array { return $this->rows; }
}

final class MemoFakeTransaction implements TransactionConnection
{
    public function id(): string { return 'memo:1'; }
    public function begin(): void {}
    public function commit(): void {}
    public function rollback(): void {}
    public function savepoint(string $name): void {}
    public function rollbackToSavepoint(string $name): void {}
    public function releaseSavepoint(string $name): void {}
}

function memoFresh(): MemoFakeMysqli {
    RequestMemo::reset();
    $GLOBALS['mysqli'] = new MemoFakeMysqli();
    $GLOBALS['DEFAULT'] = (object) ['colorUser' => ['blue' => ['color' => '#00f', 'contrast' => '#fff']]];
    $GLOBALS['PATH'] = (object) ['upload' => '/upload'];

    return $GLOBALS['mysqli'];
}

function memoUserRow(string $name): array {
    return [['id' => '7', 'name' => $name, 'surname' => 'Rossi', 'authority' => '[]', 'area' => '["frontend"]', 'color' => '', 'deleted' => 'false', 'active' => 'true']];
}

// --- RequestMemo ---

check('remember calcola una volta, forget per chiave e per scope, reset', function () {
    RequestMemo::reset();
    $calls = 0;
    $resolve = function () use (&$calls) { $calls++; return 'v'; };
    RequestMemo::remember('t', 'a', $resolve);
    RequestMemo::remember('t', 'a', $resolve);
    RequestMemo::put('t', 'b', null);
    $first = $calls === 1 && RequestMemo::has('t', 'b') && RequestMemo::get('t', 'b', 'x') === null;
    RequestMemo::forget('t', 'a');
    $second = !RequestMemo::has('t', 'a') && RequestMemo::has('t', 'b');
    RequestMemo::forget('t');
    RequestMemo::put('u', 'c', 1);
    RequestMemo::reset();
    return $first && $second && !RequestMemo::has('t', 'b') && !RequestMemo::has('u', 'c');
});

// --- Invalidazione nelle scritture di Query ---

check('Insert/Update/Delete/Truncate svuotano solo lo scope della propria tabella', function () {
    $mysqli = memoFresh();
    $query = new Query($mysqli);
    $ok = true;

    foreach ([
        // insert_id non è leggibile sul fake: il memo è già svuotato prima.
        function () use ($query) { try { $query->Insert('user', ['name' => 'a']); } catch (\Error) {} },
        fn () => $query->Update('user', ['name' => 'a'], 'id', 7),
        fn () => $query->Delete('user', ['id' => 7]),
        fn () => $query->Truncate('user'),
    ] as $write) {
        RequestMemo::put('user', 'k', 1);
        RequestMemo::put('css_font', 'k', 1);
        $write();
        $ok = $ok && !RequestMemo::has('user', 'k') && RequestMemo::has('css_font', 'k');
    }

    return $ok;
});

check('Transaction annullata: memo svuotato', function () {
    RequestMemo::reset();
    RequestMemo::put('user', 'k', 1);
    try {
        Transaction::runOn(new MemoFakeTransaction(), function () { throw new RuntimeException('boom'); });
    } catch (RuntimeException) {
    }
    return !RequestMemo::has('user', 'k');
});

check('Transaction confermata: memo intatto', function () {
    RequestMemo::reset();
    RequestMemo::put('user', 'k', 1);
    Transaction::runOn(new MemoFakeTransaction(), fn () => null);
    return RequestMemo::has('user', 'k');
});

check('le query di Query passano dal contatore in Debug', function () {
    $mysqli = memoFresh();
    $_ENV['APP_DEBUG'] = '1';
    Debug::reset();
    QueryCounter::reset();
    (new Query($mysqli))->Select('user', ['id' => 7], 1);
    (new Query($mysqli))->Select('user', ['id' => 8], 1);
    $groups = QueryCounter::groups();
    $_ENV['APP_DEBUG'] = '0';
    Debug::reset();
    QueryCounter::reset();
    return $groups === ['SELECT * FROM `user` WHERE id = ? LIMIT ?' => 2];
});

// --- sqlTableExists ---

check('sqlTableExists: true memoizzato, una sola SHOW TABLES', function () {
    $mysqli = memoFresh();
    $mysqli->rows["SHOW TABLES LIKE 'seo'"] = [['t' => 'seo']];
    $a = sqlTableExists('seo');
    $b = sqlTableExists('seo');
    return $a === true && $b === true && count($mysqli->log) === 1;
});

check('sqlTableExists: false non memoizzato', function () {
    $mysqli = memoFresh();
    $a = sqlTableExists('nuova');
    $mysqli->rows["SHOW TABLES LIKE 'nuova'"] = [['t' => 'nuova']];
    $b = sqlTableExists('nuova');
    return $a === false && $b === true && count($mysqli->log) === 2;
});

check('sqlTableExists: la chiave dipende dalla connessione', function () {
    $first = memoFresh();
    $first->rows["SHOW TABLES LIKE 'seo'"] = [['t' => 'seo']];
    sqlTableExists('seo');
    $second = new MemoFakeMysqli();
    $GLOBALS['mysqli'] = $second;
    return sqlTableExists('seo') === false && count($second->log) === 1;
});

// --- infoUser ---

check('infoUser per id: una lettura, oggetti distinti', function () {
    $mysqli = memoFresh();
    $mysqli->rows['SELECT * FROM `user`'] = memoUserRow('Mario');
    $a = infoUser(7);
    $a->name = 'modificato dal chiamante';
    $b = infoUser('7');
    return count($mysqli->log) === 1 && $b->name === 'Mario' && $b->fullName === 'Mario Rossi' && $a !== $b;
});

check('infoUser: dopo sqlModify su user la lettura è fresca', function () {
    $mysqli = memoFresh();
    $mysqli->rows['SELECT * FROM `user`'] = memoUserRow('Mario');
    infoUser(7);
    sqlModify('user', ['name' => 'Luigi'], 'id', 7);
    $mysqli->rows['SELECT * FROM `user`'] = memoUserRow('Luigi');
    return infoUser(7)->name === 'Luigi' && count($mysqli->log) === 3;
});

check('infoUser: forgetInfoUser per id e per tutti', function () {
    $mysqli = memoFresh();
    $mysqli->rows['SELECT * FROM `user`'] = memoUserRow('Mario');
    infoUser(7);
    forgetInfoUser(7);
    infoUser(7);
    forgetInfoUser();
    infoUser(7);
    return count($mysqli->log) === 3;
});

check('infoUser per email: nessun memo', function () {
    $mysqli = memoFresh();
    $mysqli->rows['SELECT * FROM `user`'] = memoUserRow('Mario');
    infoUser('m@example.com', 'email');
    infoUser('m@example.com', 'email');
    return count($mysqli->log) === 2;
});

// --- Asset::version ---

check('Asset::version: un solo stat per percorso, file assente non memoizzato', function () {
    RequestMemo::reset();
    $root = sys_get_temp_dir().'/wi-memo-'.bin2hex(random_bytes(4));
    mkdir($root.'/assets', 0777, true);
    file_put_contents($root.'/assets/a.css', 'a{}');
    touch($root.'/assets/a.css', 1721035200);
    $first = Asset::version('/assets/a.css', $root);
    touch($root.'/assets/a.css', 1721040000);
    $second = Asset::version('/assets/a.css', $root);
    $missing = Asset::version('/assets/b.css', $root);
    file_put_contents($root.'/assets/b.css', 'b{}');
    touch($root.'/assets/b.css', 1721040000);
    $created = Asset::version('/assets/b.css', $root);
    RequestMemo::forget(Asset::VERSION_MEMO);
    $fresh = Asset::version('/assets/a.css', $root);
    array_map('unlink', glob($root.'/assets/*'));
    rmdir($root.'/assets');
    rmdir($root);
    return $first === '/assets/a.css?v=1721035200'
        && $second === $first
        && $missing === '/assets/b.css'
        && $created === '/assets/b.css?v=1721040000'
        && $fresh === '/assets/a.css?v=1721040000';
});

RequestMemo::reset();

summary();
