<?php

namespace Wonder\Themes\Concerns;

use Wonder\Support\Text\Identifier;

trait HasIdentifier
{
    protected function createId(int $length = 5): string
    {
        return Identifier::make($length);
    }

    protected function resolveId(mixed $value, int $length = 5): string
    {
        $candidate = is_scalar($value) ? (string) $value : '';

        return Identifier::from($candidate, $length);
    }
}
