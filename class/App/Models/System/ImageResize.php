<?php

namespace Wonder\App\Models\System;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Coda dei ridimensionamenti: una riga per file, legata al percorso
 * relativo a `ROOT` (non a Media), così vale per ogni campo file. Le
 * larghezze si salvano qui al caricamento: lo scheduler non conosce
 * `RESPONSIVE_IMAGE_SIZES`. La scrive solo `Wonder\App\Media\ResizeQueue`.
 *
 * Stati: `pending` → `ready` | `failed`.
 */
final class ImageResize extends Model
{
    public static string $table = 'image_resizes';
    public static string $icon = 'bi bi-images';

    public const STATUSES = ['pending', 'ready', 'failed'];

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('path')->length(1000),
            // sha1 del percorso: l'unicità non dipende dalla collation né dalla lunghezza.
            Column::key('path_hash')->length(40)->unique(),
            Column::key('widths')->type('TEXT'),
            Column::key('webp')->length(5)->default('true'),
            Column::key('quality')->int(),
            Column::key('delete_when_ready')->type('TEXT'),
            Column::key('status')->length(20)->default('pending'),
            Column::key('attempts')->int()->default('0'),
            Column::key('error')->type('TEXT'),
            Column::key('queued_at')->datetime(),
            Column::key('next_attempt_at')->datetime(),
            Column::key('claim_token')->length(32),
            Column::key('claimed_until')->datetime(),
            Column::key('finished_at')->datetime(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_status_next_attempt' => ['index' => ['status', 'next_attempt_at']],
        ];
    }

    public static function dataSchema(): array
    {
        return array_map(
            static fn (string $key) => Field::key($key)->text()->sanitize(false),
            ['path', 'path_hash', 'widths', 'webp', 'quality', 'delete_when_ready', 'status', 'attempts', 'error', 'queued_at', 'next_attempt_at', 'claim_token', 'claimed_until', 'finished_at']
        );
    }
}
