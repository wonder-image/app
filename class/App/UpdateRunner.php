<?php

namespace Wonder\App;

use mysqli;
use Throwable;
use Wonder\Sql\Connection;

class UpdateRunner
{
    private mysqli $connection;

    public function __construct(?mysqli $connection = null)
    {
        global $mysqli;

        if ($connection instanceof mysqli) {
            $this->connection = $connection;
            return;
        }

        if (($mysqli ?? null) instanceof mysqli) {
            $this->connection = $mysqli;
            return;
        }

        $this->connection = Connection::Connect('main');
    }

    public function latestRun(): ?array
    {
        if (!sqlTableExists('app_update_runs')) {
            return null;
        }

        $result = sqlSelect('app_update_runs', null, 1, 'id', 'DESC');

        if (!$result->success || !$result->exists) {
            return null;
        }

        return $result->row;
    }

    public function execute(array $options = []): object
    {
        $releaseId = trim((string) ($options['release_id'] ?? ''));
        $triggerType = trim((string) ($options['trigger_type'] ?? 'api'));
        $source = trim((string) ($options['source'] ?? 'api'));
        $includeCliFiles = (bool) ($options['include_cli_files'] ?? $options['include_local_files'] ?? false);
        $runId = 0;
        $lock = null;
        $startedAt = date('Y-m-d H:i:s');

        $result = (object) [
            'success' => false,
            'status' => 'pending',
            'skipped' => false,
            'run_id' => null,
            'release_id' => $releaseId,
            'trigger_type' => $triggerType,
            'source' => $source,
            'started_at' => $startedAt,
            'finished_at' => null,
            'message' => '',
            'stats' => (object) [
                'tables' => 0,
                'rows' => 0,
                'update' => 0,
                'local' => 0,
            ],
            'api_sync' => null,
            'lock_acquired' => false,
        ];

        try {
            $this->loadTableSchemas();
            $this->ensureTrackingTable();

            $lock = new UpdateLock($this->connection, $this->lockName());

            if (!$lock->acquire(5)) {
                $result->status = 'locked';
                $result->message = 'Update già in esecuzione.';
                return $result;
            }

            $result->lock_acquired = true;

            if ($releaseId !== '' && $this->isReleaseAlreadyApplied($releaseId)) {
                $result->success = true;
                $result->status = 'skipped';
                $result->skipped = true;
                $result->message = 'Release già applicata.';
                $result->finished_at = date('Y-m-d H:i:s');
                return $result;
            }

            $runId = $this->createRunRow($releaseId, $triggerType, $source, $startedAt);
            $result->run_id = $runId > 0 ? $runId : null;

            $result->stats->tables = $this->runTables();
            $result->stats->rows = $this->runFiles($this->rowDirectories());
            $result->stats->update = $this->runFiles($this->updateDirectories());

            if ($includeCliFiles) {
                $result->stats->local = $this->runFiles($this->cliDirectories());
            }

            $result->api_sync = $this->syncApiStatus();

            $result->success = true;
            $result->status = 'success';
            $result->message = 'Update completato.';
            $result->finished_at = date('Y-m-d H:i:s');

            if ($runId > 0) {
                $this->finishRunRow($runId, 'success', $result);
            }

            return $result;
        } catch (Throwable $throwable) {
            $result->success = false;
            $result->status = 'failed';
            $result->message = $throwable->getMessage();
            $result->finished_at = date('Y-m-d H:i:s');

            if ($runId > 0) {
                $this->finishRunRow($runId, 'failed', $result, $throwable->getMessage());
            }

            return $result;
        } finally {
            if ($lock instanceof UpdateLock) {
                $lock->release();
            }
        }
    }

    public function jsonPayload(object $result): string
    {
        return json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }

    private function ensureTrackingTable(): void
    {
        global $TABLE;

        if (!is_object($TABLE ?? null)) {
            $TABLE = (object) [];
        }

        if (!isset($TABLE->APP_UPDATE_RUNS)) {
            $TABLE->APP_UPDATE_RUNS = [
                'release_id' => [
                    'sql' => [
                        'length' => 100,
                        'null' => true,
                    ],
                ],
                'trigger_type' => [
                    'sql' => [
                        'length' => 50,
                    ],
                ],
                'status' => [
                    'sql' => [
                        'length' => 30,
                    ],
                ],
                'request_ip' => [
                    'sql' => [
                        'length' => 45,
                        'null' => true,
                    ],
                ],
                'request_uri' => [
                    'sql' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                ],
                'app_version' => [
                    'sql' => [
                        'length' => 20,
                    ],
                ],
                'runner_version' => [
                    'sql' => [
                        'length' => 20,
                    ],
                ],
                'source' => [
                    'sql' => [
                        'length' => 30,
                    ],
                ],
                'started_at' => [
                    'sql' => [
                        'type' => 'DATETIME',
                    ],
                ],
                'finished_at' => [
                    'sql' => [
                        'type' => 'DATETIME',
                        'null' => true,
                    ],
                ],
                'payload_json' => [
                    'sql' => [
                        'type' => 'LONGTEXT',
                        'null' => true,
                    ],
                ],
                'error_message' => [
                    'sql' => [
                        'type' => 'TEXT',
                        'null' => true,
                    ],
                ],
                'ind_release_status' => [
                    'sql' => [
                        'index' => ['release_id', 'status'],
                    ],
                ],
            ];
        }

        sqlTable('app_update_runs', $TABLE->APP_UPDATE_RUNS);
    }

