<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersColumnSpan;
use Wonder\Themes\Concerns\RendersThemeComponents;
use Wonder\Themes\Wonder\Component;
use Wonder\Themes\Wonder\Concerns\ResponsiveGridClasses;

class Container extends Component
{
    use RendersComponentAttributes;
    use RendersColumnSpan;
    use RendersThemeComponents;
    use ResponsiveGridClasses;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $classes = [];

        if (($schema['no-grid'] ?? false) !== true) {
            $classes = array_merge(
                ['d-grid'],
                $this->responsiveClasses('col', $class->columns),
                $this->responsiveClasses('gap', $class->gap)
            );
        }

        $attributes = $this->renderComponentAttributes($class, $classes);
        $content = $this->renderThemeComponents($class->components, 'wonder');

        $html = '<div'.($attributes !== '' ? ' '.$attributes : '').'>'.$content.'</div>';

        return $this->wrapColumnSpan($class, $html);
    }

    protected function columnSpanClasses(array $span): string
    {
        return implode(' ', $this->responsiveClasses('col', $span));
    }
}
