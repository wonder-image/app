<?php

namespace Wonder\Themes\Wonder;

use Wonder\Concerns\HasSchema;
use Wonder\Themes\Concerns\HasIdentifier;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Contracts\Renderer;

abstract class Component implements Renderer
{
    use HasSchema;
    use HasAttributes;
    use HasIdentifier;

    public function renderComponents($components): string
    {
        $html = "";
        foreach ($components as $key => $component) {
            $html .= $component->render();
        }

        return $html;
    }
}
