<?php

    namespace Wonder\Data\Formatters\Geo;

    use Wonder\App\Support\GoogleMaps;
    use Wonder\Data\Formatters\Formatter;

    /** Google Place ID valido o null. */
    class PlaceIdFormatter implements Formatter
    {

        public static function format(mixed $value): mixed
        {

            return GoogleMaps::cleanPlaceId($value);

        }

    }
