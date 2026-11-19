<?php
/** php tests/App/Migrations/MigratorTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Environment;
use Wonder\App\Migrations\Migrator;

/** Database finto: registro in memoria, lock, SHOW COLUMNS e ALTER registrati. */
$makeDb = static fn () => new class extends mysqli {
    public bool $registry = false;
    public bool $lockBusy = false;
    /** @var array<string, array<string, mixed>> */
    public array $rows = [];
    /** @var string[] */
    public array $alters = [];
    /** @var string[] */
    public array $columns = ['vecchia'];

    public function __construct() {}

    #[\ReturnTypeWillChange]
    public function real_escape_string($string) { return addslashes($string); }

    #[\ReturnTypeWillChange]
    public function query($query, $resultMode = MYSQLI_STORE_RESULT)
    {
        $rows = [];

        if (str_starts_with($query, "SHOW TABLES LIKE 'app_migrations'")) {
            $rows = $this->registry ? [['x']] : [];
        } elseif (str_starts_with($query, 'CREATE TABLE IF NOT EXISTS `app_migrations`')) {
            $this->registry = true;
            return true;
        } elseif (str_contains($query, 'GET_LOCK')) {
            $rows = [['lock_status' => $this->lockBusy ? 0 : 1]];
        } elseif (str_contains($query, 'RELEASE_LOCK')) {
            return true;
        } elseif (str_starts_with($query, 'SELECT `migration`')) {
            $rows = array_values($this->rows);
        } elseif (str_starts_with($query, 'SELECT MAX(`batch`)')) {
            $rows = [['b' => $this->rows ? max(array_column($this->rows, 'batch')) : 0]];
        } elseif (str_starts_with($query, 'INSERT INTO `app_migrations`')) {
            preg_match("/VALUES \('([^']*)', '([^']*)', (\d+), '([^']*)'/", $query, $m);
            $this->rows[$m[1]] = ['migration' => $m[1], 'source' => $m[2], 'batch' => (int) $m[3], 'checksum' => $m[4], 'applied_at' => 'ora'];
            return true;
        } elseif (str_starts_with($query, 'SHOW COLUMNS FROM')) {
            preg_match("/LIKE '([^']*)'/", $query, $m);
            $rows = in_array($m[1], $this->columns, true) ? [['x']] : [];
        } elseif (str_starts_with($query, 'SHOW INDEX')) {
            $rows = [];
        } elseif (str_starts_with($query, 'ALTER TABLE')) {
            $this->alters[] = $query;
            if (str_contains($query, 'DROP COLUMN `vecchia`')) { $this->columns = []; }
            return true;
        }

        return new class($rows) {
            public int $num_rows;
            public function __construct(private array $rows) { $this->num_rows = count($rows); }
            public function fetch_assoc(): ?array { return array_shift($this->rows); }
            public function free(): void {}
        };
    }
};

$tmp = sys_get_temp_dir().'/wi-migrator-'.bin2hex(random_bytes(4));
$write = function (string $dir, string $name, string $body) use ($tmp): void {
    @mkdir("$tmp/$dir", 0755, true);
    file_put_contents("$tmp/$dir/$name.php", "<?php\nreturn new class {\n$body\n};\n");
};
$add = '    public function up(\Wonder\Sql\Migration $db): void { $db->addColumnIfMissing("articoli", "nuova", "INT NULL"); }';
$drop = '    public function destructive(): bool { return true; }
    public function up(\Wonder\Sql\Migration $db): void { $db->dropColumn("articoli", "vecchia"); }';
$boom = '    public function up(\Wonder\Sql\Migration $db): void { throw new RuntimeException("boom"); }';
$sneaky = '    public function up(\Wonder\Sql\Migration $db): void { $db->dropColumn("articoli", "vecchia"); }';

$write('app', '20260101000000_base_app', $add);
$write('site', '20260301000000_seconda', $add);
$write('site', '20260201000000_prima', $add);

$sources = [['source' => 'app', 'dir' => "$tmp/app"], ['source' => 'site', 'dir' => "$tmp/site"]];

$env = function (string $value, callable $fn) {
    $previous = $_ENV['APP_ENV'] ?? 'production';
    $_ENV['APP_ENV'] = $value;
    putenv('APP_ENV='.$value);
    Environment::reset();
    try { return $fn(); } finally {
        $_ENV['APP_ENV'] = $previous;
        putenv('APP_ENV='.$previous);
        Environment::reset();
    }
};

check('primo avvio: app registrate come già applicate (batch 0) senza eseguirle, site eseguite in ordine', function () use ($makeDb, $sources, $env) {
    $db = $makeDb();
    $r = $env('local', fn () => (new Migrator($db, $sources))->runPending());

    return $r->success
        && array_column($r->applied, 'id') === ['20260201000000_prima', '20260301000000_seconda']
        && $db->rows['20260101000000_base_app']['batch'] === 0
        && $db->rows['20260201000000_prima']['batch'] === 1
        && count($db->alters) === 2; // solo le due del sito; quella di app non gira
});

check('riesecuzione: nulla in attesa, nessun ALTER nuovo', function () use ($makeDb, $sources, $env) {
    $db = $makeDb();
    $m = new Migrator($db, $sources);
    $env('local', fn () => $m->runPending());
    $before = count($db->alters);
    $r = $env('local', fn () => $m->runPending());

    return $r->success && $r->applied === [] && count($db->alters) === $before && $m->pending() === [];
});

check('pending() non scrive nulla e al primo avvio ignora app', function () use ($makeDb, $sources) {
    $db = $makeDb();
    $ids = array_column((new Migrator($db, $sources))->pending(), 'id');

    return $ids === ['20260201000000_prima', '20260301000000_seconda'] && $db->registry === false && $db->rows === [];
});

