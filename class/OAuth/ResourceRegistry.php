<?php

namespace Wonder\OAuth;

use InvalidArgumentException;

/** Risorse protette dichiarate dai moduli. L'URL canonico si calcola a lettura da APP_URL. */
final class ResourceRegistry
{
    /** @var array<string,string> path => name */
    private static array $resources = [];

    public static function register(string $path, string $name): void
    {
        $path = '/'.trim($path, '/');
        if ($path === '/' || preg_match('#^/[A-Za-z0-9/_.-]+$#', $path) !== 1) {
            throw new InvalidArgumentException('oauth_resource_path_invalid');
        }
        self::$resources[$path] = trim($name) !== '' ? trim($name) : $path;
    }

    /** Solo i path registrati: non tocca l'issuer, sicuro al caricamento delle rotte. */
    public static function paths(): array
    {
        return array_keys(self::$resources);
    }

    public static function all(): array
    {
        if (self::$resources === []) {
            return [];
        }
        $issuer = Config::issuer();
        $out = [];
        foreach (self::$resources as $path => $name) {
            $out[] = [
                'path' => $path,
                'name' => $name,
                'url' => $issuer.$path,
                'metadata_url' => $issuer.'/.well-known/oauth-protected-resource'.$path,
            ];
        }
        return $out;
    }

    public static function findByUrl(string $url): ?array
    {
        $url = rtrim(trim($url), '/');
        foreach (self::all() as $r) {
            if (hash_equals($r['url'], $url)) {
                return $r;
            }
        }
        return null;
    }

    public static function findByPath(string $path): ?array
    {
        $path = '/'.trim($path, '/');
        foreach (self::all() as $r) {
            if ($r['path'] === $path) {
                return $r;
            }
        }
        return null;
    }

    public static function isEmpty(): bool { return self::$resources === []; }

    public static function clear(): void { self::$resources = []; }
}
