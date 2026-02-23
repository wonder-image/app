<?php

    namespace Wonder\Data\Formatters;

    interface Formatter
    {

        public static function format( mixed $value): mixed;

    }