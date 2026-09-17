<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\App\Schema\Extensions\AddressExtension;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Sede della società: dati, indirizzo con Google Place ID, contatti, dati
 * aziendali e legali, sede legale e link. Una sola sede è predefinita; le
 * altre prendono dalla predefinita ciò che manca
 * (`Wonder\App\Support\SocietyLocationResolver`).
 */
final class SocietyLocation extends Model
{
    public const BUSINESS_STATUSES = ['operational', 'closed_temporarily', 'closed_permanently', 'future_opening'];

    public static string $table = 'society_locations';
    public static string $folder = 'app/config/corporate-data';
    public static string $icon = 'bi bi-buildings';

    public static function syncSchema(): ?SyncSchema
    {
        return SyncSchema::multiRow()->keepIds();
    }

    public static function address(): AddressExtension
    {
        return AddressExtension::simple(linkKey: 'gmaps');
    }

    public static function legalAddress(): AddressExtension
    {
        return AddressExtension::simple(prefix: 'legal', linkKey: 'gmaps');
    }

    public static function tableSchema(): array
    {
        return [
            Column::key('slug')->length(100)->unique(),
            Column::key('label'),
            Column::key('is_default')->enum(['true', 'false'])->default('false'),
            Column::key('visible')->enum(['true', 'false'])->default('true'),
            Column::key('position')->int(),
            Column::key('business_status')->enum(self::BUSINESS_STATUSES)->default('operational'),
            ...static::sqlColumnsFromDataSchema(['opening_date']),
            ...static::address()->tableSchema(),
            Column::key('google_place_id'),
            ...static::sqlColumnsFromDataSchema([
                'google_synced_at',
                'email',
                'pec',
                'tel',
                'cel',
                'name',
                'legal_name',
                'pi',
                'cf',
                'sdi',
                'rea',
                'share_capital',
            ]),
            ...static::legalAddress()->tableSchema(),
            ...static::sqlColumnsFromDataSchema([
                'site',
                'instagram',
                'facebook',
                'tiktok',
                'linkedin',
                'whatsapp',
                'youtube',
            ]),
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('slug')->text()->slug(),
            Field::key('label')->text()->required(),
            Field::key('is_default')->text()->sanitize(false),
            Field::key('visible')->text()->sanitize(false),
            Field::key('business_status')->text()->sanitize(false),
            Field::key('opening_date')->date(),
            ...static::address()->dataSchema(),
            Field::key('google_place_id')->text()->sanitize(false),
            Field::key('google_synced_at')->date(),
            Field::key('email')->email(),
            Field::key('pec')->text(),
            Field::key('tel')->text(),
            Field::key('cel')->text(),
            Field::key('name')->text(),
            Field::key('legal_name')->text(),
            Field::key('pi')->text(),
            Field::key('cf')->tin(),
            Field::key('sdi')->text()->upper(),
            Field::key('rea')->text(),
            Field::key('share_capital')->number()->decimals(2),
            ...static::legalAddress()->dataSchema(),
            Field::key('site')->text(),
            Field::key('instagram')->text(),
            Field::key('facebook')->text(),
            Field::key('tiktok')->text(),
            Field::key('linkedin')->text(),
            Field::key('whatsapp')->text(),
            Field::key('youtube')->text(),
        ];
    }
}
