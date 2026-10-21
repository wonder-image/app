<?php

namespace Wonder\App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use mysqli;
use RuntimeException;
use Throwable;
use Wonder\App\Credentials;
use Wonder\App\LegacyGlobals;
use Wonder\Sql\Connection;

/**
 * Dump gzip del database `main` in `storage/backup/<Ymd-His>-<8 hex>.sql.gz`.
 * Data e ora del nome sono in UTC; il suffisso casuale rende il nome non indovinabile.
 * La cartella riceve un `.htaccess` con `Require all denied` e un `index.html` vuoto.
 *
 * Usa `mysqldump` se presente nel PATH e `proc_open` è disponibile (credenziali in un
 * `--defaults-extra-file` temporaneo 0600, mai negli argomenti),
 * altrimenti un dump PHP via mysqli (solo tabelle e dati).
 * Se mysqldump fallisce (exit ≠ 0, stderr, output vuoto o, con i commenti attivi,
 * senza il marcatore finale `-- Dump completed`) si riprova con il dump PHP;
 * l'errore arriva solo se falliscono entrambi. Il file finale compare solo a dump riuscito: si scrive su
 * un temporaneo nella stessa cartella e si rinomina alla fine.
 */
final class DatabaseBackup
{
    public const DEFAULT_KEEP = 5;

    public const MYSQLDUMP_OPTIONS = [
        '--single-transaction',
        '--skip-lock-tables',
        '--no-tablespaces',
        '--set-gtid-purged=OFF',
        '--routines',
        '--triggers',
    ];

    /** Ultima riga scritta da mysqldump (con i commenti attivi, default): senza, il dump è troncato. */
    public const DUMP_COMPLETED_MARKER = '-- Dump completed';

    /** Opzione che il mysqldump di MariaDB non conosce (e che i server MariaDB non usano). */
    private const GTID_OPTION = '--set-gtid-purged=OFF';

    /** Riconosce anche il vecchio nome senza suffisso, così la rotazione lo smaltisce. */
    private const FILE_PATTERN = '/^(\d{8}-\d{6})(?:-[0-9a-f]{8})?\.sql\.gz$/';

    private const HTACCESS = "Require all denied\n";

    private const INSERT_BATCH_ROWS = 100;

    private const INSERT_BATCH_BYTES = 1048576;

    /**
     * @param string|false|null $binary  percorso di mysqldump; null = cerca nel PATH, false = solo dump PHP
     * @param object|null $credentials   stessa forma di `Credentials::database()`
     */
    public function __construct(
        private string|false|null $binary = null,
        private ?mysqli $connection = null,
        private ?object $credentials = null,
    ) {
    }

    public static function defaultDirectory(?string $root = null): string
    {
        if ($root === null || $root === '') {
            $root = defined('ROOT') ? (string) ROOT : (string) (LegacyGlobals::get('ROOT', '') ?: (getcwd() ?: '.'));
        }

        return rtrim($root, '/').'/storage/backup';
    }

    /** `Ymd-His` in UTC più 8 hex casuali; `$suffix` serve ai test. */
    public static function fileName(?DateTimeInterface $at = null, ?string $suffix = null): string
    {
        $suffix ??= bin2hex(random_bytes(4));

        if (preg_match('/^[0-9a-f]{8}$/', $suffix) !== 1) {
            throw new InvalidArgumentException('Il suffisso del backup deve essere di 8 caratteri esadecimali.');
        }

        $at = DateTimeImmutable::createFromInterface($at ?? new DateTimeImmutable())->setTimezone(new DateTimeZone('UTC'));

        return $at->format('Ymd-His').'-'.$suffix.'.sql.gz';
    }

