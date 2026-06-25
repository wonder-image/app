<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\HasAttributes;

class Button extends Component
{
    use CanSpanColumn, HasAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $inline = (bool) ($schema['inline'] ?? false);
        $classes = ['btn'];
        $variant = $this->normalizeVariant((string) ($schema['variant'] ?? 'primary'));
        $size = trim((string) ($schema['size'] ?? ''));
        $icon = trim((string) ($schema['icon'] ?? ''));
        $iconPosition = (string) ($schema['icon_position'] ?? 'end');
        $disabled = (bool) ($schema['disabled'] ?? false);
        $label = $this->escape((string) $class->getLabel());
        $href = trim((string) $class->getHref());
        $type = trim((string) ($schema['type'] ?? 'button'));
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];

        $classes[] = "text-decoration-none";

        $classes[] = ($schema['outline'] ?? false) ? "btn-outline-{$variant}" : "btn-{$variant}";

        if ($size !== '') {
            $classes[] = "btn-{$size}";
        }
        if (($schema['active'] ?? false) === true) {
            $classes[] = 'active';
        }
        if (($schema['block'] ?? false) === true) {
            $classes[] = 'w-100';
        }
        if (($schema['nowrap'] ?? false) === true) {
            $classes[] = 'text-nowrap';
        }
        if ($icon !== '') {
            $classes[] = 'd-inline-flex';
            $classes[] = 'align-items-center';
            $classes[] = 'gap-2';
        }

        foreach ($this->extractClasses($attributes) as $extraClass) {
            $classes[] = $extraClass;
        }

        $attributes['class'] = array_values(array_unique(array_filter($classes)));
        $iconHtml = $icon !== '' ? '<i class="'.$this->escape($icon).'"></i>' : '';
        $content = match ($icon !== '' ? $iconPosition : 'none') {
            'start' => $iconHtml.' '.$label,
            'end' => $label.' '.$iconHtml,
            default => $label,
        };

        $tag = $href !== '' && !$disabled ? 'a' : 'button';

        $html = $inline ? '' : "<div class=\"{$this->getColumnSpan($class->columnSpan)}\">";

        if ($tag === 'a') {
            $attributeString = $this->renderAttributes($attributes);
            $html .= '<a'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$content.'</a>';
        } else {
            if ($disabled) {
                $attributes['class'][] = 'disabled';
            }

            $buttonAttributes = $this->attributesWithoutKeys($attributes, ['href', 'target', 'rel', 'download']);
            $buttonAttributes['type'] = $type;
            if ($disabled) {
                $buttonAttributes['disabled'] = true;
            }

            $attributeString = $this->renderAttributes($buttonAttributes);
            $html .= '<button'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$content.'</button>';
        }

        if (!$inline) {
            $html .= '</div>';
        }

        return $html;
    }

    private function normalizeVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));

        return match ($variant) {
            'white' => 'light',
            'black' => 'dark',
            '' => 'primary',
            default => $variant,
        };
    }

    /**
     * @return string[]
     */
    private function extractClasses(?array $attributes): array
    {
        $raw = $attributes['class'] ?? [];

        if (is_string($raw)) {
            return array_values(array_filter(array_map('trim', explode(' ', $raw))));
        }

        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', array_map('strval', $raw))));
        }

        return [];
    }

    private function attributesWithoutClass(?array $attributes): ?array
    {
        if (!is_array($attributes)) {
            return null;
        }

        unset($attributes['class']);

        return $attributes;
    }

    private function attributesWithoutKeys(array $attributes, array $keys): array
    {
        foreach ($keys as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }
}
