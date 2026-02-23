<?php

namespace Wonder\Support\Text;

class Slug
{
    private const SEPARATOR = '_';

    /**
     * Normalizza una stringa riutilizzabile in chiavi/id.
     */
    public static function make(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $transliterated = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value)
            : false;

        if (is_string($transliterated) && $transliterated !== '') {
            $value = $transliterated;
        }

        $value = strtolower($value);
        $value = preg_replace('/\s+/', self::SEPARATOR, $value) ?? '';
        $value = preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';

        if (self::SEPARATOR === '_') {
            $value = str_replace('-', '_', $value);
        } else {
            $value = str_replace('_', '-', $value);
        }

        $quotedSeparator = preg_quote(self::SEPARATOR, '/');
        $value = preg_replace('/' . $quotedSeparator . '+/', self::SEPARATOR, $value) ?? '';

        return trim($value, self::SEPARATOR);
    }
}
