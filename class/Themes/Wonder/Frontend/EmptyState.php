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
            $tag = in_array($schema['title_tag'] ?? null, $class::TITLE_TAGS, true) ? $schema['title_tag'] : 'h2';
            $html .= '<'.$tag.' '.$this->partAttributes($class, 'title', 'subtitle').'>'.$this->escape($title).'</'.$tag.'>';
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
