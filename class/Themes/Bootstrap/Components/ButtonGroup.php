<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Dropdown;
use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\HasAttributes;

class ButtonGroup extends Component
{
    use CanSpanColumn, HasAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $classes = [
            ($schema['vertical'] ?? false) ? 'btn-group-vertical' : 'btn-group',
        ];
        $size = trim((string) ($schema['size'] ?? ''));

        if ($size !== '') {
            $classes[] = 'btn-group-'.$size;
        }

        foreach ($this->extractClasses($schema['attributes'] ?? null) as $extraClass) {
            $classes[] = $extraClass;
        }

        $role = ($schema['toolbar'] ?? false) ? 'toolbar' : 'group';
        $label = trim((string) ($schema['label'] ?? 'Button group'));
        $classSpanColumn = $this->getColumnSpan($class->columnSpan);
        $attributes = $this->renderAttributes($this->attributesWithoutClass($schema['attributes'] ?? null));

        $html = "<div class=\"{$classSpanColumn}\">";
        $html .= '<div class="'.$this->escape(implode(' ', array_values(array_unique(array_filter($classes))))).'"'
            .' role="'.$this->escape($role).'"'
            .' aria-label="'.$this->escape($label).'"'
            .($attributes !== '' ? ' '.$attributes : '')
            .'>';
        $html .= $this->renderGroupComponents((array) $class->components);
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int, object|string> $components
     */
    private function renderGroupComponents(array $components): string
    {
        $html = '';

        foreach ($components as $component) {
            if ($component instanceof Button) {
                $button = clone $component;
                $button->schema('inline', true);
                $html .= $button->render('bootstrap');
                continue;
            }

            if ($component instanceof Dropdown) {
                $dropdown = clone $component;
                $dropdown->grouped(true);
                $dropdown->schema('inline', true);
                $html .= $dropdown->render('bootstrap');
                continue;
            }

            if (is_object($component) && method_exists($component, 'render')) {
                $html .= $component->render('bootstrap');
                continue;
            }

            if (is_string($component)) {
                $html .= $component;
            }
        }

        return $html;
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
}
