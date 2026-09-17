<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Tabella dei vecchi dati aziendali: non più scritta né sincronizzata.
 * Letta solo dalla migrazione verso `society_locations`; verrà rimossa.
 */
final class SocietyAddress extends Model
{
    public static string $table = 'society_address';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-geo-alt';

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

    public static function decorate(array $row): array
    {
        return AddressExtension::simple(linkKey: 'gmaps')->decorate($row);
    }
}