    private function loadTableSchemas(): void
    {
        global $ROOT;
        global $ROOT_APP;
        global $TABLE;

        if (!is_object($TABLE ?? null)) {
            $TABLE = (object) [];
        }

        extract($GLOBALS, EXTR_SKIP);

        if (!is_object($DB ?? null)) {
            $DB = (object) ['database' => []];
        }

        if (!is_object($DEFAULT ?? null)) {
            $DEFAULT = RuntimeDefaults::mergeStyleDefaults(null, $PATH ?? null);
        } else {
            $DEFAULT = RuntimeDefaults::mergeStyleDefaults($DEFAULT, $PATH ?? null);
        }

        foreach ($this->tableDirectories() as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (scanParentDir($directory) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                include $directory.$file;
            }
        }

        foreach (get_object_vars($TABLE) as $tableName => $tableSchema) {
            if (!is_array($tableSchema)) {
                continue;
            }

            Table::key((string) $tableName)->setSchema($tableSchema);
        }
    }

    private function createRunRow(string $releaseId, string $triggerType, string $source, string $startedAt): int
    {
        $insert = sqlInsert('app_update_runs', [
            'release_id' => $releaseId !== '' ? $releaseId : null,
            'trigger_type' => $triggerType,
            'status' => 'running',
            'request_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
            'app_version' => (string) APP_VERSION,
            'runner_version' => '2.0.0',
            'source' => $source,
            'started_at' => $startedAt,
        ]);

        return (int) ($insert->insert_id ?? 0);
    }

    private function finishRunRow(int $runId, string $status, object $result, string $errorMessage = ''): void
    {
        sqlModify('app_update_runs', [
            'status' => $status,
            'finished_at' => $result->finished_at ?? date('Y-m-d H:i:s'),
            'payload_json' => $this->jsonPayload($result),
            'error_message' => $errorMessage !== '' ? $errorMessage : null,
        ], 'id', $runId);
    }

    private function isReleaseAlreadyApplied(string $releaseId): bool
    {
        if (!sqlTableExists('app_update_runs')) {
            return false;
        }

        $run = sqlSelect('app_update_runs', [
            'release_id' => $releaseId,
            'status' => 'success',
        ], 1, 'id', 'DESC');

        return $run->success && $run->exists;
    }

    private function runTables(): int
    {
        global $TABLE;

        $count = 0;
        $pending = get_object_vars($TABLE);
        $lastError = null;

        while (!empty($pending)) {
            $progress = false;

            foreach ($pending as $table => $value) {
                try {
                    sqlTable(strtolower((string) $table), $value);
                    unset($pending[$table]);
                    $count++;
                    $progress = true;
                } catch (Throwable $throwable) {
                    $lastError = $throwable;
                }
            }

            if ($progress) {
                continue;
            }

            if ($lastError instanceof Throwable) {
                throw $lastError;
            }

            break;
        }

        return $count;
    }

    private function rowDirectories(): array
    {
        global $ROOT;
        global $ROOT_APP;

        return [
            $ROOT_APP.'/build/row/',
            $ROOT.'/custom/build/row/',
        ];
    }

    private function tableDirectories(): array
    {
        global $ROOT;
        global $ROOT_APP;

        return [
            $ROOT_APP.'/build/table/',
            $ROOT.'/custom/build/table/',
        ];
    }

    private function updateDirectories(): array
    {
        global $ROOT;
        global $ROOT_APP;

        return [
            $ROOT_APP.'/build/update/',
            $ROOT.'/custom/build/update/',
        ];
    }

    private function cliDirectories(): array
    {
        global $ROOT;
        global $ROOT_APP;

        return [
            $ROOT_APP.'/build/cli/',
            $ROOT.'/custom/build/cli/',
        ];
    }

    private function runFiles(array $directories): int
    {
        extract($GLOBALS, EXTR_SKIP);

        if (!is_object($API ?? null)) {
            $API = (object) ['key' => ''];
        }

        if (!is_object($DEFAULT ?? null)) {
            $DEFAULT = RuntimeDefaults::mergeStyleDefaults(null, $PATH ?? null);
        } else {
            $DEFAULT = RuntimeDefaults::mergeStyleDefaults($DEFAULT, $PATH ?? null);
        }

        if (!is_object($PAGE ?? null)) {
            $PAGE = (object) ['domain' => ''];
        }

        $count = 0;

        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            foreach (scanParentDir($directory) as $file) {
                if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                include $directory.$file;
                $count++;
            }
        }

        return $count;
    }

    private function syncApiStatus(): array
    {
        $status = wiApiJson('/auth/status/');

        if (!is_array($status) || !($status['success'] ?? false)) {
            return [
                'success' => false,
                'message' => (string) ($status['response'] ?? 'Sync API non disponibile.'),
            ];
        }

        if (($status['response']['active'] ?? false) == true) {
            $update = wiApiJson('/auth/update/', [
                'site_name' => $this->currentDomain(),
                'app_version' => (string) APP_VERSION,
            ]);

            return [
                'success' => (bool) ($update['success'] ?? false),
                'message' => (string) (($status['response']['description'] ?? '') ?: ($update['response'] ?? 'API aggiornata.')),
                'active' => true,
            ];
        }

        $request = wiApiJson('/auth/request/', [
            'site_name' => $this->currentDomain(),
            'app_version' => (string) APP_VERSION,
        ]);

        return [
            'success' => (bool) ($request['success'] ?? false),
            'message' => (string) ($request['response'] ?? 'Richiesta attivazione inviata.'),
            'active' => false,
        ];
    }

    private function lockName(): string
    {
        return 'wonder_image_app_update_'.md5($this->currentDomain());
    }

    private function currentDomain(): string
    {
        global $PAGE;

        if (isset($PAGE->domain) && trim((string) $PAGE->domain) !== '') {
            return trim((string) $PAGE->domain);
        }

        $value = $_ENV['APP_DOMAIN'] ?? getenv('APP_DOMAIN');

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'unknown'));
    }
}
