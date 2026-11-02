<?php

namespace Wonder\App\Resources\Scheduler;

use Wonder\App\Resource;
use Wonder\App\Scheduler\{Presentation, Repository, TaskRegistry};
use Wonder\App\ResourceSchema\{ApiSchema, NavigationSchema, PageSchema, PermissionSchema, TableColumn, TableLayoutSchema};

class RunResource extends Resource
{
    /** Periodi del filtro: chiave GET => [etichetta, secondi]. */
    public const PERIODS = ['24h' => ['Ultime 24 ore', 86400], '7d' => ['Ultimi 7 giorni', 604800], '30d' => ['Ultimi 30 giorni', 2592000]];

    public static string $model = \Wonder\App\Models\Scheduler\Run::class;
    public static function textSchema(): array { return ['label' => 'esecuzione', 'plural_label' => 'esecuzioni', 'article' => 'le', 'last' => 'ultime', 'all' => 'tutte', 'this' => 'questa']; }
    public static function labelSchema(): array
    {
        return ['task_key' => 'Attivita', 'status' => 'Esito', 'started_at' => 'Avvio (UTC)', 'finished_at' => 'Fine (UTC)',
            'duration_ms' => 'Durata', 'memory_bytes' => 'Picco memoria PHP', 'cpu_ms' => 'CPU', 'output' => 'Output', 'result' => 'Risultato'];
    }
    public static function tableSchema(): array
    {
        return [TableColumn::key('task_key')->text()->link('view'), TableColumn::key('status')->text()->formatter(static fn ($row) => Presentation::status($row['status'])),
            TableColumn::key('started_at')->text()->link('view'),
            TableColumn::key('duration_ms')->text()->formatter(static fn ($row) => e(Presentation::number($row['duration_ms'], 1000, 's'))),
            TableColumn::key('memory_bytes')->text()->formatter(static fn ($row) => e(Presentation::number($row['memory_bytes'], 1048576, 'MiB'))),
            TableColumn::key('cpu_ms')->text()->formatter(static fn ($row) => e(Presentation::number($row['cpu_ms'], 1, 'ms')))];
    }
    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)->title('Registro esecuzioni - 180 giorni')->results()->hideButtonAdd()
            ->searchFields(['task_key', 'status'])->filters()
            ->filterCustom('Attivita', 'task_key', static::taskFilterOptions(), 'checkbox', true)
            ->filterCustom('Esito', 'status', Presentation::statusLabels(), 'checkbox')
            ->filterQuery('Periodo', 'period', ['' => 'Ultimi '.Repository::RETENTION_DAYS.' giorni']
                + array_map(static fn (array $period): string => $period[0], self::PERIODS),
                static fn (array $values): string => self::periodCondition($values), 'radio');
    }

    /** Condizione del filtro Periodo: vince il periodo piu ampio fra quelli noti, vuota se nessuno. */
    public static function periodCondition(array $values, ?int $now = null): string
    {
        $seconds = array_map(static fn (string $key): int => self::PERIODS[$key][1], array_values(array_intersect($values, array_keys(self::PERIODS))));
        return $seconds === [] ? '' : "`started_at` >= '".gmdate('Y-m-d H:i:s', ($now ?? time()) - max($seconds))."'";
    }

    /**
     * Opzioni del filtro Attivita: le chiavi delle esecuzioni registrate,
     * etichettate dall'attivita in codice, poi dal nome della pianificazione,
     * altrimenti dalla chiave stessa.
     *
     * @param array<int, string> $keys
     * @param array<string, string> $taskLabels
     * @param array<string, string> $scheduleNames
     * @return array<string, string>
     */
    public static function taskOptions(array $keys, array $taskLabels, array $scheduleNames): array
    {
        $options = [];
        foreach ($keys as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $options[$key] = trim((string) ($taskLabels[$key] ?? '')) ?: (trim((string) ($scheduleNames[$key] ?? '')) ?: $key);
            }
        }
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        return $options;
    }

    private static function taskFilterOptions(): array
    {
        try {
            $repository = new Repository();
            $keys = array_column($repository->rows("SELECT DISTINCT task_key FROM scheduler_runs WHERE deleted = 'false' AND started_at >= ?",
                [gmdate('Y-m-d H:i:s', time() - Repository::RETENTION_DAYS * 86400)]), 'task_key');
            $names = array_column($repository->rows("SELECT task_key, name FROM scheduler_schedules WHERE deleted = 'false' ORDER BY id"), 'name', 'task_key');
        } catch (\Throwable) {
            return [];
        }
        try {
            $labels = TaskRegistry::options();
        } catch (\Throwable) {
            $labels = [];
        }
        return static::taskOptions($keys, $labels, $names);
    }
    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)->only(['list', 'view'])
            ->view('show', dirname(__DIR__, 4).'/app/view/pages/backend/scheduler/run.php');
    }
    public static function apiSchema(): ApiSchema { return ApiSchema::for(static::class)->enabled(false); }
    public static function permissionSchema(): PermissionSchema { return PermissionSchema::for(static::class)->access('diagnostica', ['list', 'view']); }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('dev')
            ->inGroup('automations')
            ->title('Registro esecuzioni')
            ->access('diagnostica')
            ->order(20);
    }
    public static function querySchema(): array
    {
        return array_replace(parent::querySchema(), [
            'condition' => "deleted = 'false' AND started_at >= '".gmdate('Y-m-d H:i:s', time() - 180 * 86400)."'",
        ]);
    }
}