    public static function findMysqldump(?string $path = null): ?string
    {
        $path ??= (string) getenv('PATH');
        $name = DIRECTORY_SEPARATOR === '\\' ? 'mysqldump.exe' : 'mysqldump';

        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            $dir = trim($dir);

            if ($dir === '') {
                continue;
            }

            $candidate = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$name;

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Argomenti di mysqldump: niente password, `--defaults-extra-file` deve essere il primo.
     * Con MariaDB (client o server) `--set-gtid-purged=OFF` viene omesso.
     *
     * @return list<string>
     */
    public static function mysqldumpArguments(string $binary, string $defaultsFile, string $database, bool $mariaDb = false): array
    {
        $options = $mariaDb
            ? array_values(array_filter(self::MYSQLDUMP_OPTIONS, static fn (string $option): bool => $option !== self::GTID_OPTION))
            : self::MYSQLDUMP_OPTIONS;

        return [
            $binary,
            '--defaults-extra-file='.$defaultsFile,
            ...$options,
            $database,
        ];
    }

    /**
     * Opzioni effettive che spengono i commenti di mysqldump, e con loro il marcatore finale:
     * `--skip-comments`, `--comments=0|off|false`, `--compact` (anche con `_` o `loose-`, come nei
     * file di opzioni). Vale l'ultima che compare.
     *
     * @param list<string> $arguments
     */
    public static function commentsDisabled(array $arguments): bool
    {
        $disabled = false;

        foreach ($arguments as $argument) {
            if (!str_starts_with($argument, '--')) {
                continue;
            }

            [$name, $value] = array_pad(explode('=', substr($argument, 2), 2), 2, null);
            $name = str_replace('_', '-', strtolower($name));
            $name = str_starts_with($name, 'loose-') ? substr($name, 6) : $name;
            $off = $value !== null && in_array(strtolower(trim($value)), ['0', 'off', 'false'], true);

            if ($name === 'skip-comments' || $name === 'disable-comments') {
                $disabled = true;
            } elseif ($name === 'comments' || $name === 'enable-comments') {
                $disabled = $off;
            } elseif ($name === 'compact' && !$off) {
                $disabled = true;
            }
        }

        return $disabled;
    }

    /** Versione di `mysqldump --version` o di `SELECT VERSION()` che indica MariaDB. */
    public static function isMariaDb(string $version): bool
    {
        return stripos($version, 'mariadb') !== false;
    }

    public static function defaultsFileContent(object $credentials): string
    {
        [$host, $port] = Connection::parseHostAndPort((string) ($credentials->hostname ?? ''));

        $lines = ['[client]'];
        $lines[] = 'user='.self::optionValue((string) ($credentials->username ?? ''));
        $lines[] = 'password='.self::optionValue((string) ($credentials->password ?? ''));

        if ($host !== '') {
            $lines[] = 'host='.self::optionValue($host);
            $lines[] = 'port='.$port;
        }

        $charset = trim((string) ($credentials->charset ?? ''));

        if ($charset !== '') {
            $lines[] = 'default-character-set='.self::optionValue($charset);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Mantiene gli ultimi `$keep` backup (`Ymd-His-<hex>.sql.gz`), in ordine di data del nome
     * (non di mtime); gli altri file non vengono toccati.
     *
     * @return list<string> file rimossi
     */
    public static function rotate(string $directory, int $keep = self::DEFAULT_KEEP): array
    {
        if ($keep < 1) {
            throw new InvalidArgumentException('Il numero di backup da mantenere deve essere almeno 1.');
        }

        $files = [];

        foreach (scandir($directory) ?: [] as $entry) {
            if (preg_match(self::FILE_PATTERN, $entry, $match) === 1 && is_file($directory.'/'.$entry)) {
                $files[$entry] = $match[1];
            }
        }

        uksort($files, static fn (string $a, string $b): int => [$files[$a], $a] <=> [$files[$b], $b]);

        $removed = [];

        foreach (array_slice(array_keys($files), 0, max(0, count($files) - $keep)) as $entry) {
            if (@unlink($directory.'/'.$entry)) {
                $removed[] = $directory.'/'.$entry;
            }
        }

        return $removed;
    }

    /**
     * `fallback` è l'errore di mysqldump quando il backup è stato fatto con il dump PHP al suo posto.
     *
     * @return object{path: string, method: string, bytes: int, removed: list<string>, fallback: ?string}
     */
    public function run(?string $directory = null, int $keep = self::DEFAULT_KEEP, ?DateTimeInterface $at = null): object
    {
        if ($keep < 1) {
            throw new InvalidArgumentException('Il numero di backup da mantenere deve essere almeno 1.');
        }

        $directory = rtrim($directory ?? self::defaultDirectory(), '/');
        $this->ensureDirectory($directory);

        $path = $directory.'/'.self::fileName($at);

        if (file_exists($path)) {
            throw new RuntimeException('Esiste già un backup con lo stesso nome: '.$path);
        }

        $temporary = $directory.'/.'.basename($path).'.'.bin2hex(random_bytes(4)).'.tmp';
        $binary = $this->binary === null ? self::findMysqldump() : $this->binary;
        // proc_open può essere disabilitata sugli hosting condivisi: si ripiega sul dump PHP.
        $method = is_string($binary) && $binary !== '' && function_exists('proc_open') ? 'mysqldump' : 'php';
        $fallback = null;

        try {
            if ($method === 'mysqldump') {
                try {
                    $this->dumpWithMysqldump($binary, $temporary);
                } catch (Throwable $throwable) {
                    @unlink($temporary);
                    $fallback = $throwable->getMessage();
                    $method = 'php';
                }
            }

            if ($method === 'php') {
                try {
                    $this->dumpWithPhp($temporary);
                } catch (Throwable $throwable) {
                    if ($fallback === null) {
                        throw $throwable;
                    }

                    throw new RuntimeException('Backup non riuscito né con mysqldump né con il dump PHP. mysqldump: '.$fallback.' — dump PHP: '.$throwable->getMessage(), 0, $throwable);
                }
            }

            @chmod($temporary, 0640);

            if (!rename($temporary, $path)) {
                throw new RuntimeException('Impossibile rinominare il backup in '.$path);
            }
        } catch (Throwable $throwable) {
            @unlink($temporary);
            throw $throwable;
        }

        return (object) [
            'path' => $path,
            'method' => $method,
            'bytes' => (int) filesize($path),
            'removed' => self::rotate($directory, $keep),
            'fallback' => $fallback,
        ];
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Impossibile creare la cartella dei backup: '.$directory);
            }

            @chmod($directory, 0750);
        }

        // La cartella sta sotto la document root: Apache non deve servire i dump.
        foreach (['.htaccess' => self::HTACCESS, 'index.html' => ''] as $name => $content) {
            if (!file_exists($directory.'/'.$name) && @file_put_contents($directory.'/'.$name, $content) === false) {
                throw new RuntimeException('Impossibile proteggere la cartella dei backup: '.$directory.'/'.$name);
            }
        }
    }

    private function dumpWithMysqldump(string $binary, string $temporary): void
    {
        $credentials = $this->credentials ?? Credentials::database();
        $database = (string) ($credentials->database['main'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Nome del database mancante (DB_DATABASE).');
        }

        $defaultsFile = null;
        $stderrFile = null;
        $gz = false;

        try {
            $defaultsFile = $this->privateTempFile('wi-mysqldump-');

            if (file_put_contents($defaultsFile, self::defaultsFileContent($credentials)) === false) {
                throw new RuntimeException('Impossibile scrivere il file temporaneo delle credenziali.');
            }

            $stderrFile = $this->privateTempFile('wi-mysqldump-err-');
            $mariaDb = self::isMariaDb($this->capture([$binary, '--version'])) || self::isMariaDb($this->serverVersion());
            $arguments = self::mysqldumpArguments($binary, $defaultsFile, $database, $mariaDb);
            // Il marcatore c'è solo con i commenti attivi: si guardano anche i file di opzioni (my.cnf).
            $checkMarker = !self::commentsDisabled([...$this->optionFileArguments($binary, $defaultsFile), ...$arguments]);

            $process = proc_open(
                $arguments,
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderrFile, 'w']],
                $pipes
            );

            if (!is_resource($process)) {
                throw new RuntimeException('Impossibile avviare mysqldump ('.$binary.').');
            }

            fclose($pipes[0]);

            $gz = gzopen($temporary, 'wb6');
            $writeError = false;
            $bytes = 0;
            $tail = '';

            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 65536);

                if ($chunk === false) {
                    break;
                }

                if ($chunk === '' || $writeError) {
                    continue;
                }

                if ($gz === false || gzwrite($gz, $chunk) !== strlen($chunk)) {
                    // Si continua a svuotare la pipe per non bloccare mysqldump.
                    $writeError = true;
                    continue;
                }

                $bytes += strlen($chunk);
                $tail = substr($tail.$chunk, -512);
            }

