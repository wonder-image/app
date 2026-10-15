<?php

namespace Wonder\OAuth;

use Wonder\OAuth\Store\OAuthStore;
use Wonder\OAuth\Store\SqlStore;

/** Punto d'ingresso per i moduli. */
final class OAuth
{
    private static ?OAuthStore $store = null;

    public static function resource(string $path, string $name): void
    {
        ResourceRegistry::register($path, $name);
    }

    public static function enabled(): bool
    {
        return !ResourceRegistry::isEmpty();
    }

    public static function paths(): array
    {
        return ResourceRegistry::paths();
    }

    public static function resources(): array
    {
        return ResourceRegistry::all();
    }

    public static function resourceMetadataUrl(string $path): string
    {
        return Config::issuer().'/.well-known/oauth-protected-resource/'.trim($path, '/');
    }

    public static function store(): OAuthStore
    {
        return self::$store ??= new SqlStore();
    }

    /** Test: sostituisce la persistenza (null = torna a SqlStore). */
    public static function useStore(?OAuthStore $store): void
    {
        self::$store = $store;
    }

    public static function reset(): void
    {
        ResourceRegistry::clear();
        self::$store = null;
    }
}
