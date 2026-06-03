<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\EscapesHtml;
use Wonder\Themes\Concerns\HasAttributes;

class Link extends Component
{
    use CanSpanColumn, HasAttributes, EscapesHtml;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $href = method_exists($class, 'getHref') ? (string) $class->getHref() : '';
        $label = method_exists($class, 'getLabel') ? (string) $class->getLabel() : '';
        $target = trim((string) ($schema['target'] ?? ''));
        $rel = trim((string) ($schema['rel'] ?? ''));
        $title = trim((string) ($schema['title'] ?? ''));
        $icon = trim((string) ($schema['icon'] ?? ''));
        $iconPosition = (string) ($schema['icon_position'] ?? 'start');
        $muted = (bool) ($schema['muted'] ?? false);

        $rawClass = (string) (($schema['attributes']['class'] ?? '') ?: '');
        $classes = array_filter(array_map('trim', explode(' ', $rawClass)));
        if ($muted) {
            $classes[] = 'text-body-secondary';
        }

        $extras = [];
        if ($target !== '') {
            $extras[] = 'target="'.$this->escape($target).'"';
        }
        if ($rel !== '') {
            $extras[] = 'rel="'.$this->escape($rel).'"';
        }
        if ($title !== '') {
            $extras[] = 'title="'.$this->escape($title).'"';
        }

        $columnSpan = $this->getColumnSpan($class->columnSpan);
        $classAttr = $classes === [] ? '' : ' class="'.$this->escape(implode(' ', $classes)).'"';
        $extraAttr = $extras === [] ? '' : ' '.implode(' ', $extras);

        $iconHtml = $icon !== '' ? '<i class="'.$this->escape($icon).'"></i>' : '';
        $labelHtml = $this->escape($label);

        $inner = match ($icon !== '' ? $iconPosition : 'none') {
            'end' => $labelHtml.' '.$iconHtml,
            'start' => $iconHtml.' '.$labelHtml,
            default => $labelHtml,
        };

        $link = '<a href="'.$this->escape($href).'"'.$classAttr.$extraAttr.'>'.$inner.'</a>';

        return '<div class="'.$columnSpan.'">'.$link.'</div>';
    }
}
