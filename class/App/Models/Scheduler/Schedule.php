<?php

namespace Wonder\App\Models\Scheduler;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

class Schedule extends Model
{
    public static string $table = 'scheduler_schedules';
    public static string $folder = 'app/scheduler/schedules';
    public static string $icon = 'bi bi-clock';

    public static function tableSchema(): array
    {
        return [
            Column::key('name'), Column::key('task_key')->length(120),
            Column::key('expression')->length(100)->default('0 0 * * *'),
            Column::key('timezone')->length(100)->default('Europe/Rome'),
            Column::key('parameters')->type('TEXT')->null(),
            Column::key('enabled')->length(5)->default('false'),
            Column::key('requested')->length(5)->default('false'),
            Column::key('next_due')->datetime()->null(),
            Column::key('last_started')->datetime()->null(),
        ];
    }
    public static function tablePseudos(): array
    {
        return ['ind_due' => ['index' => ['enabled', 'next_due']], 'ind_task' => ['index' => 'task_key']];
    }
    public static function dataSchema(): array
    {
        return array_map(static fn (string $key) => Field::key($key)->text()->sanitize(false),
            ['name', 'task_key', 'expression', 'timezone', 'parameters', 'enabled', 'requested', 'next_due', 'last_started']);
    }
}
