<?php

    namespace Wonder\Data\Formatters\Geo;

    use Wonder\App\Support\GoogleMaps;
    use Wonder\Data\Formatters\Formatter;

    /** Longitudine tra -180 e 180 o null. */
    class LongitudeFormatter implements Formatter
    {

        public static function format(mixed $value): mixed
        {

            return GoogleMaps::cleanLng($value);

        }

    }
