<?php

namespace Wonder\App\Support;

final class MediaFileManager
{
    public static function hasUploadedFiles(mixed $files): bool
    {
        if (!is_array($files) || !isset($files['tmp_name'])) {
            return false;
        }

        $tmpNames = is_array($files['tmp_name'])
            ? $files['tmp_name']
            : [$files['tmp_name']];

        foreach ($tmpNames as $tmpName) {
            if (is_string($tmpName) && trim($tmpName) !== '') {
                return true;
            }
        }

        return false;
    }

    public static function decodeStoredFiles(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return [];
        }

        $files = [];

        foreach ($value as $file) {
            if (!is_string($file)) {
                continue;
            }

            $file = trim($file);

            if ($file === '') {
                continue;
            }

            $files[] = $file;
        }

        return array_values($files);
    }

    public static function deleteFiles(string $pathDir, array $format, array $storedFiles): void
    {
        foreach (self::decodeStoredFiles($storedFiles) as $storedFile) {
            self::deleteFile($pathDir, $format, $storedFile);
        }
    }

    public static function deleteFile(string $pathDir, array $format, string $storedFile): void
    {
        $storedFile = trim($storedFile);

        if ($storedFile === '') {
            return;
        }

        $dir = (string) ($format['dir'] ?? '/');
        $pathDir = rtrim($pathDir, '/');

        if (substr($dir, -1) !== '/') {
            $dirParts = explode('/', trim($dir, '/'));
            $targetName = (string) array_pop($dirParts);
            $relativeDir = implode('/', $dirParts);
            $baseDir = $pathDir.($relativeDir !== '' ? '/'.$relativeDir : '');
            $extension = strtolower((string) pathinfo($storedFile, PATHINFO_EXTENSION));
            $fileName = $targetName.($extension !== '' ? '.'.$extension : '');
        } else {
            $relativeDir = trim($dir, '/');
            $baseDir = $pathDir.($relativeDir !== '' ? '/'.$relativeDir : '');
            $fileName = $storedFile;
        }

        $baseDir = rtrim($baseDir, '/');

        self::deletePath($baseDir.'/'.$fileName);
        self::deleteDerivedFiles($baseDir, $fileName, $format);
    }

    private static function deleteDerivedFiles(string $baseDir, string $fileName, array $format): void
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
        $name = (string) pathinfo($fileName, PATHINFO_FILENAME);

        if (($format['webp'] ?? false) === true && $extension !== 'webp') {
            self::deletePath($baseDir.'/'.$name.'.webp');
        }

        foreach (self::resizeWidths($format['resize'] ?? null) as $width) {
            self::deletePath($baseDir.'/'.$name.'-'.$width.'.'.$extension);

            if (($format['webp'] ?? false) === true) {
                self::deletePath($baseDir.'/'.$name.'-'.$width.'.webp');
            }
        }

        foreach (self::resizeDimensions($format['resize'] ?? null) as [$width, $height]) {
            self::deletePath($baseDir.'/'.$width.'x'.$height.'-'.$fileName);
        }
    }

    private static function resizeWidths(mixed $resize): array
    {
        if (!is_array($resize)) {
            return [];
        }

        if (isset($resize['width']) && is_numeric($resize['width'])) {
            return [(int) $resize['width']];
        }

        $widths = [];

        foreach ($resize as $entry) {
            if (is_array($entry) && isset($entry['width']) && is_numeric($entry['width'])) {
                $widths[] = (int) $entry['width'];
                continue;
            }

            if (is_numeric($entry)) {
                $widths[] = (int) $entry;
            }
        }

        return array_values(array_unique(array_filter($widths, static fn (int $width): bool => $width > 0)));
    }

    private static function resizeDimensions(mixed $resize): array
    {
        if (!is_array($resize)) {
            return [];
        }

        if (isset($resize['width']) && is_numeric($resize['width'])) {
            $width = (int) $resize['width'];
            $height = (int) ($resize['height'] ?? $resize['width']);

            return [[$width, $height]];
        }

        $dimensions = [];

        foreach ($resize as $entry) {
            if (!is_array($entry) || !isset($entry['width']) || !is_numeric($entry['width'])) {
                continue;
            }

            $width = (int) $entry['width'];
            $height = (int) ($entry['height'] ?? $entry['width']);
            $dimensions[] = [$width, $height];
        }

        return $dimensions;
    }

    private static function deletePath(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        @unlink($path);
    }
}
