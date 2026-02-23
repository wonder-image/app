<?php

namespace Wonder\App;

use InvalidArgumentException;
use Wonder\Themes\Registry;

class Theme
{
    protected static string $theme = 'wonder';

    /**
     * Imposta il tema attivo tra quelli registrati.
     */
    public static function set(string $theme): void
    {
        self::$theme = self::normalizeAndValidateTheme($theme);
    }

    /**
     * Restituisce il tema attivo (o il default se non valido).
     */
    public static function get(): string
    {
        if (!Registry::has(self::$theme)) {
            self::$theme = Registry::default();
        }

        return self::$theme;
    }

    /**
     * Elenco dei temi disponibili.
     */
    public static function available(): array
    {
        return Registry::keys();
    }

    /**
     * Normalizza e valida il nome tema.
     */
    private static function normalizeAndValidateTheme(string $theme): string
    {
        $theme = strtolower(trim($theme));
        if ($theme === '') {
            throw new InvalidArgumentException('Il tema non può essere vuoto.');
        }

        if (!Registry::has($theme)) {
            $available = implode(', ', Registry::keys());
            throw new InvalidArgumentException("Tema {$theme} non trovato. Temi disponibili: {$available}");
        }

        return $theme;
    }
}
