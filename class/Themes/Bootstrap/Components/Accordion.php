<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Bootstrap\Concerns\RendersText;
use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersThemeComponents;

class Accordion extends Component
{
    use CanSpanColumn, RendersText, RendersComponentAttributes, RendersThemeComponents;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $title = $this->escapeText(trim($class->getText()));
        $description = $this->escapeText(trim($class->getDescription()));
        $expanded = (bool) ($schema['expanded'] ?? false);
        $flush = (bool) ($schema['flush'] ?? false);
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $id = $this->createId();

        $collapsed = $expanded ? '' : ' collapsed';
        $show = $expanded ? ' show' : '';
        $ariaExpanded = $expanded ? 'true' : 'false';
        $accordionClasses = ['accordion'];
        if ($flush) {
            $accordionClasses[] = 'accordion-flush';
        }

        $attributes = $this->renderComponentAttributes($class, $accordionClasses);
        $content = $description.$this->renderThemeComponents($class->components, 'bootstrap');

        $html = "<div class=\"{$classSpanColumn}\">";
        $html .= "<div {$attributes}>";
        $html .= '<div class="accordion-item">';
        $html .= '<div class="accordion-header">';
        $html .= "<button class=\"accordion-button{$collapsed}\" type=\"button\""
            . " data-bs-toggle=\"collapse\" data-bs-target=\"#{$id}\""
            . " aria-expanded=\"{$ariaExpanded}\" aria-controls=\"{$id}\">"
            . "{$title}</button>";
        $html .= '</div>';
        $html .= "<div id=\"{$id}\" class=\"accordion-collapse collapse{$show}\">";
        $html .= "<div class=\"accordion-body\">{$content}</div>";
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
