<?php

namespace Wonder\Themes;

use InvalidArgumentException;
use Wonder\Themes\Contracts\Theme as ThemeContract;

class Registry
{
    /**
     * @var array<string, ThemeContract>|null
     */
    private static ?array $themes = null;

    private static string $defaultTheme = 'wonder';

    /**
     * Registra i temi core disponibili di default.
     */
    private static function boot(): void
    {
        if (self::$themes !== null) {
            return;
        }

        self::$themes = [];

        self::register(\Wonder\Themes\Wonder\Theme::class);
        self::register(\Wonder\Themes\Bootstrap\Theme::class);
    }

    /**
     * Registra un tema tramite classe che implementa ThemeContract.
     */
    public static function register(string $themeClass): void
    {
        if (!class_exists($themeClass)) {
            throw new InvalidArgumentException("Classe tema {$themeClass} non trovata.");
        }

        $theme = new $themeClass();
        if (!$theme instanceof ThemeContract) {
            throw new InvalidArgumentException("{$themeClass} deve implementare ThemeContract.");
        }

        $key = strtolower(trim($theme->key()));
        if ($key === '') {
            throw new InvalidArgumentException("Chiave tema non valida per {$themeClass}.");
        }

        self::$themes[$key] = $theme;
    }

    public static function has(string $key): bool
    {
        self::boot();
        return isset(self::$themes[strtolower($key)]);
    }

    public static function get(string $key): ThemeContract
    {
        self::boot();
        $normalized = strtolower($key);

        if (!isset(self::$themes[$normalized])) {
            throw new InvalidArgumentException("Tema {$key} non registrato.");
        }

        return self::$themes[$normalized];
    }

    public static function keys(): array
    {
        self::boot();
        return array_keys(self::$themes);
    }

    public static function setDefault(string $key): void
    {
        $normalized = strtolower(trim($key));
        if (!self::has($normalized)) {
            throw new InvalidArgumentException("Tema {$key} non registrato.");
        }

        self::$defaultTheme = $normalized;
    }

    public static function default(): string
    {
        self::boot();
        if (!isset(self::$themes[self::$defaultTheme])) {
            $keys = self::keys();
            self::$defaultTheme = $keys[0] ?? 'wonder';
        }

        return self::$defaultTheme;
    }
}
