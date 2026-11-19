<?php

namespace Wonder\App\Migrations;

use mysqli;
use RuntimeException;
use Throwable;
use Wonder\App\Environment;
use Wonder\App\Module\ModuleDependencySorter;
use Wonder\App\Module\Registry as ModuleRegistry;
use Wonder\Sql\Migration;

/**
 * Migrazioni versionate: un file PHP per migrazione, registro `app_migrations`.
 *
 * Solo in avanti (nessun `down()`). Ordine: `app`, moduli in ordine di
 * dipendenza, `site`; dentro una sorgente, per nome di file.
 */
class Migrator
{
    public const TABLE = 'app_migrations';
    public const LOCK = 'wonder_migrations';

    /** @var array<int, array{source: string, dir: string}> */
    private array $sources;

    /** @param array<int, array{source: string, dir: string}>|null $sources */
    public function __construct(private mysqli $connection, ?array $sources = null)
    {
        $this->sources = $sources ?? self::runtimeSources();
    }

    /** app (nel pacchetto), moduli attivi, sito. */
    public static function runtimeSources(): array
    {
        $sources = [['source' => 'app', 'dir' => dirname(__DIR__, 3).'/database/migrations']];

        foreach (ModuleDependencySorter::sortManifests(ModuleRegistry::enabled()) as $manifest) {
            $dir = $manifest->resolvePath($manifest->get('database.migrations_dir'));

            if ($dir !== null) {
                $sources[] = ['source' => $manifest->slug(), 'dir' => $dir];
            }
        }

        $root = (string) ($GLOBALS['ROOT'] ?? getcwd());
        $sources[] = ['source' => 'site', 'dir' => rtrim($root, '/').'/database/migrations'];

        return $sources;
    }

    /**
     * File presenti sul disco, nell'ordine di esecuzione.
     *
     * @return array<int, array{id: string, source: string, path: string, checksum: string}>
     */
    public function files(): array
    {
        $files = [];
        $seen = [];

        foreach ($this->sources as $source) {
            $paths = is_dir($source['dir']) ? (glob(rtrim($source['dir'], '/').'/*.php') ?: []) : [];
            sort($paths, SORT_STRING);

            foreach ($paths as $path) {
                $id = basename($path, '.php');

                if (isset($seen[$id])) {
                    throw new RuntimeException("Migrazione duplicata: {$id} ({$seen[$id]} e {$source['source']}).");
                }

                $seen[$id] = $source['source'];
                $files[] = [
                    'id' => $id,
                    'source' => $source['source'],
                    'path' => $path,
                    'checksum' => hash_file('sha256', $path),
                ];
            }
        }

        return $files;
    }

    /**
     * Migrazioni in attesa. Non scrive nulla. Se il registro non esiste ancora,
     * quelle di `app` valgono come già applicate (§ primo avvio).
     *
     * @return array<int, array{id: string, source: string, destructive: bool, path: string}>
     */
    public function pending(?mysqli $connection = null): array
    {
        $connection ??= $this->connection;
        $applied = $this->tableExists($connection) ? $this->applied($connection) : null;
        $pending = [];

        foreach ($this->files() as $file) {
            if ($applied === null ? $file['source'] === 'app' : isset($applied[$file['id']])) {
                continue;
            }

            $pending[] = [
                'id' => $file['id'],
                'source' => $file['source'],
                'destructive' => $this->load($file['path'])['destructive'],
                'path' => $file['path'],
            ];
        }

        return $pending;
    }

