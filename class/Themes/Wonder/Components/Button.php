<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Wonder\Component;

class Button extends Component
{
    public function render($class): string
    {
        $schema = $class->getSchema();
        $classes = ['btn'];
        $variant = strtolower(trim((string) ($schema['variant'] ?? 'primary')));
        $outline = (bool) ($schema['outline'] ?? false);
        $size = trim((string) ($schema['size'] ?? ''));
        $icon = trim((string) ($schema['icon'] ?? ''));
        $iconPosition = (string) ($schema['icon_position'] ?? 'end');
        $disabled = (bool) ($schema['disabled'] ?? false);
        $href = trim((string) $class->getHref());
        $type = trim((string) ($schema['type'] ?? 'button'));
        $label = $this->escape((string) $class->getLabel());
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];

        $classes[] = 'btn-'.($variant !== '' ? $variant : 'primary').($outline ? '-o' : '');

        if ($size !== '') {
            $classes[] = 'btn-'.$size;
        }
        if (($schema['arrow'] ?? false) === true) {
            $classes[] = 'btn-arrow';
        }
        if ($icon !== '') {
            $classes[] = $iconPosition === 'start' ? 'btn-icon-left' : 'btn-icon-right';
        }
        if (($schema['block'] ?? false) === true) {
            $classes[] = 'w-100';
        }
        if (($schema['nowrap'] ?? false) === true) {
            $classes[] = 'text-nowrap';
        }
        if (($schema['active'] ?? false) === true) {
            $classes[] = 'active';
        }

        foreach ($this->extractClasses($attributes) as $extraClass) {
            $classes[] = $extraClass;
        }

        $tag = $href !== '' && !$disabled ? 'a' : 'button';
        $attributes['class'] = array_values(array_unique(array_filter($classes)));
        $iconHtml = $icon !== '' ? '<i class="'.$this->escape($icon).'"></i>' : '';
        $content = match ($icon !== '' ? $iconPosition : 'none') {
            'start' => $iconHtml.' '.$label,
            'end' => $label.' '.$iconHtml,
            default => $label,
        };

        if ($tag === 'a') {
            $attributeString = $this->renderAttributes($attributes);

            return '<a'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$content.'</a>';
        }

        if ($disabled) {
            $attributes['class'][] = 'disabled';
        }

        $buttonAttributes = $this->attributesWithoutKeys($attributes, ['href', 'target', 'rel', 'download']);
        $buttonAttributes['type'] = $type;
        if ($disabled) {
            $buttonAttributes['disabled'] = true;
        }

        $attributeString = $this->renderAttributes($buttonAttributes);

        return '<button'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$content.'</button>';
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
