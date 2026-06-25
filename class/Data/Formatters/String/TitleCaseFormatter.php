<?php

    namespace Wonder\Data\Formatters\String;

    use Wonder\Data\Formatters\Formatter;

    class TitleCaseFormatter implements Formatter
    {

        public static function format(mixed $value): mixed
        {
            
            return is_string($value) ? mb_convert_case($value, MB_CASE_TITLE) : $value;
        
        }

    }
