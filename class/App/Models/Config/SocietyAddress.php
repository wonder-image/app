<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class SocietyAddress extends Model
{
    public static string $table = 'society_address';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-geo-alt';

    public static function tableSchema(): array
    {
        return [
            Column::key('country'),
            Column::key('province'),
            Column::key('city'),
            Column::key('cap')->int()->length(5),
            Column::key('street'),
            Column::key('number'),
            Column::key('more'),
            Column::key('gmaps'),
            Column::key('timetable')->type('TEXT'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('country')->text(),
            Field::key('province')->text(),
            Field::key('city')->text(),
            Field::key('cap')->number(),
            Field::key('street')->text(),
            Field::key('number')->text(),
            Field::key('more')->text(),
            Field::key('gmaps')->text(),
            Field::key('timetable')->text()->sanitize(false),
        ];
    }
}
