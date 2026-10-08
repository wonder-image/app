<?php

namespace Wonder\Docs;

use Throwable;
use Wonder\Themes\Registry;
use Wonder\Themes\Resolver;

/**
 * In quali temi un Element si può rendere.
 *
 * Non c'è una lista scritta a mano: si chiede al `Resolver` lo stesso
 * renderer che userebbe `render($theme)`. Se la classe trovata non è quella
 * speculare (`Themes\<Tema>\<Element>`), il renderer è ereditato da una
 * classe madre e la scheda lo segnala. Una scheda può comunque dichiarare un
 * tema `unsupported()` quando il renderer esiste ma non rende davvero il
 * componente.
 */
final class ThemeSupport
{
    private const ELEMENT_NAMESPACE = 'Wonder\\Elements\\';

    /** @return string[] */
    public static function themes(): array
    {
        return Registry::keys();
    }

    public static function label(string $theme): string
    {
        return ucfirst(strtolower(trim($theme)));
    }

    /** @return array<string, ThemeAvailability> tema => disponibilità */
    public static function for(ComponentDoc $doc): array
    {
        $result = [];

        foreach (self::themes() as $theme) {
            $forced = $doc->getUnsupported()[$theme] ?? null;

            if ($forced !== null) {
                $result[$theme] = new ThemeAvailability($theme, false, null, false, $forced);
                continue;
            }

            $result[$theme] = self::check($doc->getClass(), $theme);
        }

        return $result;
    }

    public static function check(string $class, string $theme): ThemeAvailability
    {
        $theme = strtolower(trim($theme));

        try {
            $renderer = Resolver::renderer($class, $theme);
        } catch (Throwable $exception) {
            return new ThemeAvailability($theme, false, null, false, self::reasonFrom($exception->getMessage(), $theme));
        }

        $rendererClass = $renderer::class;
        $expected = self::expectedRenderer($class, $theme);

        return new ThemeAvailability(
            $theme,
            true,
            $rendererClass,
            $expected !== null && $rendererClass !== $expected,
            $expected !== null && $rendererClass !== $expected
                ? 'Rende con '.$rendererClass.' (ereditato).'
                : ''
        );
    }

    /** La classe che un renderer dedicato avrebbe nel tema, per confronto. */
    public static function expectedRenderer(string $class, string $theme): ?string
    {
        $class = ltrim($class, '\\');

        if (!str_starts_with($class, self::ELEMENT_NAMESPACE) || !Registry::has($theme)) {
            return null;
        }

        return 'Wonder\\Themes\\'.Registry::get($theme)->namespace().'\\'.substr($class, strlen(self::ELEMENT_NAMESPACE));
    }

    private static function reasonFrom(string $message, string $theme): string
    {
        return 'Nessun renderer nel tema '.self::label($theme).'.';
    }
}
