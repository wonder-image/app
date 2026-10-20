<?php

namespace Wonder\App\Support;

/**
 * Sottocartella di upload scelta dal client (header `Dir` dell'editor).
 *
 * `normalize()` accetta solo percorsi relativi fatti di segmenti semplici;
 * `ensure()` crea la cartella e verifica con `realpath` che resti dentro la
 * radice degli upload.
 */
final class UploadDir
{
    /**
     * `''` → `'/'`, `'blog/2026'` → `'/blog/2026/'`; null se il percorso è
     * assoluto, contiene `..`, `.`, `\0`, backslash o caratteri non ammessi.
     */
    public static function normalize(string $dir): ?string
    {
        if ($dir === '') {
            return '/';
        }

        if (str_contains($dir, "\0") || str_contains($dir, '\\') || str_starts_with($dir, '/')) {
            return null;
        }

        $segments = explode('/', rtrim($dir, '/'));

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }

            if (!preg_match('/^[A-Za-z0-9._-]+\z/', $segment)) {
                return null;
            }
        }

        return '/'.implode('/', $segments).'/';
    }

    /**
     * Crea `$base.$relative` (già normalizzato) e ne restituisce il percorso
     * reale con `/` finale, o null se esce dalla radice o non si può creare.
     */
    public static function ensure(string $base, string $relative, int $mode = 0775): ?string
    {
        $root = realpath($base);

        if ($root === false || !is_dir($root)) {
            return null;
        }

        $target = $root.$relative;

        if (!is_dir($target) && !@mkdir($target, $mode, true) && !is_dir($target)) {
            return null;
        }

        $real = realpath($target);

        if ($real === false || ($real !== $root && !str_starts_with($real, $root.'/'))) {
            return null;
        }

        return rtrim($real, '/').'/';
    }

    /** Nome casuale (minuscole e cifre) da `random_int`. */
    public static function randomName(int $length = 16): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $name = '';

        for ($i = 0; $i < $length; $i++) {
            $name .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $name;
    }
}
