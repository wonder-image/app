<?php

namespace Wonder\App\Support;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Porta un file appena caricato nel formato che il campo ha dichiarato.
 *
 * Serve ai campi che accettano più estensioni ma ne vogliono conservare una
 * sola: l'icona app si carica anche in JPG, però su disco resta un PNG, così
 * i `<link rel="apple-touch-icon">` e le misure generate accanto
 * all'originale puntano sempre alla stessa estensione.
 *
 * La conversione è un passaggio dell'upload, non un servizio a sé: si
 * dichiara con `Wonder\Data\Fields\Image::convertTo()` e la esegue
 * `uploadFiles()` subito dopo aver spostato il file.
 */
final class ImageConverter
{
    /** Formati che sappiamo scrivere. */
    private const WRITABLE = ['png', 'jpg', 'webp'];

    /** Estensioni che GD sa leggere e che quindi possiamo convertire. */
    private const READABLE = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp'];

    /**
     * Normalizza il formato dichiarato dal campo: stringa vuota quando non
     * sappiamo scriverlo, così chi chiama può semplicemente saltare il passo.
     */
    public static function normalizeFormat(mixed $format): string
    {
        $format = strtolower(trim((string) $format));

        if ($format === 'jpeg') {
            $format = 'jpg';
        }

        return in_array($format, self::WRITABLE, true) ? $format : '';
    }

    /**
     * Converte `$imagePath` e restituisce il percorso del file da registrare.
     *
     * Ritorna `null` solo quando la conversione è fallita davvero: in quel
     * caso l'originale è ancora al suo posto e sta a chi chiama decidere se
     * tenerlo o cancellarlo. Un file già nel formato richiesto torna
     * invariato, senza riscritture inutili.
     */
    public static function convert(string $imagePath, string $format, int $quality = 90): ?string
    {
        $format = self::normalizeFormat($format);

        if ($format === '' || !is_file($imagePath)) {
            return null;
        }

        $extension = strtolower((string) pathinfo($imagePath, PATHINFO_EXTENSION));

        if (!in_array($extension, self::READABLE, true)) {
            return null;
        }

        if ($extension === $format || ($format === 'jpg' && $extension === 'jpeg')) {
            return $imagePath;
        }

        $targetPath = self::replaceExtension($imagePath, $format);

        try {

            $image = (new ImageManager(new Driver()))->read($imagePath);

            $encoded = match ($format) {
                'png' => $image->toPng(),
                'jpg' => $image->toJpeg($quality),
                'webp' => $image->toWebp($quality),
            };

            $encoded->save($targetPath);

        } catch (Throwable) {

            return null;

        }

        if (!is_file($targetPath)) {
            return null;
        }

        if ($targetPath !== $imagePath) {
            @unlink($imagePath);
        }

        return $targetPath;
    }

    private static function replaceExtension(string $path, string $extension): string
    {
        $directory = rtrim((string) pathinfo($path, PATHINFO_DIRNAME), '/');
        $name = (string) pathinfo($path, PATHINFO_FILENAME);

        return ($directory !== '' ? $directory.'/' : '').$name.'.'.$extension;
    }
}
