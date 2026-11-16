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

    /** @var list<string>|null Frammenti messi da parte da `capture()`. */
    private static ?array $captured = null;

    private static int $depth = 0;

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

        if (is_callable($html)) {
            self::$depth++;

            try {
                $html = (string) $html();
            } finally {
                self::$depth--;
            }
        }

        // Dentro `capture()` il frammento esce a parte; quelli annidati restano nel loro.
        if (self::$captured !== null && self::$depth === 0) {
            self::$captured[] = $html;

            return '';
        }

        return $html;
    }

    /**
     * Esegue un render tenendo fuori i frammenti "una volta sola": servono a
     * chi stampa dentro un `<template>`, dove uno script non gira.
     *
     * @param callable(): string $render
     * @return array{0: string, 1: string} html del render, frammenti emessi
     */
    public static function capture(callable $render): array
    {
        $previous = self::$captured;
        $previousDepth = self::$depth;
        self::$captured = [];
        self::$depth = 0;

        try {
            $html = (string) $render();
            $assets = implode('', self::$captured);
        } finally {
            self::$captured = $previous;
            self::$depth = $previousDepth;
        }

        return [$html, $assets];
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
