<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Ultima lettura di una sede da Google Places (Place Details): una riga per
 * sede, scritta solo dal task `wonder.google-places`.
 *
 * `place_id` è quello letto: se la sede cambia luogo la copia non vale più.
 * `hours` e `special_hours` sono le righe di Google già convertite,
 * `differences` le differenze trovate con gli orari del sito all'ultima
 * lettura riuscita. Dati di produzione, non sincronizzati.
 */
final class SocietyLocationGoogle extends Model
{
    public static string $table = 'society_location_google';
    public static string $folder = 'app/config/locations';
    public static string $icon = 'bi bi-google';

    public static function tableSchema(): array
    {
        return [
            Column::key('society_location_id')->int()->null(false)->foreign('society_locations'),
            Column::key('place_id'),
            Column::key('business_status')->length(40),
            Column::key('rating')->float()->null(),
            Column::key('rating_count')->int()->null(),
            Column::key('maps_uri')->length(500),
            Column::key('hours')->json(),
            Column::key('special_hours')->json(),
            Column::key('differences')->json(),
            Column::key('synced_at')->datetime()->null(),
            Column::key('error')->type('TEXT'),
            Column::key('error_at')->datetime()->null(),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'uq_society_location' => ['unique' => ['society_location_id']],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('society_location_id')->number()->decimals(0),
            Field::key('place_id')->text()->sanitize(false),
            Field::key('business_status')->text()->sanitize(false),
            Field::key('rating')->number()->decimals(1),
            Field::key('rating_count')->number()->decimals(0),
            Field::key('maps_uri')->text()->sanitize(false),
            Field::key('hours')->text()->json()->sanitize(false),
            Field::key('special_hours')->text()->json()->sanitize(false),
            Field::key('differences')->text()->json()->sanitize(false),
            Field::key('synced_at')->text()->sanitize(false),
            Field::key('error')->text()->sanitize(false),
            Field::key('error_at')->text()->sanitize(false),
        ];
    }
}
