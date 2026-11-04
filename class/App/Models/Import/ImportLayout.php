<?php

namespace Wonder\App\Models\Import;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Un tracciato scelto dall'amministratore: quali colonne, in che ordine e
 * con che intestazione (`ImportLayoutColumn`). `layout` è la chiave del
 * tracciato di codice (`catalog`); quello `is_default` lo usano l'export e,
 * come prima proposta, il caricamento.
 *
 * Resta nell'ambiente dove si scrive, come il catalogo.
 */
final class ImportLayout extends Model
{
    public static string $table = 'import_layouts';
    public static string $icon = 'bi bi-layout-three-columns';

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('name')->length(150),
            Column::key('layout')->length(60),
            Column::key('is_default')->enum(['true', 'false'])->default('false'),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->sanitize(),
            Field::key('layout')->text()->sanitize(false),
            Field::key('is_default')->text()->sanitize(false),
        ];
    }
}
