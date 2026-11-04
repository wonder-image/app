<?php

namespace Wonder\App\Models\Import;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Una modifica fatta da un caricamento, per annullarla: la tabella e la riga
 * toccate, l'azione (`created`, `updated`, `stock`) e i valori di prima.
 * `target` è una chiave del tracciato quando la modifica non è una colonna
 * sola (le categorie di un articolo, i suoi attributi).
 */
final class ImportChange extends Model
{
    public static string $table = 'import_changes';
    public static string $icon = 'bi bi-arrow-counterclockwise';

    public const ACTIONS = ['created', 'updated', 'stock'];

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('import_job_id')->int()->null(false)->foreign(ImportJob::$table),
            Column::key('import_row_id')->int(),
            Column::key('target')->length(100),
            Column::key('record_id')->int(),
            Column::key('action')->enum(self::ACTIONS),
            Column::key('old_values')->json(),
            Column::key('stock_movement_id')->int(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_job' => ['index' => 'import_job_id'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('import_job_id')->number()->decimals(0),
            Field::key('import_row_id')->number()->decimals(0),
            Field::key('target')->text()->sanitize(false),
            Field::key('record_id')->number()->decimals(0),
            Field::key('action')->text()->sanitize(false),
            Field::key('stock_movement_id')->number()->decimals(0),
        ];
    }
}
