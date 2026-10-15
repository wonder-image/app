<?php

namespace Wonder\OAuth;

/** Redirect URI: https o loopback; confronto esatto, porta ignorata solo sul loopback (RFC 8252 §7.3). */
final class RedirectUri
{
    private const LOOPBACK = ['localhost', '127.0.0.1', '[::1]'];

    public static function isAllowed(string $uri): bool
    {
        if ($uri === '' || strlen($uri) > 2000 || str_contains($uri, '#')) {
            return false;
        }

        $parts = parse_url($uri);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        return $scheme === 'https' || ($scheme === 'http' && self::isLoopback($uri));
    }

    public static function isLoopback(string $uri): bool
    {
        $parts = parse_url($uri);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'http'
            && in_array(strtolower((string) ($parts['host'] ?? '')), self::LOOPBACK, true);
    }

    public static function matches(string $registered, string $presented): bool
    {
        if (!self::isLoopback($registered) || !self::isLoopback($presented)) {
            return hash_equals($registered, $presented);
        }

        return hash_equals(self::withoutPort($registered), self::withoutPort($presented));
    }

    public static function matchesAny(array $registered, string $presented): bool
    {
        foreach ($registered as $uri) {
            if (is_string($uri) && self::matches($uri, $presented)) {
                return true;
            }
        }

        return false;
    }

    public static function host(string $uri): string
    {
        return strtolower((string) (parse_url($uri, PHP_URL_HOST) ?? ''));
    }

    private static function withoutPort(string $uri): string
    {
        $p = parse_url($uri);

        return strtolower((string) $p['scheme']).'://'.strtolower((string) $p['host'])
            .($p['path'] ?? '').(isset($p['query']) ? '?'.$p['query'] : '');
    }
}
