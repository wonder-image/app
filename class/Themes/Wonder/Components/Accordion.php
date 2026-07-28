<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersThemeComponents;
use Wonder\Themes\Wonder\Component;

class Accordion extends Component
{
    use RendersComponentAttributes;
    use RendersThemeComponents;

    private const ICONS = [
        'plus' => [
            'collapsed' => 'bi bi-plus',
            'expanded' => 'bi bi-dash',
        ],
        'chevron' => [
            'collapsed' => 'bi bi-chevron-down',
            'expanded' => 'bi bi-chevron-up',
        ],
        'plus-lg' => [
            'collapsed' => 'bi bi-plus-lg',
            'expanded' => 'bi bi-dash-lg',
        ],
    ];

    public function render($class): string
    {
        $schema = $class->getSchema();
        $expanded = (bool) ($schema['expanded'] ?? false);
        $iconStyle = (string) ($schema['icon'] ?? 'plus');
        $iconSet = self::ICONS[$iconStyle] ?? self::ICONS['plus'];
        $icon = $iconSet[$expanded ? 'expanded' : 'collapsed'];
        $title = $this->escape(trim($class->getText()));
        $description = $this->escape(trim($class->getDescription()));
        $content = $description.$this->renderThemeComponents($class->components, 'wonder');

        $rootClasses = ['wi-dropdown-box'];
        if ($expanded) {
            $rootClasses[] = 'wi-show';
        }

        $titleClasses = ['wi-dropdown-title', 'wi-switcher'];
        $titleSize = trim((string) ($schema['title_size'] ?? ''));
        if ($titleSize !== '') {
            $titleClasses[] = $titleSize;
        }

        $contentClasses = ['wi-dropdown-content'];
        $descriptionSize = trim((string) ($schema['description_size'] ?? ''));
        if ($descriptionSize !== '') {
            $contentClasses[] = $descriptionSize;
        }

        $rootAttributes = $this->renderComponentAttributes(
            $class,
            $rootClasses,
            ['wi-show']
        );

        $html = '<div '.$rootAttributes.'>';
        $html .= '<div class="'.$this->escape(implode(' ', $titleClasses)).'">';
        $html .= $title.' <i class="'.$this->escape($icon).'"></i>';
        $html .= '</div>';
        $html .= '<div class="'.$this->escape(implode(' ', $contentClasses)).'">';
        $html .= $content;
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}
