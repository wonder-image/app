<?php

namespace Wonder\Data\Patterns;

class VatPattern
{
    private const MAP = [
        'IT' => '([0-9]{11})',
        'DE' => '([0-9]{9})',
        'FR' => '([A-Z0-9]{2}[0-9]{9})',
        'ES' => '([A-Z0-9][0-9]{7}[A-Z0-9])',
        'NL' => '([0-9]{9}B[0-9]{2})',
        'BE' => '(0?[0-9]{9})',
        'AT' => '(U[0-9]{8})',
        'IE' => '([0-9A-Z]{7,8})',
        'PL' => '([0-9]{10})',
        'default' => '([A-Z0-9]{5,15})',
    ];

    public static function forCountry(string $country): ?string
    {
        return self::MAP[$country] ?? null;
    }

    public static function default(): ?string
    {
        return self::MAP['default'] ?? null;
    }

    public static function allCountries(): array
    {
        $patterns = self::MAP;
        unset($patterns['default']);

        return $patterns;
    }
}
