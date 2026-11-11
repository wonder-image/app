<?php

namespace Wonder\App\Media;

use InvalidArgumentException;

/**
 * I tipi della libreria Media, in un posto solo: estensioni ammesse,
 * cartella sotto `assets/upload`, tipo di input (`accept`) e peso massimo.
 * Le Resource di Media, l'upload di massa e la migrazione delle icone
 * leggono da qui. Solo dati: nessuna query, nessun file.
 */
final class MediaTypes
{
    public const IMAGE = 'image';
    public const ICON = 'icon';
    public const DOCUMENT = 'document';
    public const VIDEO = 'video';

    /** Solo le immagini raster hanno le misure responsive e il webp. */
    private const TYPES = [
        self::IMAGE => [
            'extensions' => ['png', 'jpg', 'jpeg', 'webp'],
            'folder' => 'images',
            'accept' => 'image',
            'max_size' => 5,
            'resize' => true,
        ],
        self::ICON => [
            'extensions' => ['svg'],
            'folder' => 'icons',
            'accept' => 'svg',
            'max_size' => 5,
            'resize' => false,
        ],
        self::DOCUMENT => [
            'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods'],
            'folder' => 'documents',
            'accept' => 'document',
            'max_size' => 5,
            'resize' => false,
        ],
        self::VIDEO => [
            'extensions' => ['mp4', 'webm', 'mov'],
            'folder' => 'videos',
            'accept' => 'media-video',
            'max_size' => 100,
            'resize' => false,
        ],
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::TYPES);
    }

    public static function has(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }

    /** @return list<string> estensioni ammesse, minuscole e senza punto */
    public static function extensions(string $type): array
    {
        return self::definition($type)['extensions'];
    }

    /** Cartella sotto `assets/upload`. */
    public static function folder(string $type): string
    {
        return self::definition($type)['folder'];
    }

    /** Tipo dell'input file (`FormField::fileDragDrop($accept)`). */
    public static function accept(string $type): string
    {
        return self::definition($type)['accept'];
    }

    /** Peso massimo di un file, in MB. */
    public static function maxSize(string $type): int
    {
        return self::definition($type)['max_size'];
    }

    /** Vero se il tipo genera misure responsive e webp. */
    public static function resizes(string $type): bool
    {
        return self::definition($type)['resize'];
    }

    /** Vero se il file (nome o percorso) ha un'estensione ammessa dal tipo. */
    public static function accepts(string $type, string $file): bool
    {
        return in_array(self::extension($file), self::extensions($type), true);
    }

    public static function extension(string $file): string
    {
        return strtolower((string) pathinfo(trim($file), PATHINFO_EXTENSION));
    }

    /** @return array{extensions: list<string>, folder: string, accept: string, max_size: int, resize: bool} */
    private static function definition(string $type): array
    {
        return self::TYPES[$type] ?? throw new InvalidArgumentException("Tipo di media sconosciuto: {$type}");
    }
}
