<?php

namespace Wonder\App\Support;

use finfo;

/**
 * Controllo puro di un singolo file di `$_FILES`: restituisce il codice
 * ALERT dell'errore o null se il file è accettabile.
 *
 * Non sposta file e non chiama `is_uploaded_file()`: lo fa l'handler.
 */
final class UploadValidator
{
    public const UPLOAD_FAILED = 920;
    public const EXTENSION = 921;
    public const SIZE = 922;
    public const MIME_MISMATCH = 927;
    /** Ridimensionamento fallito su un'immagine della whitelist (`IMAGE_TYPES`). */
    public const IMAGE_PROCESSING = 928;

    public const DEFAULT_MAX_SIZE = 10 * 1048576;

    /** Estensioni rifiutate quando il campo non dichiara una whitelist. */
    public const DENIED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
        'html', 'htm', 'xhtml', 'xht', 'shtml', 'mht', 'js', 'mjs',
        'svg', 'svgz', 'xml', 'xsl', 'xslt', 'cgi', 'pl', 'py', 'sh', 'htaccess',
    ];

    /** Rifiutate anche se dichiarate: il contenuto compresso non si può ripulire. */
    public const ALWAYS_DENIED_EXTENSIONS = ['svgz'];

    /** MIME che `finfo` riconosce come codice o markup eseguibile. */
    public const DANGEROUS_MIMES = [
        'text/x-php', 'application/x-php', 'application/x-httpd-php',
        'text/html', 'application/xhtml+xml',
        'application/javascript', 'text/javascript',
        'text/x-shellscript', 'application/x-sh',
    ];

    /** Altri formati con MIME noto oltre a `IMAGE_TYPES` e `FILE_TYPES`. */
    public const MEDIA_TYPES = [
        'gif' => ['image/gif'],
        'svg' => ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain'],
        'mp4' => ['video/mp4', 'application/mp4', 'video/x-m4v', 'video/quicktime'],
        'ico' => ['image/vnd.microsoft.icon', 'image/x-icon', 'image/ico'],
    ];

    /** Immagini dell'editor: estensione => MIME ammessi da `finfo`. */
    public const IMAGE_TYPES = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
    ];

    /** Allegati dell'editor: documenti comuni, niente eseguibili/HTML/SVG. */
    public const FILE_TYPES = [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        // Un csv che inizia con `#` è letto da finfo come sorgente C.
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'text/x-c', 'text/x-c++'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'odt' => ['application/vnd.oasis.opendocument.text', 'application/zip'],
        'ods' => ['application/vnd.oasis.opendocument.spreadsheet', 'application/zip'],
        'zip' => ['application/zip'],
    ];

    /**
     * @param mixed $file voce di `$_FILES` (una sola, non multipla)
     * @param array<string,list<string>> $types estensione => MIME ammessi
     */
    public static function validate(mixed $file, array $types, int $maxSize = self::DEFAULT_MAX_SIZE): ?int
    {
        if (!self::isSingleEnvelope($file)) {
            return self::UPLOAD_FAILED;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            return in_array((int) $file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? self::SIZE
                : self::UPLOAD_FAILED;
        }

        $extension = self::extension((string) $file['name']);

        if ($extension === '' || !array_key_exists($extension, $types)) {
            return self::EXTENSION;
        }

        $tmpName = (string) $file['tmp_name'];
        $size = is_file($tmpName) ? (int) filesize($tmpName) : -1;

        if ($size <= 0) {
            return self::UPLOAD_FAILED;
        }

        if ($size > $maxSize) {
            return self::SIZE;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName);

        if (!is_string($mime) || !in_array(strtolower($mime), array_map('strtolower', $types[$extension]), true)) {
            return self::MIME_MISMATCH;
        }

        return null;
    }

    /**
     * Estensione e contenuto di un file per `uploadFiles()`.
     *
     * Whitelist esplicita: l'estensione deve esserci. Whitelist vuota: vale
     * `DENIED_EXTENSIONS`. Poi `finfo`: per le estensioni note il MIME deve
     * corrispondere, per le altre si rifiuta solo codice o markup.
     *
     * @param mixed $extensions lista o stringa separata da virgole
     */
    public static function checkFormat(string $extension, string $tmpPath, mixed $extensions = []): ?int
    {
        $extension = strtolower($extension);
        $allowed = self::normalizeExtensions($extensions);

        // Niente trim: `svg ` deve essere rifiutato, non salvato così.
        if (preg_match('/^[a-z0-9]+$/D', $extension) !== 1) {
            return self::EXTENSION;
        }

        if (in_array($extension, self::ALWAYS_DENIED_EXTENSIONS, true)) {
            return self::EXTENSION;
        }

        if ($allowed !== []) {
            if ($extension === '' || !in_array($extension, $allowed, true)) {
                return self::EXTENSION;
            }
        } elseif ($extension === '' || in_array($extension, self::DENIED_EXTENSIONS, true)) {
            return self::EXTENSION;
        }

        if (!is_file($tmpPath)) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        $mime = is_string($mime) ? strtolower($mime) : '';
        $known = self::IMAGE_TYPES + self::FILE_TYPES + self::MEDIA_TYPES;

        if (isset($known[$extension])) {
            return in_array($mime, array_map('strtolower', $known[$extension]), true) ? null : self::MIME_MISMATCH;
        }

        return in_array($mime, self::DANGEROUS_MIMES, true) ? self::MIME_MISMATCH : null;
    }

    /** @return list<string> */
    public static function normalizeExtensions(mixed $extensions): array
    {
        if (is_string($extensions)) {
            $extensions = explode(',', $extensions);
        }

        if (!is_array($extensions)) {
            return [];
        }

        $normalized = [];

        foreach ($extensions as $extension) {
            if (is_scalar($extension) && ($extension = strtolower(trim((string) $extension, " .\t"))) !== '') {
                $normalized[] = $extension;
            }
        }

        return array_values(array_unique($normalized));
    }

    public static function extension(string $name): string
    {
        return strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    }

    private static function isSingleEnvelope(mixed $file): bool
    {
        if (!is_array($file)) {
            return false;
        }

        foreach (['name', 'tmp_name', 'error', 'size'] as $key) {
            if (!array_key_exists($key, $file) || !is_scalar($file[$key])) {
                return false;
            }
        }

        return true;
    }
}
