<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;

abstract class ConfigurationFile extends Model
{
    public static string $table = '';
    public static string $folder = 'app/config/configuration-file';
    public static string $icon = 'bi bi-file-earmark-code';

    public static function tableSchema(): array
    {
        return [];
    }

    public static function dataSchema(): array
    {
        return [];
    }
}