            fclose($pipes[1]);
            $exitCode = proc_close($process);
            $stderr = trim((string) @file_get_contents($stderrFile));

            $closed = $gz !== false && gzclose($gz);
            $gz = false;

            if ($exitCode !== 0 || $stderr !== '') {
                throw new RuntimeException('mysqldump non riuscito (exit '.$exitCode.')'.($stderr !== '' ? ': '.$stderr : '.'));
            }

            if ($writeError || !$closed) {
                throw new RuntimeException('Scrittura del backup non riuscita: '.$temporary);
            }

            if ($bytes === 0) {
                throw new RuntimeException('mysqldump non ha prodotto alcun output.');
            }

            if ($checkMarker && !str_contains($tail, self::DUMP_COMPLETED_MARKER)) {
                throw new RuntimeException('Output di mysqldump troncato (manca «'.self::DUMP_COMPLETED_MARKER.'»).');
            }
        } finally {
            if ($gz !== false) {
                @gzclose($gz);
            }

            foreach ([$defaultsFile, $stderrFile] as $file) {
                if (is_string($file) && file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /** Stdout di un comando breve (versione, opzioni); stringa vuota se non parte. */
    private function capture(array $command): string
    {
        $process = @proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return '';
        }

        fclose($pipes[0]);
        $output = (string) stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $output;
    }

    /**
     * Opzioni lette dai file di opzioni (`--print-defaults`, ammesso subito dopo
     * `--defaults-extra-file`). L'output può contenere la password: resta in memoria.
     *
     * @return list<string>
     */
    private function optionFileArguments(string $binary, string $defaultsFile): array
    {
        $output = $this->capture([$binary, '--defaults-extra-file='.$defaultsFile, '--print-defaults']);
        $tokens = preg_split('/\s+/', $output, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($tokens, static fn (string $token): bool => str_starts_with($token, '--')));
    }

    /**
     * `SELECT VERSION()` sulla connessione iniettata; senza connessione e con le credenziali
     * del sito ne apre una. Con credenziali esplicite (test, chiamanti esterni) non si connette.
     */
    private function serverVersion(): string
    {
        try {
            $connection = $this->connection ?? ($this->credentials === null ? Connection::Connect() : null);

            if (!$connection instanceof mysqli) {
                return '';
            }

            $result = $connection->query('SELECT VERSION()');

            if (!is_object($result)) {
                return '';
            }

            $row = $result->fetch_row();
            $result->free();

            return is_array($row) ? (string) ($row[0] ?? '') : '';
        } catch (Throwable) {
            return '';
        }
    }

    private function dumpWithPhp(string $temporary): void
    {
        $connection = $this->connection ?? Connection::Connect();
        $gz = gzopen($temporary, 'wb6');

        if ($gz === false) {
            throw new RuntimeException('Impossibile scrivere il backup: '.$temporary);
        }

        try {
            $charset = $this->connectionCharset($connection);

            $this->write($gz, "-- Wonder Image db:backup (dump PHP) ".gmdate('Y-m-d H:i:s')." UTC\n");
            $this->write($gz, ($charset !== '' ? "SET NAMES {$charset};\n" : '')."SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            foreach ($this->baseTables($connection) as $table) {
                $this->dumpTable($connection, $gz, $table);
            }

            $this->write($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (Throwable $throwable) {
            @gzclose($gz);
            throw $throwable;
        }

        if (!gzclose($gz)) {
            throw new RuntimeException('Chiusura del backup non riuscita: '.$temporary);
        }
    }

    /** @return list<string> */
    private function baseTables(mysqli $connection): array
    {
        $result = $this->query($connection, 'SHOW FULL TABLES');
        $tables = [];

        while (($row = $result->fetch_row()) !== null && $row !== false) {
            if (strtoupper((string) ($row[1] ?? 'BASE TABLE')) === 'BASE TABLE') {
                $tables[] = (string) $row[0];
            }
        }

        $result->free();

        return $tables;
    }

    private function dumpTable(mysqli $connection, $gz, string $table): void
    {
        $identifier = '`'.str_replace('`', '``', $table).'`';

        $result = $this->query($connection, 'SHOW CREATE TABLE '.$identifier);
        $row = $result->fetch_row();
        $result->free();

        if (!is_array($row) || !isset($row[1])) {
            throw new RuntimeException('SHOW CREATE TABLE senza risultato per '.$table);
        }

        $this->write($gz, "DROP TABLE IF EXISTS {$identifier};\n".$row[1].";\n\n");

        $result = $this->query($connection, 'SELECT * FROM '.$identifier, MYSQLI_USE_RESULT);
        $values = [];
        $size = 0;

        try {
            while (($row = $result->fetch_row()) !== null && $row !== false) {
                $tuple = '('.implode(',', array_map(
                    static fn ($value): string => $value === null ? 'NULL' : "'".$connection->real_escape_string((string) $value)."'",
                    $row
                )).')';

                $values[] = $tuple;
                $size += strlen($tuple);

                if (count($values) >= self::INSERT_BATCH_ROWS || $size >= self::INSERT_BATCH_BYTES) {
                    $this->write($gz, "INSERT INTO {$identifier} VALUES\n".implode(",\n", $values).";\n");
                    $values = [];
                    $size = 0;
                }
            }
        } finally {
            $result->free();
        }

        if ($values !== []) {
            $this->write($gz, "INSERT INTO {$identifier} VALUES\n".implode(",\n", $values).";\n");
        }

        $this->write($gz, "\n");
    }

    private function query(mysqli $connection, string $sql, int $mode = MYSQLI_STORE_RESULT): object
    {
        $result = $connection->query($sql, $mode);

        if (!is_object($result)) {
            $error = '';

            try {
                $error = (string) $connection->error;
            } catch (Throwable) {
            }

            throw new RuntimeException('Query non riuscita ('.$sql.')'.($error !== '' ? ': '.$error : '.'));
        }

        return $result;
    }

    private function connectionCharset(mysqli $connection): string
    {
        try {
            $charset = (string) $connection->character_set_name();
        } catch (Throwable) {
            return '';
        }

        return preg_match('/^[A-Za-z0-9_]+$/', $charset) === 1 ? $charset : '';
    }

    private function write($gz, string $data): void
    {
        if (gzwrite($gz, $data) !== strlen($data)) {
            throw new RuntimeException('Scrittura del backup non riuscita.');
        }
    }

    private function privateTempFile(string $prefix): string
    {
        $file = tempnam(sys_get_temp_dir(), $prefix);

        if ($file === false) {
            throw new RuntimeException('Impossibile creare un file temporaneo.');
        }

        @chmod($file, 0600);

        return $file;
    }

    private static function optionValue(string $value): string
    {
        return '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']).'"';
    }
}
