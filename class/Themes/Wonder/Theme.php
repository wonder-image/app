<?php

namespace Wonder\Themes\Wonder;

use Wonder\Themes\Contracts\Theme as ThemeContract;

class Theme implements ThemeContract
{
    public function key(): string
    {
        return 'wonder';
    }

    public function namespace(): string
    {
        return 'Wonder';
    }

    public function fallback(): ?string
    {
        return null;
    }
}
