<?php

namespace Wonder\Themes;

use RuntimeException;
use ReflectionClass;
use Wonder\App\Theme;
use Wonder\Themes\Contracts\Renderer;

class Resolver
{
    private const ELEMENT_NAMESPACE = 'Wonder\\Elements\\';

    public static function renderer($class, ?string $theme = null): Renderer
    {
        if (!is_string($class) || $class === '') {
            throw new RuntimeException('Classe elemento non valida.');
        }

        if (!class_exists($class)) {
            throw new RuntimeException("Classe elemento {$class} non trovata.");
        }

        $activeTheme = self::resolveRequestedTheme($theme);
        $themeChain = self::resolveThemeChain($activeTheme);
        $elementCandidates = self::resolveElementCandidates($class);
        $attempted = [];

        foreach ($themeChain as $themeKey) {
            $themeNamespace = Registry::get($themeKey)->namespace();

            foreach ($elementCandidates as $elementClass) {
                $relative = self::relativeElementClass($elementClass);
                if ($relative === null) {
                    continue;
                }

                $rendererClass = "Wonder\\Themes\\{$themeNamespace}\\{$relative}";
                $attempted[] = $rendererClass;

                if (!class_exists($rendererClass)) {
                    continue;
                }

                $reflection = new ReflectionClass($rendererClass);
                if ($reflection->isAbstract()) {
                    continue;
                }

                $renderer = new $rendererClass();
                if (!$renderer instanceof Renderer) {
                    throw new RuntimeException("{$rendererClass} deve implementare Renderer.");
                }

                return $renderer;
            }
        }

        $attemptedText = implode(', ', $attempted);
        throw new RuntimeException(
            "Nessun renderer trovato per {$class} (tema attivo: {$activeTheme}). Tentativi: {$attemptedText}"
        );
    }

    /**
     * Costruisce la catena tema corrente + fallback.
     *
     * @return string[]
     */
    private static function resolveThemeChain(string $activeTheme): array
    {
        $chain = [];
        $cursor = strtolower($activeTheme);

        while ($cursor !== '') {
            if (!Registry::has($cursor) || in_array($cursor, $chain, true)) {
                break;
            }

            $chain[] = $cursor;
            $fallback = Registry::get($cursor)->fallback();
            $cursor = $fallback !== null ? strtolower($fallback) : '';
        }

        $default = Registry::default();
        if (!in_array($default, $chain, true)) {
            $chain[] = $default;
        }

        return $chain;
    }

    /**
     * Costruisce la gerarchia concreta -> parent dell'elemento.
     *
     * @return string[]
     */
    private static function resolveElementCandidates(string $class): array
    {
        $candidates = [];
        $cursor = $class;

        while (is_string($cursor) && $cursor !== '') {
            if (str_starts_with($cursor, self::ELEMENT_NAMESPACE)) {
                $candidates[] = $cursor;
            }

            $parent = get_parent_class($cursor);
            if ($parent === false) {
                break;
            }

            $cursor = $parent;
        }

        return $candidates;
    }

    private static function relativeElementClass(string $class): ?string
    {
        if (!str_starts_with($class, self::ELEMENT_NAMESPACE)) {
            return null;
        }

        return substr($class, strlen(self::ELEMENT_NAMESPACE));
    }

    private static function resolveRequestedTheme(?string $theme): string
    {
        if ($theme === null) {
            return Theme::get();
        }

        $normalized = strtolower(trim($theme));
        if ($normalized === '') {
            return Theme::get();
        }

        if (!Registry::has($normalized)) {
            $available = implode(', ', Registry::keys());
            throw new RuntimeException("Tema {$theme} non registrato. Temi disponibili: {$available}");
        }

        return $normalized;
    }
}
