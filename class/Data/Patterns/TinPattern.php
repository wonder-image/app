<?php

namespace Wonder\Data\Patterns;

class TinPattern
{
    private const MAP = [
        'IT' => [
            'private' => '([A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z])',
            'business' => '([0-9]{11}|[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z])',
        ],
        'DE' => [
            'private' => '([0-9]{11})',
            'business' => '([0-9]{11})',
        ],
        'FR' => [
            'private' => '([0-9]{13})',
            'business' => '([0-9]{13})',
        ],
        'ES' => [
            'private' => '([A-Z0-9][0-9]{7}[A-Z0-9])',
            'business' => '([A-Z0-9][0-9]{7}[A-Z0-9])',
        ],
        'NL' => [
            'private' => '([0-9]{9})',
            'business' => '([0-9]{9})',
        ],
        'BE' => [
            'private' => '([0-9]{11})',
            'business' => '([0-9]{11})',
        ],
        'AT' => [
            'private' => '([0-9]{9})',
            'business' => '([0-9]{9})',
        ],
        'IE' => [
            'private' => '([0-9A-Z]{7,8})',
            'business' => '([0-9A-Z]{7,8})',
        ],
        'PL' => [
            'private' => '([0-9]{11})',
            'business' => '([0-9]{11})',
        ],
        'default' => [
            'private' => '([A-Z0-9]{5,20})',
            'business' => '([A-Z0-9]{5,20})',
        ],
    ];

    public static function resolve(string $country, ?string $type = null): ?string
    {
        $patternSet = self::MAP[$country] ?? (self::MAP['default'] ?? null);
        if ($patternSet === null) {
            return null;
        }

        if (!is_array($patternSet)) {
            return $patternSet;
        }

        $type = $type ?? 'all';

        if ($type === 'all') {
            $business = $patternSet['business'] ?? null;
            $private = $patternSet['private'] ?? null;

            if ($business !== null && $private !== null && $business !== $private) {
                return '(?:' . $business . '|' . $private . ')';
            }

            return $business ?? $private;
        }

        return $patternSet[$type] ?? null;
    }
}
