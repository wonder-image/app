<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Wonder\Component;

class Badge extends Component
{
    public function render($class): string
    {
        $schema = $class->getSchema();
        $variant = strtolower(trim((string) ($schema['variant'] ?? 'secondary')));
        $outline = (bool) ($schema['outline'] ?? false);
        $icon = trim((string) ($schema['icon'] ?? ''));
        $iconPosition = (string) ($schema['icon_position'] ?? 'start');
        $href = trim((string) $class->getHref());
        $label = $this->escape((string) $class->getLabel());
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];

        $classes = [
            'badge',
            'badge-'.($variant !== '' ? $variant : 'secondary').($outline ? '-o' : ''),
        ];

        foreach ($this->extractClasses($attributes) as $extraClass) {
            $classes[] = $extraClass;
        }

        $attributes['class'] = array_values(array_unique(array_filter($classes)));
        $iconHtml = $icon !== '' ? '<i class="'.$this->escape($icon).'"></i>' : '';
        $content = match ($icon !== '' ? $iconPosition : 'none') {
            'end' => $label.' '.$iconHtml,
            'start' => $iconHtml.' '.$label,
            default => $label,
        };

        if ($href !== '') {
            $attributeString = $this->renderAttributes($attributes);

            return '<a'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$content.'</a>';
        }

        $spanAttributes = $this->attributesWithoutKeys($attributes, ['href', 'target', 'rel', 'download']);
        $attributeString = $this->renderAttributes($spanAttributes);

        return '<span'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$content.'</span>';
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
