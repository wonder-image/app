<?php

namespace Wonder\Themes\Concerns;

use InvalidArgumentException;
use Wonder\App\ResourceSchema\Input as ResourceInput;
use Wonder\Elements\Component as ElementComponent;

trait RendersThemeComponents
{
    protected function renderThemeComponents(array $components, string $theme): string
    {
        $html = '';

        foreach ($components as $component) {
            if (!is_object($component) || !method_exists($component, 'render')) {
                throw new InvalidArgumentException(
                    'Ogni componente figlio deve essere un oggetto renderizzabile.'
                );
            }

            $html .= $component instanceof ElementComponent || $component instanceof ResourceInput
                ? $component->render($theme)
                : $component->render();
        }

        return $html;
    }
}
