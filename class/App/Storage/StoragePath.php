<?php

namespace Wonder\App\Storage;

/** Percorsi relativi di uno storage: normalizzazione e controlli. */
final class StoragePath
{
    /**
     * `'/images//a.jpg'` → `'images/a.jpg'`. Lancia `StorageException` se il
     * percorso contiene `..`, `.`, byte nullo o backslash.
     */
    public static function normalize(string $path): string
    {
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new StorageException('Percorso non valido.');
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '') {
                continue;
            }

            if ($segment === '.' || $segment === '..') {
                throw new StorageException('Percorso non valido.');
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /** Come `normalize()` ma vieta il percorso vuoto (la radice). */
    public static function file(string $path): string
    {
        $path = self::normalize($path);

        if ($path === '') {
            throw new StorageException('Percorso del file mancante.');
        }

        return $path;
    }

    /** Unisce due percorsi già relativi. */
    public static function join(string ...$parts): string
    {
        return self::normalize(implode('/', $parts));
    }
}
