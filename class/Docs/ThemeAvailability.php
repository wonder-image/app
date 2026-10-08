<?php

namespace Wonder\Docs;

/** Il risultato di `ThemeSupport::check()`: c'è un renderer, qual è, e se è ereditato. */
final class ThemeAvailability
{
    public function __construct(
        public readonly string $theme,
        public readonly bool $available,
        public readonly ?string $renderer = null,
        public readonly bool $inherited = false,
        public readonly string $reason = '',
    ) {}

    public function label(): string
    {
        return ThemeSupport::label($this->theme);
    }
}