    /**
     * Opzioni: `pretend` (nessuna scrittura, elenca l'SQL), `force` (ammette le
     * distruttive fuori da local), `run_id`, `with_schema` (poi `runTables()`),
     * `schema_runner` (callable che sostituisce `UpdateRunner::runSchema`).
     */
    public function runPending(array $options = []): MigrationRunResult
    {
        $result = new MigrationRunResult();
        $pretend = (bool) ($options['pretend'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $runId = isset($options['run_id']) ? (int) $options['run_id'] : null;

        if (!$this->acquireLock()) {
            $result->status = 'busy';
            $result->error = 'Migrazioni già in esecuzione.';
            return $result;
        }

        try {
            $firstRun = !$this->tableExists($this->connection);

            if (!$pretend) {
                $this->baselineIfNew();
            }

            $applied = $this->tableExists($this->connection) ? $this->applied($this->connection) : [];
            $nonLocal = $this->environment() !== Environment::LOCAL;
            $blockedSources = [];
            $result->batch = $pretend || !$this->tableExists($this->connection) ? 0 : max(1, $this->nextBatch());

            foreach ($this->files() as $file) {
                if (isset($applied[$file['id']]) || ($firstRun && $file['source'] === 'app')) {
                    continue;
                }

                $meta = $this->load($file['path']);
                $entry = ['id' => $file['id'], 'source' => $file['source'], 'destructive' => $meta['destructive']];

                if (isset($blockedSources[$file['source']])) {
                    $result->pending[] = $entry;
                    continue;
                }

                if ($meta['destructive'] && $nonLocal && !$force) {
                    $result->skipped[] = $entry;
                    $blockedSources[$file['source']] = true;
                    continue;
                }

                $started = hrtime(true);
                $db = new Migration($this->connection, $meta['destructive'], $pretend);

                try {
                    $meta['instance']->up($db);
                } catch (Throwable $throwable) {
                    $result->status = 'failed';
                    $result->error = $file['id'].': '.$throwable->getMessage();
                    $result->pending[] = $entry;
                    $blockedSources = array_fill_keys(array_column($this->sources, 'source'), true);
                    continue;
                }

                $entry['statements'] = $db->statements();
                $result->applied[] = $entry;

                if (!$pretend) {
                    $this->record($file, $meta['destructive'], $result->batch, (int) ((hrtime(true) - $started) / 1_000_000), $runId);
                }
            }

            if ($result->status === 'failed') {
                return $result;
            }

            if (!empty($options['with_schema']) && !$pretend) {
                $runner = $options['schema_runner'] ?? static fn () => (new \Wonder\App\UpdateRunner())->runSchema();
                $result->schema = $runner();
            }

            $result->success = true;
            $result->status = 'done';

            return $result;
        } catch (Throwable $throwable) {
            $result->success = false;
            $result->status = 'failed';
            $result->error = $throwable->getMessage();

            return $result;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return array<int, array<string, mixed>> `state`: applied | pending | modified | missing
     */
    public function status(): array
    {
        $applied = $this->tableExists($this->connection) ? $this->applied($this->connection) : null;
        $rows = [];
        $onDisk = [];

        foreach ($this->files() as $file) {
            $onDisk[$file['id']] = true;
            $row = $applied[$file['id']] ?? null;

            if ($row === null) {
                $state = $applied === null && $file['source'] === 'app' ? 'applied' : 'pending';
                $rows[] = ['id' => $file['id'], 'source' => $file['source'], 'state' => $state, 'batch' => null, 'applied_at' => null];
                continue;
            }

            $modified = (string) $row['checksum'] !== '' && $row['checksum'] !== $file['checksum'];
            $rows[] = [
                'id' => $file['id'],
                'source' => $file['source'],
                'state' => $modified ? 'modified' : 'applied',
                'batch' => (int) $row['batch'],
                'applied_at' => $row['applied_at'],
            ];
        }

        foreach ($applied ?? [] as $id => $row) {
            if (!isset($onDisk[$id])) {
                $rows[] = ['id' => $id, 'source' => $row['source'], 'state' => 'missing', 'batch' => (int) $row['batch'], 'applied_at' => $row['applied_at']];
            }
        }

        return $rows;
    }

    /**
     * Per la CI: segnala file modificati dopo l'applicazione e file spariti.
     * Non serve il database di produzione: con registro assente non c'è nulla da confrontare.
     *
     * @return string[]
     */
    public function check(): array
    {
        $problems = [];

        foreach ($this->status() as $row) {
            if ($row['state'] === 'modified') {
                $problems[] = "{$row['id']}: il file è cambiato dopo l'applicazione.";
            } elseif ($row['state'] === 'missing') {
                $problems[] = "{$row['id']}: applicata ma il file non c'è più.";
            }
        }

        return $problems;
    }

    /** @return array{instance: object, destructive: bool, description: string} */
    private function load(string $path): array
    {
        $instance = require $path;

        if (!is_object($instance) || !method_exists($instance, 'up')) {
            throw new RuntimeException(basename($path).' deve ritornare un oggetto con up(Migration $db).');
        }

        return [
            'instance' => $instance,
            'destructive' => method_exists($instance, 'destructive') ? (bool) $instance->destructive() : false,
            'description' => method_exists($instance, 'description') ? (string) $instance->description() : '',
        ];
    }

    private function environment(): string
    {
        try {
            return Environment::current();
        } catch (Throwable) {
            return Environment::PRODUCTION;
        }
    }

    private function tableExists(mysqli $connection): bool
    {
        $result = $connection->query("SHOW TABLES LIKE '".self::TABLE."'");

        return is_object($result) && ($result->num_rows ?? 0) > 0;
    }

    /** @return array<string, array<string, mixed>> */
    private function applied(mysqli $connection): array
    {
        $rows = [];
        $result = $connection->query('SELECT `migration`, `source`, `batch`, `checksum`, `applied_at` FROM `'.self::TABLE.'`');

        while (is_object($result) && ($row = $result->fetch_assoc())) {
            $rows[(string) $row['migration']] = $row;
        }

        return $rows;
    }

    /** Crea il registro; al primo avvio segna come già applicate le migrazioni di `app`, senza eseguirle. */
    private function baselineIfNew(): void
    {
        if ($this->tableExists($this->connection)) {
            return;
        }

        $this->exec('CREATE TABLE IF NOT EXISTS `'.self::TABLE.'` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `migration` VARCHAR(190) NOT NULL,
            `source` VARCHAR(100) NOT NULL,
            `batch` INT NOT NULL DEFAULT 0,
            `checksum` CHAR(64) NOT NULL DEFAULT \'\',
            `destructive` TINYINT(1) NOT NULL DEFAULT 0,
            `applied_at` DATETIME NOT NULL,
            `duration_ms` INT NOT NULL DEFAULT 0,
            `run_id` INT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

        foreach ($this->files() as $file) {
            if ($file['source'] === 'app') {
                $this->record($file, $this->load($file['path'])['destructive'], 0, 0, null);
            }
        }
    }

    private function nextBatch(): int
    {
        $result = $this->connection->query('SELECT MAX(`batch`) AS b FROM `'.self::TABLE.'`');
        $row = is_object($result) ? $result->fetch_assoc() : null;

        return ((int) ($row['b'] ?? 0)) + 1;
    }

    private function record(array $file, bool $destructive, int $batch, int $durationMs, ?int $runId): void
    {
        $c = $this->connection;
        $this->exec(sprintf(
            "INSERT INTO `%s` (`migration`, `source`, `batch`, `checksum`, `destructive`, `applied_at`, `duration_ms`, `run_id`) VALUES ('%s', '%s', %d, '%s', %d, NOW(), %d, %s)",
            self::TABLE,
            $c->real_escape_string($file['id']),
            $c->real_escape_string($file['source']),
            $batch,
            $file['checksum'],
            $destructive ? 1 : 0,
            $durationMs,
            $runId === null ? 'NULL' : (string) $runId
        ));
    }

    private function exec(string $sql): void
    {
        if ($this->connection->query($sql) === false) {
            throw new RuntimeException('Registro migrazioni: '.$this->connection->error);
        }
    }

    private function acquireLock(): bool
    {
        $result = $this->connection->query("SELECT GET_LOCK('".self::LOCK."', 0) AS lock_status");
        $row = is_object($result) ? $result->fetch_assoc() : null;

        return isset($row['lock_status']) && (int) $row['lock_status'] === 1;
    }

    private function releaseLock(): void
    {
        $this->connection->query("SELECT RELEASE_LOCK('".self::LOCK."')");
    }
}
