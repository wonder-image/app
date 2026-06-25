<?php

namespace Wonder\Support\Text;

class Identifier
{
    private const DEFAULT_LENGTH = 5;

    /**
     * Genera un id lowercase con parte random composta solo da lettere.
     */
    public static function make(int $length = self::DEFAULT_LENGTH): string
    {
        $size = $length > 0 ? $length : self::DEFAULT_LENGTH;
        Random::init('letters');

        return strtolower(Random::generate($size));
    }

    /**
     * Restituisce un id normalizzato dalla stringa o un fallback random.
     */
    public static function from(string $value, int $length = self::DEFAULT_LENGTH): string
    {
        $safeValue = Slug::make($value);

        if ($safeValue !== '') {
            return $safeValue;
        }

        return self::make($length);
    }
}
