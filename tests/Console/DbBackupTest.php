<?php
/**
 * php tests/Console/DbBackupTest.php
 *
 * `forge db:backup` / `DatabaseBackup`: nome file, rotazione, password mai
 * negli argomenti di mysqldump, nessun file parziale su errore, dump PHP,
 * ripiego sul dump PHP se mysqldump fallisce, MariaDB senza --set-gtid-purged,
 * opzione `backup` dell'API di update.
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

const MYSQL_VERSION = 'mysqldump  Ver 8.0.36 for Linux on x86_64 (MySQL Community Server - GPL)';
const MARIADB_VERSION = 'mysqldump  Ver 10.19 Distrib 10.11.6-MariaDB, for debian-linux-gnu (x86_64)';
const MARKER = '-- Dump completed on 2026-10-20 10:00:00';

// Lo script risponde a `--version` (rilevamento MariaDB) e a `--print-defaults`
// (opzioni dei file di opzioni, formato reale di mysqldump 9.6) prima del corpo.
$script = static function (string $name, string $body, string $version = MYSQL_VERSION, string $optionFiles = '--user=shop --password=*****') use ($base): string {
    $path = $base.'/bin-'.$name;
    $head = 'if [ "$1" = "--version" ]; then echo '.escapeshellarg($version).'; exit 0; fi'."\n"
        .'if [ "$2" = "--print-defaults" ]; then echo "mysqldump would have been started with the following arguments:"; echo '.escapeshellarg($optionFiles.' ').'; exit 0; fi';
    file_put_contents($path, "#!/bin/sh\n".$head."\n".$body."\n");
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

check('argomenti mysqldump con MariaDB: niente --set-gtid-purged, resto invariato', fn () =>
    DatabaseBackup::mysqldumpArguments('/usr/bin/mysqldump', '/tmp/x.cnf', 'shop_db', true)
        === ['/usr/bin/mysqldump', '--defaults-extra-file=/tmp/x.cnf', '--single-transaction', '--skip-lock-tables', '--no-tablespaces', '--routines', '--triggers', 'shop_db']);

check('rilevamento MariaDB da --version e da VERSION()', fn () =>
    DatabaseBackup::isMariaDb(MARIADB_VERSION)
        && DatabaseBackup::isMariaDb('10.11.6-MariaDB-0+deb12u1-log')
        && !DatabaseBackup::isMariaDb(MYSQL_VERSION)
        && !DatabaseBackup::isMariaDb('8.0.36')
        && !DatabaseBackup::isMariaDb(''));

check('commenti spenti: --skip-comments, --comments=0, --compact, varianti da file di opzioni; vale l\'ultima', fn () =>
    DatabaseBackup::commentsDisabled(['--user=shop', '--skip-comments'])
        && DatabaseBackup::commentsDisabled(['--skip_comments'])
        && DatabaseBackup::commentsDisabled(['--loose-skip-comments'])
        && DatabaseBackup::commentsDisabled(['--comments=0'])
        && DatabaseBackup::commentsDisabled(['--comments=OFF'])
        && DatabaseBackup::commentsDisabled(['--compact'])
        && DatabaseBackup::commentsDisabled(['--disable-comments'])
        && !DatabaseBackup::commentsDisabled([])
        && !DatabaseBackup::commentsDisabled(['--user=shop', '--password=*****', '--skip-dump-date'])
        && !DatabaseBackup::commentsDisabled(['--skip-comments', '--comments'])
        && !DatabaseBackup::commentsDisabled(['--comments=1'])
        && !DatabaseBackup::commentsDisabled(['--compact=0'])
        && !DatabaseBackup::commentsDisabled(['skip-comments'])
        && !DatabaseBackup::commentsDisabled(DatabaseBackup::mysqldumpArguments('/usr/bin/mysqldump', '/tmp/x.cnf', 'shop_db')));

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
        'echo '.escapeshellarg(MARKER),
    ]));

    $dir = $base.'/ok/storage/backup';
    $backup = (new DatabaseBackup($binary, null, $credentials))->run($dir, 5, new DateTimeImmutable('2026-10-20 10:00:00'));

    $dump = gzdecode((string) file_get_contents($backup->path));
    $args = (string) file_get_contents($log.'/args');

    return $backup->path === $dir.'/20261020-100000.sql.gz'
        && $backup->method === 'mysqldump'
        && $backup->fallback === null
        && $dump === "CREATE TABLE t (id int);\nINSERT INTO t VALUES (1);\n".MARKER."\n"
        && str_contains($args, '--set-gtid-purged=OFF')
        && !str_contains($args, 'S3gr')
        && str_contains((string) file_get_contents($log.'/defaults'), 'password="S3gr\\"eto\\\\pw"')
        && trim((string) file_get_contents($log.'/mode')) === '-rw-------'
        && !file_exists(trim((string) file_get_contents($log.'/path')))
        && (fileperms($dir) & 0777) === 0750
        && $entries($dir) === ['20261020-100000.sql.gz'];
});

check('run applica la rotazione (--keep)', function () use ($base, $credentials, $script, $entries) {
    $dir = $base.'/keep';
    mkdir($dir);
    foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $name) {
        touch($dir.'/'.$name.'.sql.gz');
    }
    $binary = $script('keep', "echo 'SELECT 1;'\necho ".escapeshellarg(MARKER));
    $backup = (new DatabaseBackup($binary, null, $credentials))->run($dir, 2, new DateTimeImmutable('2026-10-20 11:00:00'));

    return count($backup->removed) === 2
        && $entries($dir) === ['20260103-000000.sql.gz', '20261020-110000.sql.gz'];
});

/** mysqli finto: tabelle, viste e righe in memoria. */
$fakeMysqli = static fn (array $tables, array $failOn = [], ?string $version = null) => new class($tables, $failOn, $version) extends mysqli {
    public array $queries = [];

    public function __construct(private array $tables, private array $failOn, private ?string $version) {}

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
            $query === 'SELECT VERSION()' && $this->version !== null => [[$this->version]],
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

/** Script mysqldump che fallisce: exit 2 con output parziale. */
$failingScript = static fn (string $name) => $script($name, implode("\n", [
    'echo "${1#--defaults-extra-file=}" > '.escapeshellarg($base.'/'.$name.'-path'),
    'echo "CREATE TABLE parziale"',
    'echo "mysqldump: Got error: 1045: Access denied" >&2',
    'exit 2',
]));

check('mysqldump KO, dump PHP OK: file presente, metodo php, errore di mysqldump in fallback', function () use ($base, $credentials, $failingScript, $fakeMysqli, $tables, $entries) {
    $binary = $failingScript('fallback');
    $dir = $base.'/fallback';
    $backup = (new DatabaseBackup($binary, $fakeMysqli($tables), $credentials))->run($dir, 5, new DateTimeImmutable('2026-10-20 13:00:00'));
    $dump = (string) gzdecode((string) file_get_contents($backup->path));

    return $backup->method === 'php'
        && str_contains((string) $backup->fallback, 'exit 2')
        && str_contains((string) $backup->fallback, 'Access denied')
        && str_contains($dump, 'CREATE TABLE `products`')
        && !str_contains($dump, 'parziale')
        && $entries($dir) === ['20261020-130000.sql.gz']
        && !file_exists(trim((string) file_get_contents($base.'/fallback-path')));
});

check('mysqldump KO e dump PHP KO: eccezione con entrambi gli errori, nessun file, credenziali rimosse', function () use ($base, $credentials, $failingScript, $fakeMysqli, $tables, $entries) {
    $binary = $failingScript('fail');
    $dir = $base.'/fail';

    try {
        (new DatabaseBackup($binary, $fakeMysqli($tables, ['SHOW FULL TABLES']), $credentials))->run($dir);
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'né con mysqldump né con il dump PHP')
            && str_contains($e->getMessage(), 'exit 2')
            && str_contains($e->getMessage(), 'Access denied')
            && str_contains($e->getMessage(), 'SHOW FULL TABLES')
            && $entries($dir) === []
            && !file_exists(trim((string) file_get_contents($base.'/fail-path')));
    }

    return false;
});

