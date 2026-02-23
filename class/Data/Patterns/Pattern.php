<?php

namespace Wonder\Data\Patterns;

class Pattern
{
    public static function normalizeValue(string $value): string
    {
        $raw = strtoupper(trim($value));

        return preg_replace('/[^A-Z0-9]/', '', $raw) ?? '';
    }

    public static function match(string $regex, string $value): ?array
    {
        if (!preg_match('/^' . $regex . '$/', $value, $matches)) {
            return null;
        }

        return $matches;
    }
}
