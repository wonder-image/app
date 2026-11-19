<?php

namespace Wonder\App\Deploy;

use RuntimeException;

/**
 * Manifesto di una release: `deploy-manifest.json` nella radice della release.
 *
 * `forge deploy:prepare` lo scrive per ultimo: una cartella senza manifesto è una
 * preparazione interrotta e `forge deploy:activate` la rifiuta.
 * Contiene solo metadati (nessun valore di configurazione o segreto).
 */
final class ReleaseManifest
{
    public const FILE = 'deploy-manifest.json';
    public const FORMAT = 1;

    /** Lunghezza massima = colonna `release_id` di `app_update_runs`. */
    private const ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/';

    public static function validId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1 && !str_contains($id, '..');
    }

    /** @param array<string, mixed> $extra */
    public static function write(string $dir, string $releaseId, array $extra = []): array
    {
        if (!self::validId($releaseId)) {
            throw new RuntimeException('Identificativo di release non valido (lettere, cifre, punto, trattino, massimo 100 caratteri).');
        }

        $manifest = array_merge([
            'format' => self::FORMAT,
            'release' => $releaseId,
            'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
        ], $extra);

        $path = rtrim($dir, '/').'/'.self::FILE;
        $tmp = $path.'.tmp-'.getmypid();
        $json = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false || file_put_contents($tmp, $json.PHP_EOL) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Impossibile scrivere '.$path.'.');
        }

        return $manifest;
    }

    /** @return array<string, mixed> */
    public static function read(string $dir): array
    {
        $path = rtrim($dir, '/').'/'.self::FILE;

        if (!is_file($path)) {
            throw new RuntimeException('Manca '.self::FILE.' in '.$dir.': la preparazione non è finita o la cartella non è una release.');
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || !self::validId((string) ($manifest['release'] ?? ''))) {
            throw new RuntimeException(self::FILE.' non valido in '.$dir.'.');
        }

        return $manifest;
    }
}
