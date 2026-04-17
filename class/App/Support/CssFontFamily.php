<?php

namespace Wonder\App\Support;

final class CssFontFamily
{
    public static function fallback(): string
    {
        return '"Roboto", sans-serif';
    }

    public static function normalize(mixed $value, string $fallback = ''): string
    {
        $fontFamily = trim((string) $value);

        if ($fontFamily === '') {
            return $fallback;
        }

        $fontFamily = stripslashes($fontFamily);
        $fontFamily = html_entity_decode($fontFamily, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $fontFamily = preg_replace(
            "/^'([^']+)'(\\s*,.*)?$/",
            '"$1"$2',
            $fontFamily
        ) ?? $fontFamily;

        return trim($fontFamily) !== '' ? trim($fontFamily) : $fallback;
    }
}
