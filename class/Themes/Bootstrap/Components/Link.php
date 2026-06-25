<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\EscapesHtml;
use Wonder\Themes\Concerns\HasAttributes;

class Link extends Component
{
    use HasAttributes, EscapesHtml;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $label = method_exists($class, 'getLabel') ? (string) $class->getLabel() : '';
        $icon = trim((string) ($schema['icon'] ?? ''));
        $iconPosition = (string) ($schema['icon_position'] ?? 'start');
        $muted = (bool) ($schema['muted'] ?? false);
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];

        $rawClass = $attributes['class'] ?? '';
        if (is_array($rawClass)) {
            $rawClass = implode(' ', array_map('strval', $rawClass));
        }
        $rawClass = (string) $rawClass;
        $classes = array_filter(array_map('trim', explode(' ', $rawClass)));
        if ($muted) {
            $classes[] = 'text-body-secondary';
        }

        if ($classes !== []) {
            $attributes['class'] = array_values(array_unique($classes));
        } else {
            unset($attributes['class']);
        }
        $attributeString = $this->renderAttributes($attributes);

        $iconHtml = $icon !== '' ? '<i class="'.$this->escape($icon).'"></i>' : '';
        $labelHtml = $this->escape($label);

        $inner = match ($icon !== '' ? $iconPosition : 'none') {
            'end' => $labelHtml.' '.$iconHtml,
            'start' => $iconHtml.' '.$labelHtml,
            default => $labelHtml,
        };

        return '<a'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$inner.'</a>';

    }
}
