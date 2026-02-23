<?php

    namespace Wonder\Data\Formatters\String;

    use Wonder\Data\Formatters\Formatter;

    class TrimFormatter implements Formatter
    {

        public static function format(mixed $value): mixed
        {
            
            return is_string($value) ? trim($value) : $value;
        
        }

    }
