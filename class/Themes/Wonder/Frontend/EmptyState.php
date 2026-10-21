<?php

namespace Wonder\Themes\Wonder\Frontend;

use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Concerns\RendersPartAttributes;
use Wonder\Themes\Concerns\RendersThemeComponents;
use Wonder\Themes\Wonder\Component;

class EmptyState extends Component
{
    use RendersComponentAttributes, RendersPartAttributes, RendersThemeComponents;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $icon = trim((string) ($schema['icon'] ?? ''));
        $title = (string) ($schema['title'] ?? '');
        $text = (string) ($schema['text'] ?? '');
        $action = $schema['action'] ?? null;

        $html = '<div '.$this->renderComponentAttributes($class, ['wi-empty-state']).'>';

        if ($icon !== '') {
            $html .= '<i '.$this->partAttributes($class, 'icon', 'wi-empty-state__icon '.$icon, ['aria-hidden']).' aria-hidden="true"></i>';
        }

        if ($title !== '') {
            $html .= '<h2 '.$this->partAttributes($class, 'title', 'subtitle').'>'.$this->escape($title).'</h2>';
        }

        if ($text !== '') {
            $attributes = $this->partAttributes($class, 'text', '');
            $html .= '<p'.($attributes !== '' ? ' '.$attributes : '').'>'.$this->escape($text).'</p>';
        }

        if (is_object($action)) {
            $html .= $this->renderThemeComponents([$action], 'wonder');
        }

        return $html.'</div>';
    }
}
