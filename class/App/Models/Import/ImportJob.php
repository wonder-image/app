<?php

namespace Wonder\App\Models\Import;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Un caricamento da file: il file, le colonne abbinate, lo stato e i
 * contatori. Le righe stanno in `ImportRow`, le modifiche fatte in
 * `ImportChange`.
 *
 * Stati: `uploaded` → `validated` → `running` → `done` | `failed`;
 * `undone` dopo l'annullamento.
 */
final class ImportJob extends Model
{
    public static string $table = 'import_jobs';
    public static string $icon = 'bi bi-file-earmark-arrow-up';

    public const STATUSES = ['uploaded', 'validated', 'running', 'done', 'failed', 'undone'];

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('layout')->length(60),
            Column::key('import_layout_id')->int(),
            Column::key('file_name')->length(255),
            Column::key('path')->length(255),
            Column::key('mapping')->json(),
            Column::key('status')->enum(self::STATUSES)->default('uploaded'),
            Column::key('rows_total')->int(),
            Column::key('rows_valid')->int(),
            Column::key('rows_error')->int(),
            Column::key('rows_done')->int(),
            Column::key('rows_created')->int(),
            Column::key('rows_updated')->int(),
            Column::key('message')->type('TEXT'),
            Column::key('user_id')->int(),
            Column::key('started_at')->datetime(),
            Column::key('finished_at')->datetime(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_status' => ['index' => 'status'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('layout')->text()->sanitize(false),
            Field::key('import_layout_id')->number()->decimals(0),
            Field::key('file_name')->text()->sanitize(false),
            Field::key('path')->text()->sanitize(false),
            Field::key('status')->text()->sanitize(false),
            Field::key('rows_total')->number()->decimals(0),
            Field::key('rows_valid')->number()->decimals(0),
            Field::key('rows_error')->number()->decimals(0),
            Field::key('rows_done')->number()->decimals(0),
            Field::key('rows_created')->number()->decimals(0),
            Field::key('rows_updated')->number()->decimals(0),
            Field::key('message')->text()->sanitize(false),
            Field::key('user_id')->number()->decimals(0),
            Field::key('started_at')->text()->sanitize(false),
            Field::key('finished_at')->text()->sanitize(false),
        ];
    }
}
