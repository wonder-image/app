<?php

namespace Wonder\App\Models\Scheduler;

use Wonder\App\Model;
use Wonder\Sql\TableSchema as Column;

class Run extends Model
{
    public static string $table = 'scheduler_runs';
    public static string $folder = 'app/scheduler/runs';
    public static string $icon = 'bi bi-journal-text';
    public static function tableSchema(): array
    {
        return [
            Column::key('schedule_id')->int()->null(), Column::key('task_key')->length(120),
            Column::key('status')->length(30), Column::key('started_at')->datetime(),
            Column::key('finished_at')->datetime()->null(), Column::key('duration_ms')->type('DOUBLE')->null(),
            Column::key('memory_bytes')->type('BIGINT')->null(), Column::key('cpu_ms')->type('DOUBLE')->null(),
            Column::key('output')->type('TEXT')->null(), Column::key('result')->type('TEXT')->null(),
        ];
    }
    public static function dataSchema(): array { return []; }
    public static function tablePseudos(): array
    {
        return ['ind_started' => ['index' => 'started_at'], 'ind_task_started' => ['index' => ['task_key', 'started_at']],
            'ind_schedule' => ['index' => 'schedule_id']];
    }
}
