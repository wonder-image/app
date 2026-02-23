<?php

namespace Wonder\Themes\Bootstrap;

use Wonder\Themes\Concerns\HasIdentifier;
use Wonder\Themes\Contracts\Renderer;
use Wonder\Themes\Concerns\EscapesHtml;

abstract class Component implements Renderer
{
    use EscapesHtml;
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
