<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class SocietyTimetable extends Model
{
    public static string $table = 'society_timetable';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-clock';

    public static function tableSchema(): array
    {
        return [
            Column::key('society_address_id')->int()->null(false)->foreign('society_address'),
            Column::key('position')->int()->null(false),
            Column::key('day')->enum(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])->null(false),
            Column::key('from_time')->length(5)->null(false),
            Column::key('to_time')->length(5)->null(false),
            Column::key('deleted')->default('false'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('society_address_id')->number(),
            Field::key('position')->number(),
            Field::key('day')->text(),
            Field::key('from_time')->text(),
            Field::key('to_time')->text(),
            Field::key('deleted')->text()->sanitize(false),
        ];
    }
}
