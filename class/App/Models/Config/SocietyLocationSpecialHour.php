<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Orari speciali e chiusure di una sede, come `specialHours` di Google.
 * `closed = true` vale anche su un intervallo di date; un'apertura
 * straordinaria dura al massimo fino al giorno dopo. Dati di produzione,
 * non sincronizzati.
 */
final class SocietyLocationSpecialHour extends Model
{
    public const SOURCES = ['manual', 'google'];

    public static string $table = 'society_location_special_hours';
    public static string $folder = 'app/config/locations';
    public static string $icon = 'bi bi-calendar-x';

    public static function tableSchema(): array
    {
        return [
            Column::key('society_location_id')->int()->null(false)->foreign('society_locations'),
            ...static::sqlColumnsFromDataSchema(['start_date', 'end_date']),
            Column::key('closed')->enum(['true', 'false'])->default('true'),
            Column::key('open_time')->length(5),
            Column::key('close_time')->length(5),
            Column::key('note'),
            Column::key('source')->enum(self::SOURCES)->default('manual'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('society_location_id')->number(),
            Field::key('start_date')->date(),
            Field::key('end_date')->date(),
            Field::key('closed')->text()->sanitize(false),
            Field::key('open_time')->text()->sanitize(false),
            Field::key('close_time')->text()->sanitize(false),
            Field::key('note')->text(),
            Field::key('source')->text()->sanitize(false),
        ];
    }
}