check('mysqldump exit 0 con stderr non vuoto: trattato come errore (ripiego, poi nessun file)', function () use ($base, $credentials, $script, $fakeMysqli, $tables, $entries) {
    $binary = $script('warn', "echo 'CREATE TABLE t (id int);'\necho ".escapeshellarg(MARKER)."\necho 'mysqldump: Error 2013: Lost connection' >&2\nexit 0");
    $dir = $base.'/warn';

    try {
        (new DatabaseBackup($binary, $fakeMysqli($tables, ['SHOW FULL TABLES']), $credentials))->run($dir);
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'Lost connection') && $entries($dir) === [];
    }

    return false;
});

check('mysqldump senza output: trattato come errore (ripiego, poi nessun file)', function () use ($base, $credentials, $script, $fakeMysqli, $tables, $entries) {
    $binary = $script('empty', 'exit 0');
    $dir = $base.'/empty';

    try {
        (new DatabaseBackup($binary, $fakeMysqli($tables, ['SHOW FULL TABLES']), $credentials))->run($dir);
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'alcun output') && $entries($dir) === [];
    }

    return false;
});

check('mysqldump exit 0 senza «-- Dump completed» (troncato): si ripiega sul dump PHP', function () use ($base, $credentials, $script, $fakeMysqli, $tables, $entries) {
    $binary = $script('truncated', "echo 'CREATE TABLE t (id int);'\necho 'INSERT INTO t VALUES (1'");
    $dir = $base.'/truncated';
    $backup = (new DatabaseBackup($binary, $fakeMysqli($tables), $credentials))->run($dir, 5, new DateTimeImmutable('2026-10-20 14:00:00'));
    $dump = (string) gzdecode((string) file_get_contents($backup->path));

    return $backup->method === 'php'
        && str_contains((string) $backup->fallback, 'troncato')
        && !str_contains($dump, 'INSERT INTO t VALUES (1')
        && $entries($dir) === ['20261020-140000.sql.gz'];
});

