<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Wonder\Component;

class ButtonGroup extends Component
{
    public function render($class): string
    {
        $schema = $class->getSchema();
        $classes = ['btn-group'];

        if (($schema['vertical'] ?? false) === true) {
            $classes[] = 'd-flex';
            $classes[] = 'd-column';
        }

        foreach ($this->extractClasses($schema['attributes'] ?? null) as $extraClass) {
            $classes[] = $extraClass;
        }

        $attributes = $this->renderAttributes($this->attributesWithoutClass($schema['attributes'] ?? null));

        return '<div class="'.$this->escape(implode(' ', array_values(array_unique(array_filter($classes))))).'"'
            .($attributes !== '' ? ' '.$attributes : '')
            .'>'.$this->renderComponents((array) $class->components).'</div>';
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
