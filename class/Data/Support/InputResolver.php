<?php

namespace Wonder\Data\Support;

class InputResolver
{
    public static function get(?string $field, array $input = []): ?string
    {
        if (!is_string($field) || trim($field) === '') {
            return null;
        }

        if (!isset($input[$field]) || !is_scalar($input[$field])) {
            return null;
        }

        $value = trim((string) $input[$field]);

        return $value !== '' ? $value : null;
    }
}