check('mysqldump OK con skip-comments nei file di opzioni: niente marcatore, nessun ripiego, file presente', function () use ($base, $credentials, $script, $fakeMysqli, $tables, $entries) {
    $binary = $script('skip-comments', "echo 'CREATE TABLE t (id int);'\necho 'INSERT INTO t VALUES (1);'", MYSQL_VERSION, '--user=shop --password=***** --skip-comments');
    $dir = $base.'/skip-comments';
    $connection = $fakeMysqli($tables);
    $backup = (new DatabaseBackup($binary, $connection, $credentials))->run($dir, 5, new DateTimeImmutable('2026-10-20 15:00:00'));
    $dump = (string) gzdecode((string) file_get_contents($backup->path));

    return $backup->method === 'mysqldump'
        && $backup->fallback === null
        && $dump === "CREATE TABLE t (id int);\nINSERT INTO t VALUES (1);\n"
        && $entries($dir) === ['20261020-150000.sql.gz']
        && !in_array(['SHOW FULL TABLES', MYSQLI_STORE_RESULT], $connection->queries, true);
});

check('skip-comments non spegne gli altri controlli: exit ≠ 0 ripiega comunque', function () use ($base, $credentials, $script, $fakeMysqli, $tables) {
    $binary = $script('skip-comments-ko', "echo 'CREATE TABLE parziale'\nexit 3", MYSQL_VERSION, '--skip-comments');
    $backup = (new DatabaseBackup($binary, $fakeMysqli($tables), $credentials))->run($base.'/skip-comments-ko');

    return $backup->method === 'php' && str_contains((string) $backup->fallback, 'exit 3');
});

check('mysqldump di MariaDB: --set-gtid-purged assente negli argomenti passati', function () use ($base, $credentials, $script) {
    $log = $base.'/maria-args';
    $binary = $script('maria', 'printf "%s\n" "$@" > '.escapeshellarg($log)."\necho 'SELECT 1;'\necho ".escapeshellarg(MARKER), MARIADB_VERSION);
    $backup = (new DatabaseBackup($binary, null, $credentials))->run($base.'/maria');
    $args = (string) file_get_contents($log);

    return $backup->method === 'mysqldump'
        && !str_contains($args, 'gtid')
        && str_contains($args, '--single-transaction')
        && str_contains($args, 'shop_db');
});

