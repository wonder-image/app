<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class Society extends Model
{
    public static string $table = 'society';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-buildings';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'name',
                'legal_name',
                'email',
                'pec',
                'tel',
                'cel',
                'pi',
                'cf',
                'sdi',
                'rea',
                'share_capital',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text(),
            Field::key('legal_name')->text(),
            Field::key('email')->text(),
            Field::key('pec')->text(),
            Field::key('tel')->text(),
            Field::key('cel')->text(),
            Field::key('pi')->text(),
            Field::key('cf')->text(),
            Field::key('sdi')->text()->upper(),
            Field::key('rea')->text(),
            Field::key('share_capital')->number()->decimals(2),
        ];
    }
}
