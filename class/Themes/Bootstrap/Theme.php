<?php

namespace Wonder\Themes\Bootstrap;

use Wonder\Themes\Contracts\Theme as ThemeContract;

class Theme implements ThemeContract
{
    public function key(): string
    {
        return 'bootstrap';
    }

    public function namespace(): string
    {
        return 'Bootstrap';
    }

    public function fallback(): ?string
    {
        return null;
    }
}
