<?php

namespace Wonder\App\Media;

/**
 * Il file caricato da un campo media, nella forma che si aspetta
 * `uploadFiles()`: una voce di `$_FILES` con un solo file e le chiavi a
 * lista. Il campo del form lo manda come file singolo, le API anche come
 * lista: qui diventano uguali.
 */
final class MediaUpload
{
    private const KEYS = ['name', 'type', 'tmp_name', 'error', 'size'];

    /**
     * @return array<string, list<mixed>>|null null se `$value` non è un file caricato
     */
    public static function from(mixed $value): ?array
    {
        if (!is_array($value) || !isset($value['tmp_name'])) {
            return null;
        }

        $names = (array) $value['tmp_name'];

        foreach ($names as $index => $temporary) {
            if (!is_string($temporary) || trim($temporary) === '') {
                continue;
            }

            $upload = [];

            foreach (self::KEYS as $key) {
                $entry = $value[$key] ?? null;
                $upload[$key] = [is_array($entry) ? ($entry[$index] ?? null) : $entry];
            }

            return $upload;
        }

        return null;
    }

    /**
     * Tutti i file di una voce di `$_FILES`, per posizione: un file
     * arrivato diventa una voce come quella di `from()`, uno perso `false`.
     * Le posizioni senza file restano fuori.
     *
     * @return array<int, array<string, list<mixed>>|false>
     */
    public static function split(mixed $value): array
    {
        if (!is_array($value) || !isset($value['error'])) {
            return [];
        }

        $uploads = [];

        foreach ((array) $value['error'] as $index => $error) {
            $error = is_numeric($error) ? (int) $error : UPLOAD_ERR_NO_FILE;

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $upload = [];

            foreach (self::KEYS as $key) {
                $entry = $value[$key] ?? null;
                $upload[$key] = [is_array($entry) ? ($entry[$index] ?? null) : $entry];
            }

            $temporary = $upload['tmp_name'][0];
            $uploads[(int) $index] = $error === UPLOAD_ERR_OK && is_string($temporary) && trim($temporary) !== ''
                ? $upload
                : false;
        }

        return $uploads;
    }

    /**
     * Vero se il browser ha provato a mandare un file che PHP non ha
     * ricevuto (troppo pesante, caricamento interrotto): non è "nessun file".
     */
    public static function lost(mixed $value): bool
    {
        if (!is_array($value) || !isset($value['error'])) {
            return false;
        }

        foreach ((array) $value['error'] as $error) {
            if (is_numeric($error) && !in_array((int) $error, [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE], true)) {
                return true;
            }
        }

        return false;
    }

    /** Il nome del file come l'ha mandato il browser, senza cartelle. */
    public static function fileName(array $upload): string
    {
        $name = $upload['name'] ?? '';
        $name = is_array($name) ? (string) (reset($name) ?: '') : (string) $name;

        return basename(str_replace('\\', '/', trim($name)));
    }

    /** Il nome senza estensione, leggibile: è il nome del media in libreria. */
    public static function title(array $upload): string
    {
        $title = (string) pathinfo(self::fileName($upload), PATHINFO_FILENAME);

        return trim((string) preg_replace('/\s+/', ' ', str_replace(['_', '-'], ' ', $title)));
    }
}
