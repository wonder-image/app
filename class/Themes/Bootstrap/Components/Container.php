<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\HasColumns;
use Wonder\Themes\Bootstrap\Concerns\HasGap;
use Wonder\Themes\Concerns\HasAttributes;

class Container extends Component
{
    use HasColumns, CanSpanColumn, HasGap, HasAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $classColumn = $this->getColumns($class->columns);
        $classGap = $this->getGap($class->gap);
        $attributes = $this->renderAttributes($schema['attributes'] ?? null);

        $html = "<div class=\"{$classSpanColumn}\">";
        $html .= "<div class=\"{$classColumn} {$classGap}\" {$attributes}>";
        $html .= $this->renderComponents($class->components);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
