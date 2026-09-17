<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\App\Support\OpeningHours;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Orari regolari e secondari di una sede, come `regularHours.periods` di
 * Google: più fasce nello stesso giorno sono più righe. Dati di produzione,
 * non sincronizzati.
 */
final class SocietyLocationHour extends Model
{
    public static string $table = 'society_location_hours';
    public static string $folder = 'app/config/opening-hours';
    public static string $icon = 'bi bi-clock';

    public static function tableSchema(): array
    {
        return [
            Column::key('society_location_id')->int()->null(false)->foreign('society_locations'),
            Column::key('hours_type')->length(30)->default(OpeningHours::REGULAR),
            Column::key('open_day')->enum(OpeningHours::DAYS)->null(false),
            Column::key('open_time')->length(5)->null(false),
            Column::key('close_day')->enum(OpeningHours::DAYS),
            Column::key('close_time')->length(5),
            Column::key('position')->int(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('society_location_id')->number(),
            Field::key('hours_type')->text()->sanitize(false),
            Field::key('open_day')->text()->sanitize(false),
            Field::key('open_time')->text()->sanitize(false),
            Field::key('close_day')->text()->sanitize(false),
            Field::key('close_time')->text()->sanitize(false),
        ];
    }
}
