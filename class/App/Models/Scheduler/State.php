<?php

namespace Wonder\App\Models\Scheduler;

use Wonder\App\Model;
use Wonder\Sql\TableSchema as Column;

class State extends Model
{
    public static string $table = 'scheduler_state';
    public static function tableSchema(): array
    {
        return [Column::key('state_key')->length(150)->unique(), Column::key('value')->type('TEXT')];
    }
    public static function dataSchema(): array { return []; }
}
