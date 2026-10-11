<?php

namespace Wonder\App\Models\Config;

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

/**
 * Recensioni Google di una sede (al massimo 5, come le restituisce Place
 * Details), scritte solo dal task `wonder.google-places`.
 *
 * `name` è l'identificativo Google della recensione (`places/…/reviews/…`).
 * I testi passano dal livello SQL, che salva i caratteri fuori da Latin-1
 * (es. emoji) come entità `&#N;`: chi li mostra li decodifica prima di
 * fare l'escape. Dati di produzione, non sincronizzati.
 */
final class SocietyLocationReview extends Model
{
    public static string $table = 'society_location_reviews';
    public static string $folder = 'app/config/locations';
    public static string $icon = 'bi bi-star';

    public static function tableSchema(): array
    {
        return [
            Column::key('society_location_id')->int()->null(false)->foreign('society_locations'),
            Column::key('name'),
            Column::key('rating')->int()->null(),
            Column::key('text')->type('TEXT'),
            Column::key('language')->length(20),
            Column::key('original_text')->type('TEXT'),
            Column::key('original_language')->length(20),
            Column::key('author_name'),
            Column::key('author_uri')->length(500),
            Column::key('author_photo_uri')->length(500),
            Column::key('publish_time')->datetime()->null(),
            Column::key('relative_time')->length(100),
            Column::key('maps_uri')->length(500),
        ];
    }

    public static function tablePseudos(): array
    {
        return [
            'ind_society_location' => ['index' => ['society_location_id']],
        ];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('society_location_id')->number()->decimals(0),
            Field::key('name')->text()->sanitize(false),
            Field::key('rating')->number()->decimals(0),
            Field::key('text')->text()->sanitize(false),
            Field::key('language')->text()->sanitize(false),
            Field::key('original_text')->text()->sanitize(false),
            Field::key('original_language')->text()->sanitize(false),
            Field::key('author_name')->text()->sanitize(false),
            Field::key('author_uri')->text()->sanitize(false),
            Field::key('author_photo_uri')->text()->sanitize(false),
            Field::key('publish_time')->text()->sanitize(false),
            Field::key('relative_time')->text()->sanitize(false),
            Field::key('maps_uri')->text()->sanitize(false),
        ];
    }
}
