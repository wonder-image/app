<?php
/**
 * php tests/Console/DbBackupTest.php
 *
 * `forge db:backup` / `DatabaseBackup`: nome file, rotazione, password mai
 * negli argomenti di mysqldump, nessun file parziale su errore, dump PHP.
 * mysqldump è sostituito da script shell finti; il dump PHP usa un mysqli finto.
 */
declare(strict_types=1);

use Wonder\App\Support\DatabaseBackup;

require __DIR__ . '/../harness.php';
require __DIR__ . '/../../vendor/autoload.php';

$base = sys_get_temp_dir().'/wi-dbbackup-test-'.bin2hex(random_bytes(4));
mkdir($base, 0777, true);

$cleanup = static function (string $dir) use (&$cleanup): void {
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir.'/'.$entry;
        is_dir($path) && !is_link($path) ? $cleanup($path) : unlink($path);
    }
    rmdir($dir);
};
register_shutdown_function(static fn () => is_dir($base) && $cleanup($base));

$credentials = (object) [
    'hostname' => 'db.example.test:3307',
    'username' => 'shop',
    'password' => 'S3gr"eto\\pw',
    'charset' => 'utf8mb4',
    'database' => ['main' => 'shop_db'],
];

$script = static function (string $name, string $body) use ($base): string {
    $path = $base.'/bin-'.$name;
    file_put_contents($path, "#!/bin/sh\n".$body."\n");
    chmod($path, 0755);
    return $path;
};

$entries = static fn (string $dir): array => array_values(array_diff(scandir($dir) ?: [], ['.', '..']));

check('nome file Ymd-His.sql.gz', fn () =>
    DatabaseBackup::fileName(new DateTimeImmutable('2026-10-20 09:05:03')) === '20261020-090503.sql.gz');

check('cartella di default ROOT/storage/backup', fn () =>
    DatabaseBackup::defaultDirectory('/srv/sito/') === '/srv/sito/storage/backup');

check('rotazione: restano gli ultimi N, gli altri file non si toccano', function () use ($base, $entries) {
    $dir = $base.'/rotate';
    mkdir($dir);
    $names = ['20260101-000000', '20260102-000000', '20260103-120000', '20260103-120001', '20260201-000000', '20250101-000000', '20261231-235959'];
    foreach ($names as $name) {
        touch($dir.'/'.$name.'.sql.gz');
    }
    touch($dir.'/note.txt');
    touch($dir.'/.20260101-000000.sql.gz.abcd.tmp');

    $removed = DatabaseBackup::rotate($dir, 3);
    $left = $entries($dir);
    sort($left);

    return count($removed) === 4
        && $left === ['.20260101-000000.sql.gz.abcd.tmp', '20260103-120001.sql.gz', '20260201-000000.sql.gz', '20261231-235959.sql.gz', 'note.txt'];
});

check('rotazione con keep < 1 rifiutata', function () use ($base) {
    try {
        DatabaseBackup::rotate($base, 0);
    } catch (InvalidArgumentException) {
        return true;
    }
    return false;
});

check('argomenti mysqldump: defaults-extra-file primo, opzioni esatte, nessuna password', function () use ($credentials) {
    $args = DatabaseBackup::mysqldumpArguments('/usr/bin/mysqldump', '/tmp/x.cnf', 'shop_db');

    return $args === ['/usr/bin/mysqldump', '--defaults-extra-file=/tmp/x.cnf', '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--set-gtid-purged=OFF', '--routines', '--triggers', 'shop_db']
        && !str_contains(implode(' ', $args), $credentials->password)
        && !str_contains(implode(' ', $args), 'S3gr');
});

check('file di opzioni: password quotata ed escapata, host e porta separati', fn () =>
    DatabaseBackup::defaultsFileContent($credentials) === "[client]\nuser=\"shop\"\npassword=\"S3gr\\\"eto\\\\pw\"\nhost=\"db.example.test\"\nport=3307\ndefault-character-set=\"utf8mb4\"\n");

