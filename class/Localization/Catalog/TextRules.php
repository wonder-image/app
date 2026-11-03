<?php

namespace Wonder\Localization\Catalog;

/**
 * Controlli su una traduzione rispetto al suo testo sorgente: stessi
 * `{{placeholder}}` e stessi tag HTML (nomi, con le ripetizioni). L'ordine
 * può cambiare, perché una lingua può spostare un link nella frase.
 */
final class TextRules
{
    /** @return list<string> */
    public static function errors(string $source, string $target): array
    {
        $errors = [];

        if (self::placeholders($source) !== self::placeholders($target)) {
            $errors[] = 'I segnaposto {{...}} non coincidono con la sorgente.';
        }

        if (self::tags($source) !== self::tags($target)) {
            $errors[] = 'I tag HTML non coincidono con la sorgente.';
        }

        return $errors;
    }

    /** @return list<string> */
    public static function placeholders(string $text): array
    {
        preg_match_all('/\{\{\s*([A-Za-z0-9_.-]+)\s*\}\}/', $text, $matches);
        $names = array_values(array_unique($matches[1]));
        sort($names);

        return $names;
    }

    /** @return list<string> */
    public static function tags(string $text): array
    {
        preg_match_all('/<\s*(\/?)\s*([A-Za-z][A-Za-z0-9-]*)/', $text, $matches, PREG_SET_ORDER);
        $tags = array_map(static fn (array $m): string => $m[1].strtolower($m[2]), $matches);
        sort($tags);

        return $tags;
    }
}
