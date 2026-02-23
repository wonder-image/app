<?php

    namespace Wonder\Data\Formatters\String;

    use Wonder\Data\Formatters\Formatter;
    use Wonder\Support\Text\Slug;

    class SlugFormatter implements Formatter
    {

        public static function format(mixed $value): mixed
        {

            if (!is_string($value)) {
                return $value;
            }

            return str_replace('_', '-', Slug::make($value));

        }

    }
