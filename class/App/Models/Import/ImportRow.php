<?php

namespace Wonder\App\Models\Import;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Una riga del file, già letta: `data` sono le celle per chiave di colonna,
 * `errors` e `warnings` quello che l'anteprima ha trovato.
 *
 * Si scrivono a blocchi con SQL diretto (`Support\Import\Store`): un file da
 * 50.000 righe non passa dal `create()` di una alla volta.
 */
final class ImportRow extends Model
{
    public static string $table = 'import_rows';
    public static string $icon = 'bi bi-list-ol';

    public const STATUSES = ['pending', 'ok', 'error', 'skipped', 'done'];

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('import_job_id')->int()->null(false)->foreign(ImportJob::$table),
            Column::key('line')->int(),
            Column::key('data')->json(),
            Column::key('status')->enum(self::STATUSES)->default('pending'),
            Column::key('action')->length(20),
            Column::key('errors')->json(),
            Column::key('warnings')->json(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_job_status' => ['index' => ['import_job_id', 'status']],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('import_job_id')->number()->decimals(0),
            Field::key('line')->number()->decimals(0),
            Field::key('status')->text()->sanitize(false),
            Field::key('action')->text()->sanitize(false),
        ];
    }
}
