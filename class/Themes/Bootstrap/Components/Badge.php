<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\HasAttributes;

class Badge extends Component
{
    use CanSpanColumn, HasAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $classes = ['badge'];
        $variant = $this->normalizeVariant((string) ($schema['variant'] ?? 'secondary'));
        $icon = trim((string) ($schema['icon'] ?? ''));
        $iconPosition = (string) ($schema['icon_position'] ?? 'start');
        $label = $this->escape((string) $class->getLabel());
        $href = trim((string) $class->getHref());
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];

        if (($schema['outline'] ?? false) === true) {
            $classes[] = 'border';
            $classes[] = "border-{$variant}";
            $classes[] = "text-{$variant}";
            $classes[] = 'bg-transparent';
        } else {
            $classes[] = "text-bg-{$variant}";
        }

        if (($schema['pill'] ?? false) === true) {
            $classes[] = 'rounded-pill';
        }

        if ($icon !== '') {
            $classes[] = 'd-inline-flex';
            $classes[] = 'align-items-center';
            $classes[] = 'gap-1';
        }

        foreach ($this->extractClasses($attributes) as $extraClass) {
            $classes[] = $extraClass;
        }

        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $attributes['class'] = array_values(array_unique(array_filter($classes)));
        $iconHtml = $icon !== '' ? '<i class="'.$this->escape($icon).'"></i>' : '';
        $content = match ($icon !== '' ? $iconPosition : 'none') {
            'end' => $label.' '.$iconHtml,
            'start' => $iconHtml.' '.$label,
            default => $label,
        };

        $html = "<div class=\"{$classSpanColumn}\">";

        if ($href !== '') {
            $attributeString = $this->renderAttributes($attributes);
            $html .= '<a'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$content.'</a>';
        } else {
            $spanAttributes = $this->attributesWithoutKeys($attributes, ['href', 'target', 'rel', 'download']);
            $attributeString = $this->renderAttributes($spanAttributes);
            $html .= '<span'.($attributeString !== '' ? ' '.$attributeString : '').'>'.$content.'</span>';
        }

        $html .= '</div>';

        return $html;
    }

    private function normalizeVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));

        return match ($variant) {
            'white' => 'light',
            'black' => 'dark',
            'link' => 'secondary',
            '' => 'secondary',
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
