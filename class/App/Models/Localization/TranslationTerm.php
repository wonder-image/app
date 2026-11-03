<?php

namespace Wonder\App\Models\Localization;

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Il vocabolario delle traduzioni automatiche: un termine nella lingua base
 * che si traduce sempre allo stesso modo (`glossary`, traduzioni in
 * `translations` con `field = term`) o che non si traduce mai (`keep`).
 *
 * I `candidate` li propone il task contando i segmenti ripetuti; il cliente li
 * conferma o li scarta (`rejected`, non più proposti).
 *
 * Non si sincronizza, come le traduzioni che governa.
 */
class TranslationTerm extends Model
{
    public static string $table = 'translation_terms';
    public static string $folder = 'translation-terms';
    public static string $icon = 'bi bi-journal-text';

    public const GLOSSARY = 'glossary';
    public const KEEP = 'keep';
    public const MODES = [self::GLOSSARY, self::KEEP];

    public const CONFIRMED = 'confirmed';
    public const CANDIDATE = 'candidate';
    public const REJECTED = 'rejected';
    public const STATUSES = [self::CONFIRMED, self::CANDIDATE, self::REJECTED];

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('term')->length(191)->unique(),
            Column::key('mode')->enum(self::MODES)->default(self::GLOSSARY),
            Column::key('status')->enum(self::STATUSES)->default(self::CONFIRMED),
            Column::key('occurrences')->int()->default('0'),
        ];
    }

    public static function tablePseudos(): array
    {
        return ['ind_status' => ['index' => 'status']];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('term')->text()->sanitize(false)->required(),
            Field::key('mode')->text()->sanitize(false),
            Field::key('status')->text()->sanitize(false),
            Field::key('occurrences')->number()->decimals(0),
        ];
    }
}
