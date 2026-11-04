<?php

namespace Wonder\App\Models\Import;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/** Una colonna di un tracciato: la chiave, l'intestazione nel file e la posizione. */
final class ImportLayoutColumn extends Model
{
    public static string $table = 'import_layout_columns';
    public static string $icon = 'bi bi-layout-three-columns';

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('import_layout_id')->int()->null(false)->foreign(ImportLayout::$table),
            Column::key('column_key')->length(100),
            Column::key('header')->length(150),
            Column::key('position')->int(),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('import_layout_id')->number()->decimals(0),
            Field::key('column_key')->text()->sanitize(false),
            Field::key('header')->text()->sanitize(false),
            Field::key('position')->number()->decimals(0),
        ];
    }
}
