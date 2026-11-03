<?php

namespace Wonder\App\Models\Localization;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * La traduzione di un campo di un Model traducibile in una lingua. La colonna
 * del Model resta il testo nella lingua base; `source_hash` è lo sha256 di
 * quel testo quando la traduzione è stata fatta.
 *
 * Stati: `automatic` (i job la possono rifare), `reviewed` (corretta a mano,
 * nessun job la tocca più), `review` (il testo base è cambiato dopo una
 * traduzione `reviewed`: si mostra ancora, ma non si rifà da sola).
 *
 * Non si sincronizza: come le righe che traduce, vive nell'ambiente dove il
 * cliente scrive i contenuti, e `row_id` non è stabile fra ambienti.
 */
class Translation extends Model
{
    public static string $table = 'translations';
    public static string $folder = 'translations';
    public static string $icon = 'bi bi-translate';

    public const AUTOMATIC = 'automatic';
    public const REVIEWED = 'reviewed';
    public const REVIEW = 'review';
    public const STATUSES = [self::AUTOMATIC, self::REVIEWED, self::REVIEW];

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('table_name')->length(100),
            Column::key('row_id')->int(),
            Column::key('field')->length(100),
            Column::key('language_code')->length(10)
                ->unique(['table_name', 'row_id', 'field', 'language_code']),
            Column::key('value')->type('MEDIUMTEXT'),
            Column::key('source_hash')->length(64),
            Column::key('status')->enum(self::STATUSES)->default(self::AUTOMATIC),
            Column::key('engine')->length(50),
            Column::key('translated_at')->datetime(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_status' => ['index' => ['table_name', 'language_code', 'status']],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('table_name')->text()->sanitize(false),
            Field::key('row_id')->number()->decimals(0),
            Field::key('field')->text()->sanitize(false),
            Field::key('language_code')->text()->sanitize(false),
            Field::key('value')->text()->sanitize(false),
            Field::key('source_hash')->text()->sanitize(false),
            Field::key('status')->text()->sanitize(false),
            Field::key('engine')->text()->sanitize(false),
            Field::key('translated_at')->date(),
        ];
    }
}
