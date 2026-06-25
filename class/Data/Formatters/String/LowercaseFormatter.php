<?php

    namespace Wonder\Data\Formatters\String;

    use Wonder\Data\Formatters\Formatter;

    class LowercaseFormatter implements Formatter
    {

        public static function format(mixed $value): mixed
        {
            
            return is_string($value) ? mb_strtolower($value) : $value;
        
        }

    }
