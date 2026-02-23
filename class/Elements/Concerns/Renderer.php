<?php

namespace Wonder\Elements\Concerns;

use Wonder\Themes\Resolver;

trait Renderer
{
    /**
     * Renderizza l'elemento col tema attivo o con un tema esplicito.
     */
    public function render(?string $theme = null): string
    {
        $renderer = Resolver::renderer(static::class, $theme);
        return $renderer->render($this);
    }
}
