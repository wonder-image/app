<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

final class SocietyAddress extends Model
{
    public static string $table = 'society_address';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-geo-alt';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::singleton();
    }

    public static function tableSchema(): array
    {
        return [
            ...AddressExtension::simple(linkKey: 'gmaps')->tableSchema(),
            Column::key('timetable')->type('TEXT'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            ...AddressExtension::simple(linkKey: 'gmaps')->dataSchema(),
            Field::key('timetable')->text()->sanitize(false),
        ];
    }
}
