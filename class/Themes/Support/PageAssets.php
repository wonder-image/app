<?php

namespace Wonder\Themes\Support;

/**
 * Frammenti da stampare una sola volta per pagina.
 *
 * Un renderer che porta con sé un piccolo `<style>` o `<script>` (il bottone
 * "copia" del blocco di codice, il resize dell'anteprima) lo chiede qui con una
 * chiave: la prima istanza lo stampa, le altre ricevono una stringa vuota. Il
 * registro è unico per processo, così due renderer di temi diversi che
 * condividono lo stesso frammento non lo ripetono.
 */
final class PageAssets
{
    /** @var array<string, true> */
    private static array $emitted = [];

    /**
     * @param callable(): string|string $html
     */
    public static function once(string $key, callable|string $html): string
    {
        $key = trim($key);

        if ($key === '' || isset(self::$emitted[$key])) {
            return '';
        }

        self::$emitted[$key] = true;

        return is_callable($html) ? (string) $html() : $html;
    }

    public static function emitted(string $key): bool
    {
        return isset(self::$emitted[trim($key)]);
    }

    /** Dimentica cosa è già stato stampato (test, o una nuova pagina nello stesso processo). */
    public static function reset(): void
    {
        self::$emitted = [];
    }
}
