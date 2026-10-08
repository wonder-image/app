<?php

namespace Wonder\App\Models\Css;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;

final class CssFont extends Model
{
    public static string $table = 'css_font';
    public static string $folder = 'app/css/font';
    public static string $icon = 'bi bi-fonts';

    public static function syncSchema(): ?SyncSchema
    {
        // Gli id restano gli stessi in ogni ambiente: le impostazioni puntano ai font per id.
        return SyncSchema::multiRow()->keepIds();
    }

    public static function tableSchema(): array
    {
        return [
            ...static::sqlColumnsFromDataSchema([
                'name',
                'slug',
                'link',
                'font_family',
                'visible',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->required(),
            // Chiave tecnica: nasce dal nome alla creazione, non è nel form e non cambia.
            Field::key('slug')->text()->slug()->immutableOnUpdate(),
            Field::key('link')->text()->required(),
            Field::key('font_family')->text()->required(),
            Field::key('visible')->text()->required(),
        ];
    }
}
