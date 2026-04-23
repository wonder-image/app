<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

final class SocietySocial extends Model
{
    public static string $table = 'society_social';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-share';

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'site',
                'instagram',
                'facebook',
                'tiktok',
                'linkedin',
                'whatsapp',
                'youtube',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('site')->text(),
            Field::key('instagram')->text(),
            Field::key('facebook')->text(),
            Field::key('tiktok')->text(),
            Field::key('linkedin')->text(),
            Field::key('whatsapp')->text(),
            Field::key('youtube')->text(),
        ];
    }
}