check('lock occupato: stato busy, niente eseguito', function () use ($makeDb, $sources) {
    $db = $makeDb();
    $db->lockBusy = true;
    $r = (new Migrator($db, $sources))->runPending();

    return !$r->success && $r->status === 'busy' && $db->rows === [] && $db->alters === [];
});

check('pretend: elenca l\'SQL e non scrive né registro né ALTER', function () use ($makeDb, $sources, $env) {
    $db = $makeDb();
    $r = $env('local', fn () => (new Migrator($db, $sources))->runPending(['pretend' => true]));

    return $r->success && count($r->applied) === 2 && str_contains($r->applied[0]['statements'][0], 'ADD COLUMN `nuova`')
        && $db->alters === [] && $db->registry === false;
});

check('fallimento: si ferma, la migrazione non è registrata e il resto resta in attesa', function () use ($makeDb, $write, $tmp, $boom, $add, $sources, $env) {
    $write('fail', '20260210000000_rotta', $boom);
    $write('fail', '20260220000000_dopo', $add);
    $src = [['source' => 'app', 'dir' => "$tmp/app"], ['source' => 'site', 'dir' => "$tmp/fail"]];
    $db = $makeDb();
    $r = $env('local', fn () => (new Migrator($db, $src))->runPending());

    return !$r->success && $r->status === 'failed' && str_contains((string) $r->error, 'boom')
        && !isset($db->rows['20260210000000_rotta']) && !isset($db->rows['20260220000000_dopo'])
        && count($r->pending) === 2;
});

check('distruttiva in production senza force: saltata e segnalata', function () use ($makeDb, $write, $tmp, $drop, $env) {
    $write('d1', '20260401000000_togli', $drop);
    $src = [['source' => 'site', 'dir' => "$tmp/d1"]];
    $db = $makeDb();
    $r = $env('production', fn () => (new Migrator($db, $src))->runPending());

    return count($r->skipped) === 1 && $r->applied === [] && $db->columns === ['vecchia'];
});

check('distruttiva in production con force, e in local: parte', function () use ($makeDb, $tmp, $env) {
    $src = [['source' => 'site', 'dir' => "$tmp/d1"]];
    $db = $makeDb();
    $a = $env('production', fn () => (new Migrator($db, $src))->runPending(['force' => true]));
    $db2 = $makeDb();
    $b = $env('local', fn () => (new Migrator($db2, $src))->runPending());

    return $a->success && $db->columns === [] && $b->success && $db2->columns === [];
});

check('staging come production: distruttiva saltata senza force', function () use ($makeDb, $tmp, $env) {
    $db = $makeDb();
    $r = $env('staging', fn () => (new Migrator($db, [['source' => 'site', 'dir' => "$tmp/d1"]]))->runPending());

    return count($r->skipped) === 1 && $db->columns === ['vecchia'];
});

check('dropColumn senza destructive(): la migrazione fallisce e nulla viene tolto', function () use ($makeDb, $write, $tmp, $sneaky, $env) {
    $write('s1', '20260501000000_furba', $sneaky);
    $db = $makeDb();
    $r = $env('local', fn () => (new Migrator($db, [['source' => 'site', 'dir' => "$tmp/s1"]]))->runPending());

    return !$r->success && str_contains((string) $r->error, 'destructive') && $db->columns === ['vecchia'];
});

check('with_schema: lo schema gira dopo le migrazioni, non con pretend né se una fallisce', function () use ($makeDb, $sources, $tmp, $env) {
    $calls = [];
    $runner = function () use (&$calls) { $calls[] = 'schema'; return ['tables' => 3]; };
    $db = $makeDb();
    $r = $env('local', fn () => (new Migrator($db, $sources))->runPending(['with_schema' => true, 'schema_runner' => $runner]));
    $p = $env('local', fn () => (new Migrator($makeDb(), $sources))->runPending(['with_schema' => true, 'pretend' => true, 'schema_runner' => $runner]));
    $f = $env('local', fn () => (new Migrator($makeDb(), [['source' => 'site', 'dir' => "$tmp/fail"]]))->runPending(['with_schema' => true, 'schema_runner' => $runner]));

    return $r->schema === ['tables' => 3] && $calls === ['schema'] && $p->schema === null && $f->schema === null;
});

check('status e check: file modificato dopo l\'applicazione e file sparito', function () use ($makeDb, $write, $tmp, $add, $sources, $env) {
    $db = $makeDb();
    $m = new Migrator($db, $sources);
    $env('local', fn () => $m->runPending());
    $clean = $m->check() === [];
    file_put_contents("$tmp/site/20260201000000_prima.php", "<?php\n// cambiata\nreturn new class {\n$add\n};\n");
    unlink("$tmp/site/20260301000000_seconda.php");
    $states = array_column($m->status(), 'state', 'id');
    $write('site', '20260301000000_seconda', $add);

    return $clean && $states['20260201000000_prima'] === 'modified' && $states['20260301000000_seconda'] === 'missing'
        && count($m->check()) === 1;
});

check('id duplicato fra sorgenti: errore', function () use ($makeDb, $write, $tmp, $add) {
    $write('x1', '20260601000000_uguale', $add);
    $write('x2', '20260601000000_uguale', $add);
    try {
        (new Migrator($makeDb(), [['source' => 'a', 'dir' => "$tmp/x1"], ['source' => 'b', 'dir' => "$tmp/x2"]]))->files();
        return false;
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'duplicata');
    }
});

exec('rm -rf '.escapeshellarg($tmp));

summary();
