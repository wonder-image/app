<?php

namespace Wonder\App\Models\Localization;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Memoria del motore: lo stesso testo fra le stesse lingue non torna al
 * driver. `hash` è sha256 di `<da>|<a>|<testo>`.
 *
 * Non si sincronizza: è una cache, si ricostruisce da sola.
 */
class TranslationMemory extends Model
{
    public static string $table = 'translation_memory';
    public static string $folder = 'translation-memory';
    public static string $icon = 'bi bi-database';

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('hash')->length(64)->unique(),
            Column::key('source_language')->length(10),
            Column::key('target_language')->length(10),
            Column::key('source')->type('MEDIUMTEXT'),
            Column::key('value')->type('MEDIUMTEXT'),
            Column::key('engine')->length(50),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('hash')->text()->sanitize(false),
            Field::key('source_language')->text()->sanitize(false),
            Field::key('target_language')->text()->sanitize(false),
            Field::key('source')->text()->sanitize(false),
            Field::key('value')->text()->sanitize(false),
            Field::key('engine')->text()->sanitize(false),
        ];
    }
}
