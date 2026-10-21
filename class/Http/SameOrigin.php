<?php

namespace Wonder\Http;

/**
 * Verifica che una richiesta arrivi da una pagina dello stesso sito.
 *
 * Con `Sec-Fetch-Site` (browser recenti) vale solo `same-origin`; senza,
 * l'header `Origin` deve avere lo stesso host (e porta) della richiesta.
 * Senza nessuno dei due la richiesta è rifiutata.
 */
final class SameOrigin
{
    public static function check(?array $server = null): bool
    {
        $server = $server ?? $_SERVER;

        $fetchSite = strtolower(trim((string) ($server['HTTP_SEC_FETCH_SITE'] ?? '')));
        if ($fetchSite !== '') {
            return $fetchSite === 'same-origin';
        }

        $origin = trim((string) ($server['HTTP_ORIGIN'] ?? ''));
        if ($origin === '' || strtolower($origin) === 'null') {
            return false;
        }

        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = parse_url($origin, PHP_URL_HOST);
        if (!is_string($scheme) || !is_string($host) || $host === '') {
            return false;
        }

        $port = parse_url($origin, PHP_URL_PORT);
        $originHost = self::hostPort(strtolower($host), is_int($port) ? $port : null, strtolower($scheme));

        $requestHost = strtolower(UrlParser::requestHost($server));
        $requestScheme = UrlParser::isHttpsRequest($server) ? 'https' : 'http';
        $parsed = parse_url('//'.$requestHost);
        if (!is_array($parsed) || !isset($parsed['host'])) {
            return false;
        }

        $expected = self::hostPort((string) $parsed['host'], $parsed['port'] ?? null, $requestScheme);

        return hash_equals($expected, $originHost);
    }

    private static function hostPort(string $host, ?int $port, string $scheme): string
    {
        $default = $scheme === 'https' ? 443 : 80;

        return $port === null || $port === $default ? $host : $host.':'.$port;
    }
}