check('mysqldump ok: gzip completo, password solo nel file 0600 poi rimosso', function () use ($base, $credentials, $script, $entries) {
    $log = $base.'/ok-log';
    mkdir($log);
    $binary = $script('ok', implode("\n", [
        'printf "%s\n" "$@" > '.escapeshellarg($log.'/args'),
        'f="${1#--defaults-extra-file=}"',
        'cp "$f" '.escapeshellarg($log.'/defaults'),
        'ls -l "$f" | cut -c1-10 > '.escapeshellarg($log.'/mode'),
        'echo "$f" > '.escapeshellarg($log.'/path'),
        'echo "CREATE TABLE t (id int);"',
        'echo "INSERT INTO t VALUES (1);"',
    ]));

    $dir = $base.'/ok/storage/backup';
    $backup = (new DatabaseBackup($binary, null, $credentials))->run($dir, 5, new DateTimeImmutable('2026-10-20 10:00:00'));

    $dump = gzdecode((string) file_get_contents($backup->path));
    $args = (string) file_get_contents($log.'/args');

    return $backup->path === $dir.'/20261020-100000.sql.gz'
        && $backup->method === 'mysqldump'
        && $dump === "CREATE TABLE t (id int);\nINSERT INTO t VALUES (1);\n"
        && !str_contains($args, 'S3gr')
        && str_contains((string) file_get_contents($log.'/defaults'), 'password="S3gr\\"eto\\\\pw"')
        && trim((string) file_get_contents($log.'/mode')) === '-rw-------'
        && !file_exists(trim((string) file_get_contents($log.'/path')))
        && (fileperms($dir) & 0777) === 0750
        && $entries($dir) === ['20261020-100000.sql.gz'];
});

check('mysqldump exit ≠ 0: eccezione, nessun file parziale, credenziali rimosse', function () use ($base, $credentials, $script, $entries) {
    $binary = $script('fail', implode("\n", [
        'echo "${1#--defaults-extra-file=}" > '.escapeshellarg($base.'/fail-path'),
        'echo "CREATE TABLE parziale"',
        'echo "mysqldump: Got error: 1045: Access denied" >&2',
        'exit 2',
    ]));
    $dir = $base.'/fail';

    try {
        (new DatabaseBackup($binary, null, $credentials))->run($dir);
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'exit 2')
            && str_contains($e->getMessage(), 'Access denied')
            && $entries($dir) === []
            && !file_exists(trim((string) file_get_contents($base.'/fail-path')));
    }

    return false;
});

check('mysqldump exit 0 con stderr non vuoto: eccezione, nessun file', function () use ($base, $credentials, $script, $entries) {
    $binary = $script('warn', "echo 'CREATE TABLE t (id int);'\necho 'mysqldump: Error 2013: Lost connection' >&2\nexit 0");
    $dir = $base.'/warn';

    try {
        (new DatabaseBackup($binary, null, $credentials))->run($dir);
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'Lost connection') && $entries($dir) === [];
    }

    return false;
});

check('mysqldump senza output: eccezione, nessun file', function () use ($base, $credentials, $script, $entries) {
    $binary = $script('empty', 'exit 0');
    $dir = $base.'/empty';

    try {
        (new DatabaseBackup($binary, null, $credentials))->run($dir);
    } catch (RuntimeException) {
        return $entries($dir) === [];
    }

    return false;
});

check('run applica la rotazione (--keep)', function () use ($base, $credentials, $script, $entries) {
    $dir = $base.'/keep';
    mkdir($dir);
    foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $name) {
        touch($dir.'/'.$name.'.sql.gz');
    }
    $binary = $script('keep', "echo 'SELECT 1;'");
    $backup = (new DatabaseBackup($binary, null, $credentials))->run($dir, 2, new DateTimeImmutable('2026-10-20 11:00:00'));

    return count($backup->removed) === 2
        && $entries($dir) === ['20260103-000000.sql.gz', '20261020-110000.sql.gz'];
});

