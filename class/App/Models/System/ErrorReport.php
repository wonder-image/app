<?php

namespace Wonder\App\Models\System;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Errore ripetuto che non ha un documento a cui appendersi.
 *
 * Un servizio esterno che non risponde lo fa cento volte di seguito: qui
 * resta una riga sola con il contatore, così la casella di chi riceve gli
 * avvisi non si riempie di copie dello stesso problema.
 *
 * Sono errori **per chi sviluppa**: un guasto tecnico da correggere. Quello che
 * riguarda chi usa il sito non è un errore ma una notifica, e non passa di qui.
 *
 * Non si sincronizza: è la storia di un ambiente.
 */
final class ErrorReport extends Model
{
    public static string $table = 'error_reports';
    public static string $folder = 'app/system/errors';
    public static string $icon = 'bi bi-exclamation-octagon';

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('fingerprint')->length(191)->null(false)->unique(),
            Column::key('service')->length(100),
            Column::key('action')->length(100),
            Column::key('message')->type('TEXT'),
            Column::key('context')->json(),
            Column::key('occurrences')->int()->default('1'),
            Column::key('first_seen_at')->datetime(),
            Column::key('last_seen_at')->datetime(),
            Column::key('notified_at')->datetime(),
            Column::key('resolved_at')->datetime(),
            Column::key('resolved_by')->int(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_resolved' => ['index' => 'resolved_at'],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('fingerprint')->text()->sanitize(false),
            Field::key('service')->text()->sanitize(false),
            Field::key('action')->text()->sanitize(false),
            Field::key('message')->text(),
            Field::key('context')->json(),
            Field::key('occurrences')->number()->decimals(0),
            Field::key('first_seen_at')->date(),
            Field::key('last_seen_at')->date(),
            Field::key('notified_at')->date(),
            Field::key('resolved_at')->date(),
            Field::key('resolved_by')->number()->decimals(0),
        ];
    }
}
