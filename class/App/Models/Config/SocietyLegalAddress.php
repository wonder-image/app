<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class SocietyLegalAddress extends Model
{
    public static string $table = 'society_legal_address';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-bank';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'legal_country',
                'legal_province',
                'legal_city',
            ]),
            Column::key('legal_cap')->int()->length(5),
            ...static::sqlColumnsFromDataSchema([
                'legal_street',
                'legal_number',
                'legal_more',
                'legal_gmaps',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('legal_country')->text(),
            Field::key('legal_province')->text(),
            Field::key('legal_city')->text(),
            Field::key('legal_cap')->number(),
            Field::key('legal_street')->text(),
            Field::key('legal_number')->text(),
            Field::key('legal_more')->text(),
            Field::key('legal_gmaps')->text(),
        ];
    }
}
