<?php

    namespace Wonder\Data\Formatters\String;

    use Wonder\Data\Formatters\Formatter;
    use Wonder\Support\Html\SafeHtml;

    /**
     * Pulisce l'HTML di un testo formattato con la whitelist di `SafeHtml`.
     * Lo aggiunge `Field::richText()`: gira in `Field::format()`, quindi anche
     * in `Model::create()`/`Model::update()`. Il salvataggio dal backend
     * (`formToArray()`) fa la stessa pulizia leggendo `format['rich_text']`.
     */
    class RichTextFormatter implements Formatter
    {

        public static function format(mixed $value): mixed
        {

            return is_string($value) ? SafeHtml::clean($value) : $value;

        }

    }
