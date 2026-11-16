<?php

namespace Wonder\App\Media;

use Throwable;

/**
 * I file che esistono su disco per un media: l'originale e, per le
 * immagini, le misure (`nome-240.jpg`) e i webp (`nome.webp`,
 * `nome-240.webp`). Li legge dalla cartella, non dalla configurazione:
 * le misure di una configurazione precedente restano scaricabili.
 *
 * Una misura che il config non conosce conta solo se l'immagine è larga
 * proprio quanto dice il nome: `foto-2024.jpg` di un altro media non
 * diventa una misura di `foto.jpg`.
 */
final class MediaDownloads
{
    /**
     * @return array{
     *     groups: list<array{label: string, width: ?int, files: list<array{name: string, format: string, bytes: int, url: string, path: string, width: ?int, height: ?int}>}>,
     *     pending: bool,
     *     error: ?string
     * }
     *         `groups`: l'originale per primo, poi le misure dalla più piccola
     */
    public static function of(MediaFile $media, ?string $root = null): array
    {
        $empty = ['groups' => [], 'pending' => false, 'error' => null];
        $root ??= ResizeQueue::root();

        if ($root === '' || $media->file === '' || basename($media->file) !== $media->file) {
            return $empty;
        }

        $directory = rtrim($root, '/').'/'.ResizeQueue::UPLOAD.MediaTypes::folder($media->type).'/';
        $url = MediaFile::directoryUrl($media->type);
        $original = self::file($directory, $url, $media->file);

        if ($original === null) {
            return $empty;
        }

        $groups = [['label' => 'Originale', 'width' => null, 'files' => [$original]]];
        $name = pathinfo($media->file, PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($media->file, PATHINFO_EXTENSION));

        if ($media->isImage()) {
            if ($extension !== 'webp' && ($webp = self::file($directory, $url, $name.'.webp')) !== null) {
                $groups[0]['files'][] = $webp;
            }

            foreach (self::widths($directory, $name, $extension) as $width) {
                $files = array_values(array_filter([
                    self::file($directory, $url, $name.'-'.$width.'.'.$extension),
                    $extension !== 'webp' ? self::file($directory, $url, $name.'-'.$width.'.webp') : null,
                ]));

                if ($files !== []) {
                    $groups[] = ['label' => $width.' px', 'width' => $width, 'files' => $files];
                }
            }
        }

        $pending = false;
        $error = null;

        try {
            $status = ResizeQueue::status($directory.$media->file);
            $pending = $status === ResizeQueue::PENDING;
            $error = $status === ResizeQueue::FAILED ? (ResizeQueue::error($directory.$media->file) ?? '') : null;
        } catch (Throwable) {
            // Coda non ancora creata: niente stato.
        }

        return ['groups' => $groups, 'pending' => $pending, 'error' => $error];
    }

    /** Tutti i file, in ordine, per lo zip. */
    public static function files(array $downloads): array
    {
        $files = [];

        foreach ($downloads['groups'] ?? [] as $group) {
            array_push($files, ...$group['files']);
        }

        return $files;
    }

    /** Peso leggibile: `850 B`, `38 KB`, `1,2 MB`. */
    public static function size(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return str_replace('.', ',', (string) round($bytes / 1024 / 1024, 1)).' MB';
    }

    /** @return list<int> le larghezze, dalla più piccola */
    private static function widths(string $directory, string $name, string $extension): array
    {
        $configured = defined('RESPONSIVE_IMAGE_SIZES') && is_array(RESPONSIVE_IMAGE_SIZES)
            ? array_map('intval', RESPONSIVE_IMAGE_SIZES)
            : [];
        $widths = [];
        $pattern = '/^'.preg_quote($name, '/').'-([1-9][0-9]{0,4})\.(?:'.preg_quote($extension, '/').'|webp)$/';

        foreach (scandir($directory) ?: [] as $file) {
            if (preg_match($pattern, $file, $match) !== 1) {
                continue;
            }

            $width = (int) $match[1];

            if (in_array($width, $configured, true) || (int) (@getimagesize($directory.$file)[0] ?? 0) === $width) {
                $widths[$width] = $width;
            }
        }

        sort($widths);

        return array_values($widths);
    }

    private static function file(string $directory, string $url, string $file): ?array
    {
        $path = $directory.$file;

        if (!is_file($path)) {
            return null;
        }

        $size = @getimagesize($path);

        return [
            'name' => $file,
            'format' => strtoupper(pathinfo($file, PATHINFO_EXTENSION)),
            'bytes' => (int) filesize($path),
            'url' => $url.rawurlencode($file),
            'path' => $path,
            'width' => is_array($size) ? (int) $size[0] : null,
            'height' => is_array($size) ? (int) $size[1] : null,
        ];
    }
}
