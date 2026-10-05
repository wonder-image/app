<?php

namespace Wonder\App\Models\System;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Come si chiama una nostra entità dentro un sistema esterno: l'id della
 * fattura in Fatture in Cloud, quello del cliente in Stripe, la spedizione dal
 * corriere.
 *
 * Non si sincronizza mai: gli id di prova non valgono in produzione, e per
 * questo `environment` fa parte della chiave.
 */
class ExternalReference extends Model
{
    public static string $table = 'external_references';
    public static string $folder = 'external-references';
    public static string $icon = 'bi bi-link-45deg';

    public const ENVIRONMENTS = ['live', 'test'];

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('entity_type')->length(100),
            Column::key('entity_id')->int(),
            Column::key('provider')->length(100),
            Column::key('environment')->enum(self::ENVIRONMENTS)->default('live'),
            Column::key('object_type')->length(100),
            Column::key('external_id')->length(191)
                ->unique(['provider', 'environment', 'object_type', 'external_id']),
            Column::key('synced_at')->datetime(),
            Column::key('sync_error')->type('TEXT'),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_entity' => ['index' => ['entity_type', 'entity_id']],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('entity_type')->text()->sanitize(false),
            Field::key('entity_id')->number()->decimals(0),
            Field::key('provider')->text()->sanitize(false),
            Field::key('environment')->text()->sanitize(false),
            Field::key('object_type')->text()->sanitize(false),
            Field::key('external_id')->text()->sanitize(false),
            Field::key('synced_at')->date(),
            Field::key('sync_error')->text(),
        ];
    }
}
