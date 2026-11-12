<?php

namespace Wonder\App\Models\Media;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Chi usa quale media: una riga per ogni media referenziato da una colonna
 * di un record (`owner_table`, `owner_id`, `owner_column`). La scrive solo
 * `Wonder\App\Media\MediaUsages`; resta fuori da `TableSync`.
 */
final class MediaUsage extends Model
{
    public static string $table = 'media_usages';
    public static string $icon = 'bi bi-link-45deg';

    public static function tableSchema(): array
    {
        return [
            Column::key('media_id')->int()->null(false),
            Column::key('owner_table')->length(100)->null(false),
            Column::key('owner_id')->length(64)->null(false),
            Column::key('owner_column')->length(100)->null(false),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_media' => ['index' => 'media_id'],
            'ind_owner' => ['index' => ['owner_table', 'owner_id']],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('media_id')->number()->decimals(0),
            Field::key('owner_table')->text()->sanitize(false),
            Field::key('owner_id')->text()->sanitize(false),
            Field::key('owner_column')->text()->sanitize(false),
        ];
    }
}
