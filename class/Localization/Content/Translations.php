<?php

namespace Wonder\Localization\Content;

use Closure;

/**
 * Store di default per le traduzioni dei contenuti. I test e i siti possono
 * sostituirlo con `Translations::useStore()`.
 */
final class Translations
{
    private static ?Closure $factory = null;
    private static ?TranslationStore $store = null;

    public static function store(): TranslationStore
    {
        return self::$store ??= self::$factory !== null ? (self::$factory)() : new DatabaseTranslationStore();
    }

    /** @param TranslationStore|Closure(): TranslationStore|null $store null torna al database */
    public static function useStore(TranslationStore|Closure|null $store): void
    {
        self::$store = $store instanceof TranslationStore ? $store : null;
        self::$factory = $store instanceof Closure ? $store : null;
    }

    /** Hash del testo sorgente, uguale a `SHA2(colonna, 256)` in MySQL. */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }
}