check('server MariaDB (VERSION()) con client MySQL: --set-gtid-purged assente', function () use ($base, $credentials, $script, $fakeMysqli) {
    $log = $base.'/maria-server-args';
    $binary = $script('maria-server', 'printf "%s\n" "$@" > '.escapeshellarg($log)."\necho 'SELECT 1;'\necho ".escapeshellarg(MARKER));
    $connection = $fakeMysqli([], [], '10.11.6-MariaDB-0+deb12u1-log');
    $backup = (new DatabaseBackup($binary, $connection, $credentials))->run($base.'/maria-server');

    return $backup->method === 'mysqldump'
        && !str_contains((string) file_get_contents($log), 'gtid')
        && in_array(['SELECT VERSION()', MYSQLI_STORE_RESULT], $connection->queries, true);
});

check('server MySQL (VERSION()) con client MySQL: --set-gtid-purged presente', function () use ($base, $credentials, $script, $fakeMysqli) {
    $log = $base.'/mysql-server-args';
    $binary = $script('mysql-server', 'printf "%s\n" "$@" > '.escapeshellarg($log)."\necho 'SELECT 1;'\necho ".escapeshellarg(MARKER));
    (new DatabaseBackup($binary, $fakeMysqli([], [], '8.0.36'), $credentials))->run($base.'/mysql-server');

    return str_contains((string) file_get_contents($log), '--set-gtid-purged=OFF');
});

/**
 * `forge db:backup` via CommandTester: cwd temporanea con un bootstrap vuoto,
 * DatabaseBackup iniettato sovrascrivendo makeBackup().
 */
$runCommand = static function (DatabaseBackup $backup, string $dir) use ($base): array {
    $root = $base.'/cmd-root-'.bin2hex(random_bytes(3));
    mkdir($root.'/vendor/wonder-image/app', 0777, true);
    file_put_contents($root.'/vendor/wonder-image/app/wonder-image.php', "<?php\n");

    $command = new class($backup) extends Wonder\Console\Commands\DbBackup {
        public function __construct(private DatabaseBackup $injected) { parent::__construct(); }
        protected function makeBackup(): DatabaseBackup { return $this->injected; }
    };
    $tester = new Symfony\Component\Console\Tester\CommandTester($command);
    $previous = getcwd();
    chdir($root);

    try {
        $code = $tester->execute(['--path' => $dir]);
    } finally {
        chdir((string) $previous);
    }

    return [$code, $tester->getDisplay()];
};

check('forge db:backup: mysqldump KO → dump PHP OK → exit 0, file presente, avviso', function () use ($base, $credentials, $failingScript, $fakeMysqli, $tables, $entries, $runCommand) {
    $dir = $base.'/cmd-fallback';
    [$code, $display] = $runCommand(new DatabaseBackup($failingScript('cmd-fallback'), $fakeMysqli($tables), $credentials), $dir);
    $files = $entries($dir);

    return $code === 0
        && count($files) === 1 && preg_match('/^\d{8}-\d{6}\.sql\.gz$/', $files[0]) === 1
        && str_contains($display, 'mysqldump non riuscito, usato il dump PHP')
        && str_contains($display, 'Backup creato');
});

check('forge db:backup: entrambi KO → exit ≠ 0 e nessun file', function () use ($base, $credentials, $failingScript, $fakeMysqli, $tables, $entries, $runCommand) {
    $dir = $base.'/cmd-fail';
    [$code, $display] = $runCommand(new DatabaseBackup($failingScript('cmd-fail'), $fakeMysqli($tables, ['SHOW FULL TABLES']), $credentials), $dir);

    return $code !== 0
        && (!is_dir($dir) || $entries($dir) === [])
        && str_contains($display, 'Backup non riuscito');
});

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

check('API update: backup=false (bool, "false", "0", 0, "no", "off") disattiva il backup', function () {
    foreach ([false, 'false', 'FALSE', '0', 0, 'no', 'off', ' Off '] as $value) {
        if (Wonder\App\UpdateRunner::apiOptions(['backup' => $value])['backup'] !== false) {
            return false;
        }
    }
    return true;
});

