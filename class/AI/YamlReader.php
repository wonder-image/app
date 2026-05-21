<?php

namespace Wonder\AI;

use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Thin wrapper su `Symfony\Component\Yaml\Yaml::parseFile()` con:
 *
 * - cache statica per evitare di rileggere/parsare gli stessi file
 *   durante una singola request CLI / HTTP;
 * - error handling uniforme: file mancante → `null` (chiamante decide
 *   il fallback), parse error → `RuntimeException` con file+messaggio
 *   (fail-fast: un YAML rotto va corretto, non ignorato);
 * - flag `PARSE_EXCEPTION_ON_INVALID_TYPE` per pescare subito tipi
 *   ambigui (es. `temperature: yes` interpretato come bool true).
 *
 * Usato da `ConfigLoader`, `AgentResolver`, eventuali altri consumer
 * di YAML nel framework. Non esponiamo l'SDK Symfony direttamente per
 * poter cambiare parser in futuro senza touch sui call site.
 */
final class YamlReader
{
    /** @var array<string,mixed> path assoluto → contenuto parsato */
    private static array $cache = [];

    /**
     * Resetta la cache. Pensato per test o per script CLI long-running
     * che riconfigurano il filesystem in corsa.
     */
    public static function reset(): void
    {
        self::$cache = [];
    }

    /**
     * Legge e parsa un file YAML.
     *
     * @param string $path Path assoluto al file `.yml` / `.yaml`.
     * @return array<string,mixed>|null Contenuto parsato come array
     *   associativo, oppure `null` se il file non esiste (il caller
     *   tipicamente vuole degradare a default invece di crashare).
     *
     * @throws RuntimeException Se il file esiste ma il YAML è invalido,
     *   oppure se contiene un valore non-array al root (es. una stringa
     *   o un null) che non rappresenta una mappa di settings.
     */
    public static function parseFile(string $path): ?array
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (array_key_exists($path, self::$cache)) {
            return self::$cache[$path];
        }

        if (!is_file($path)) {
            self::$cache[$path] = null;
            return null;
        }

        try {
            $parsed = Yaml::parseFile($path, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $e) {
            throw new RuntimeException(
                'YAML invalido in '.$path.': '.$e->getMessage(),
                0,
                $e
            );
        }

        if ($parsed === null) {
            // File vuoto. Lecito (commento di placeholder) → array vuoto.
            self::$cache[$path] = [];
            return [];
        }

        if (!is_array($parsed)) {
            throw new RuntimeException(
                'YAML in '.$path.' deve restituire una mappa (array associativo), ricevuto: '.gettype($parsed)
            );
        }

        self::$cache[$path] = $parsed;
        return $parsed;
    }
}
