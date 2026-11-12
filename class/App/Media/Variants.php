<?php

namespace Wonder\App\Media;

use Wonder\App\Support\Asset;
use Wonder\App\Support\MediaFileManager;

/**
 * Anteprime del backend mentre le misure sono in coda: una misura che non è
 * ancora su disco lascia il posto all'originale. Quando non si può sapere
 * (URL esterno, originale non locale) resta tutto com'era.
 */
final class Variants
{
    /** `$variant`, oppure `$original` se la misura manca e l'originale locale c'è. */
    public static function url(string $variant, string $original, ?string $root = null, ?string $appUrl = null): string
    {
        return Asset::path($variant, $root, $appUrl) === null && Asset::path($original, $root, $appUrl) !== null
            ? $original
            : $variant;
    }

    /**
     * Il suffisso di larghezza (`240`) con cui il backend compone
     * `nome-240.ext`: resta se ogni file di `$files` ha quella misura in
     * `$directoryUrl`, altrimenti `''`, cioè gli originali.
     */
    public static function suffix(string $directoryUrl, mixed $files, string $suffix, ?string $root = null, ?string $appUrl = null): string
    {
        if ($suffix === '') {
            return '';
        }

        $directoryUrl = rtrim($directoryUrl, '/').'/';

        foreach (MediaFileManager::decodeStoredFiles($files) as $file) {
            $original = $directoryUrl.$file;
            $variant = $directoryUrl.pathinfo($file, PATHINFO_FILENAME).'-'.ltrim($suffix, '-').'.'.pathinfo($file, PATHINFO_EXTENSION);

            if (self::url($variant, $original, $root, $appUrl) === $original) {
                return '';
            }
        }

        return $suffix;
    }
}