check('API update: backup assente, vuoto, true, "1", "true" o non scalare = backup attivo', function () {
    foreach ([[], ['backup' => null], ['backup' => ''], ['backup' => true], ['backup' => '1'], ['backup' => 'true'], ['backup' => 1], ['backup' => []]] as $parameters) {
        if (Wonder\App\UpdateRunner::apiOptions($parameters)['backup'] !== true) {
            return false;
        }
    }
    return true;
});

check('API update: opzioni normalizzate come prima (release_id, trigger_type, source)', fn () =>
    Wonder\App\UpdateRunner::apiOptions(['release_id' => ' r42 ', 'source' => 'altro'])
        === ['release_id' => 'r42', 'trigger_type' => 'api', 'source' => 'github', 'backup' => true]
    && Wonder\App\UpdateRunner::apiOptions(['source' => 'backend'])['source'] === 'backend');

check('update con backup=false: nessun backup e nessuna query al database', function () {
    $connection = new class extends mysqli {
        public array $queries = [];
        public function __construct() {}

        #[\ReturnTypeWillChange]
        public function query($query, $resultMode = MYSQLI_STORE_RESULT)
        {
            $this->queries[] = $query;
            return new class([['app_update_runs'], ['user']]) {
                public function __construct(private array $rows) {}
                public function fetch_row(): ?array { return array_shift($this->rows); }
                public function free(): void {}
            };
        }
    };
    $runner = new Wonder\App\UpdateRunner($connection);
    $should = static fn (array $options): bool => (new ReflectionMethod($runner, 'shouldBackup'))->invoke($runner, $options);

    $skipped = $should(Wonder\App\UpdateRunner::apiOptions(['backup' => 'false']));
    $queriesWhenSkipped = count($connection->queries);

    return $skipped === false
        && $queriesWhenSkipped === 0
        && $should(Wonder\App\UpdateRunner::apiOptions([])) === true
        && $should([]) === true;
});

/** UpdateRunner con DatabaseBackup iniettato: runBackup() scrive nel risultato, in ROOT/storage/backup. */
$runBackup = static function (DatabaseBackup $backup) use ($base): object {
    $GLOBALS['ROOT'] = $base.'/update-root-'.bin2hex(random_bytes(3));
    $runner = new class($backup) extends Wonder\App\UpdateRunner {
        public function __construct(private DatabaseBackup $injected) {}
        protected function makeBackup(): DatabaseBackup { return $this->injected; }
    };
    $result = (object) [];
    (new ReflectionMethod(Wonder\App\UpdateRunner::class, 'runBackup'))->invoke($runner, $result);

    return $result;
};

check('update: con il ripiego sul dump PHP l\'avviso compare nel risultato (backup_warning)', function () use ($base, $credentials, $failingScript, $fakeMysqli, $tables, $runBackup) {
    $result = $runBackup(new DatabaseBackup($failingScript('update-fallback'), $fakeMysqli($tables), $credentials));
    $json = (new Wonder\App\UpdateRunner(new class extends mysqli { public function __construct() {} }))->jsonPayload($result);

    return is_file($result->backup)
        && str_starts_with($result->backup, $GLOBALS['ROOT'].'/storage/backup/')
        && str_starts_with((string) $result->backup_warning, 'mysqldump non riuscito, usato il dump PHP: ')
        && str_contains($result->backup_warning, 'Access denied')
        && str_contains($json, '"backup_warning"');
});

check('update: mysqldump riuscito, nessun backup_warning', function () use ($base, $credentials, $script, $runBackup) {
    $result = $runBackup(new DatabaseBackup($script('update-ok', "echo 'SELECT 1;'\necho ".escapeshellarg(MARKER)), null, $credentials));

    return is_file($result->backup) && !property_exists($result, 'backup_warning');
});

check('endpoint /api/app/update/: entrambi i percorsi passano da UpdateRunner::apiOptions()', function () {
    $source = (string) file_get_contents(__DIR__.'/../../app/http/api/app/update.php');

    return substr_count($source, 'UpdateRunner::apiOptions($payload)') === 1
        && substr_count($source, 'UpdateRunner::apiOptions($PARAMETERS)') === 1
        && substr_count($source, '->execute(') === 2;
});

summary();
