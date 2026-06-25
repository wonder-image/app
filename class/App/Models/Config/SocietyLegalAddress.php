<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\App\Support\SyncSchema;

final class SocietyLegalAddress extends Model
{
    public static string $table = 'society_legal_address';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-bank';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::singleton();
    }

    public static function tableSchema(): array
    {
        return AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->tableSchema();
    }

    public static function dataSchema(): array
    {
        return AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->dataSchema();
    }

    public static function decorate(array $row): array
    {
        return AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps')->decorate($row);
    }
}
