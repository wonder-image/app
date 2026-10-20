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
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
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