/** mysqli finto: tabelle, viste e righe in memoria. */
$fakeMysqli = static fn (array $tables, array $failOn = []) => new class($tables, $failOn) extends mysqli {
    public array $queries = [];

    public function __construct(private array $tables, private array $failOn) {}

    #[\ReturnTypeWillChange]
    public function real_escape_string($string) { return addslashes($string); }

    #[\ReturnTypeWillChange]
    public function character_set_name() { return 'utf8mb4'; }

    #[\ReturnTypeWillChange]
    public function query($query, $resultMode = MYSQLI_STORE_RESULT)
    {
        $this->queries[] = [$query, $resultMode];

        foreach ($this->failOn as $needle) {
            if (str_contains($query, $needle)) {
                return false;
            }
        }

        $rows = match (true) {
            $query === 'SHOW FULL TABLES' => array_map(fn ($name) => [$name, $this->tables[$name]['type'] ?? 'BASE TABLE'], array_keys($this->tables)),
            (bool) preg_match('/^SHOW CREATE TABLE `(.+)`$/', $query, $m) => [[$m[1], $this->tables[$m[1]]['create']]],
            (bool) preg_match('/^SELECT \* FROM `(.+)`$/', $query, $m) => $this->tables[$m[1]]['rows'],
            default => throw new RuntimeException('Query inattesa: '.$query),
        };

        return new class($rows) {
            public function __construct(private array $rows) {}
            public function fetch_row(): ?array { return array_shift($this->rows); }
            public function free(): void {}
        };
    }
};

$tables = [
    'products' => [
        'create' => "CREATE TABLE `products` (\n  `id` int NOT NULL,\n  `name` varchar(50) DEFAULT NULL,\n  PRIMARY KEY (`id`)\n)",
        'rows' => [['1', "Tazza d'autore"], ['2', null]],
    ],
    'v_products' => ['type' => 'VIEW', 'create' => '', 'rows' => []],
];

check('dump PHP: DROP + CREATE TABLE + INSERT con escape e NULL, viste escluse', function () use ($base, $fakeMysqli, $tables) {
    $connection = $fakeMysqli($tables);
    $dir = $base.'/php';
    $backup = (new DatabaseBackup(false, $connection))->run($dir, 5, new DateTimeImmutable('2026-10-20 12:00:00'));
    $dump = (string) gzdecode((string) file_get_contents($backup->path));
    $selects = array_values(array_filter($connection->queries, fn ($q) => str_starts_with($q[0], 'SELECT')));

    return $backup->method === 'php'
        && str_contains($dump, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;")
        && str_contains($dump, "DROP TABLE IF EXISTS `products`;\nCREATE TABLE `products` (")
        && str_contains($dump, "INSERT INTO `products` VALUES\n('1','Tazza d\\'autore'),\n('2',NULL);")
        && !str_contains($dump, 'v_products')
        && str_ends_with($dump, "SET FOREIGN_KEY_CHECKS=1;\n")
        && count($selects) === 1 && $selects[0][1] === MYSQLI_USE_RESULT;
});

check('dump PHP con query fallita: eccezione, nessun file parziale', function () use ($base, $fakeMysqli, $tables, $entries) {
    $dir = $base.'/php-fail';

    try {
        (new DatabaseBackup(false, $fakeMysqli($tables, ['SELECT * FROM `products`'])))->run($dir);
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'SELECT * FROM `products`') && $entries($dir) === [];
    }

    return false;
});

check('update: backup solo se il database ha tabelle oltre a app_update_runs', function () {
    $runner = static fn (array $tables) => new Wonder\App\UpdateRunner(new class($tables) extends mysqli {
        public function __construct(private array $tables) {}

        #[\ReturnTypeWillChange]
        public function query($query, $resultMode = MYSQLI_STORE_RESULT)
        {
            return new class(array_map(fn ($t) => [$t], $this->tables)) {
                public function __construct(private array $rows) {}
                public function fetch_row(): ?array { return array_shift($this->rows); }
                public function free(): void {}
            };
        }
    });
    $has = static fn (Wonder\App\UpdateRunner $r): bool => (new ReflectionMethod($r, 'hasApplicationTables'))->invoke($r);

    return $has($runner([])) === false
        && $has($runner(['app_update_runs'])) === false
        && $has($runner(['app_update_runs', 'user'])) === true;
});

summary();
