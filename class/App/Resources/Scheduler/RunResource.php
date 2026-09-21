<?php

namespace Wonder\App\Resources\Scheduler;

use Wonder\App\Resource;
use Wonder\App\Scheduler\Presentation;
use Wonder\App\ResourceSchema\{ApiSchema, NavigationSchema, PageSchema, PermissionSchema, TableColumn, TableLayoutSchema};

class RunResource extends Resource
{
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
            TableColumn::key('started_at')->text(),
            TableColumn::key('duration_ms')->text()->formatter(static fn ($row) => e(Presentation::number($row['duration_ms'], 1000, 's'))),
            TableColumn::key('memory_bytes')->text()->formatter(static fn ($row) => e(Presentation::number($row['memory_bytes'], 1048576, 'MiB'))),
            TableColumn::key('cpu_ms')->text()->formatter(static fn ($row) => e(Presentation::number($row['cpu_ms'], 1, 'ms')))];
    }
    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)->title('Registro esecuzioni - 180 giorni')->results()->hideButtonAdd()
            ->searchFields(['task_key', 'status'])->filters();
    }
    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)->only(['list', 'view'])
            ->view('show', dirname(__DIR__, 4).'/app/view/pages/backend/scheduler/run.php');
    }
    public static function apiSchema(): ApiSchema { return ApiSchema::for(static::class)->enabled(false); }
    public static function permissionSchema(): PermissionSchema { return PermissionSchema::for(static::class)->backend(['list', 'view'], ['admin']); }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)->inSection('dev')->title('Registro esecuzioni')->authority(['admin'])->order(20);
    }
    public static function querySchema(): array
    {
        return array_replace(parent::querySchema(), [
            'condition' => "deleted = 'false' AND started_at >= '".gmdate('Y-m-d H:i:s', time() - 180 * 86400)."'",
        ]);
    }
}
