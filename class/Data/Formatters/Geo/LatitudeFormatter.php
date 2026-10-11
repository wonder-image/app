<?php

    namespace Wonder\Data\Formatters\Geo;

    use Wonder\App\Support\GoogleMaps;
    use Wonder\Data\Formatters\Formatter;

    /** Latitudine tra -90 e 90 o null. */
    class LatitudeFormatter implements Formatter
    {

        public static function format(mixed $value): mixed
        {

            return GoogleMaps::cleanLat($value);

        }

    }
