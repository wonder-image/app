<?php

namespace Wonder\Support\Text;

class Slug
{
    private const SEPARATOR = '_';

    /**
     * Translitterazione deterministica dei diacritici più comuni, applicata
     * prima di iconv così che l'output non dipenda dal locale/piattaforma su
     * cui gira iconv (con `//TRANSLIT` l'esito varia tra sistemi e con
     * `//IGNORE` i caratteri accentati possono venire scartati del tutto).
     */
    private const TRANSLITERATIONS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss',
    ];

    /**
     * Normalizza una stringa riutilizzabile in chiavi/id.
     */
    public static function make(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // Minuscole unicode-aware + translitterazione deterministica dei
        // diacritici comuni, PRIMA di iconv: garantisce lo stesso output su
        // qualsiasi locale/piattaforma.
        $value = self::lowercase($value);
        $value = strtr($value, self::TRANSLITERATIONS);

        // Diacritici residui (meno comuni): tentativo con iconv, che dipende
        // dalla piattaforma; i caratteri non convertibili vengono poi scartati.
        $transliterated = function_exists('iconv')
            ? @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value)
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

    /**
     * Minuscole indipendenti dal locale: usa mbstring quando disponibile
     * (gestisce i diacritici unicode), con fallback su strtolower.
     */
    private static function lowercase(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
