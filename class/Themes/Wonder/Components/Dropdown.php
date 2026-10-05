<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersDropdownItems;
use Wonder\Themes\Wonder\Component;

class Dropdown extends Component
{
    use RendersDropdownItems;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $wrapperClasses = ['wi-dropdown-btn'];
        $toggleClasses = ['btn'];
        $variant = strtolower(trim((string) ($schema['variant'] ?? 'secondary')));
        $outline = (bool) ($schema['outline'] ?? false);
        $size = trim((string) ($schema['size'] ?? ''));
        $disabled = (bool) ($schema['disabled'] ?? false);
        $align = (string) ($schema['align'] ?? 'start');

        if ($align === 'end') {
            $wrapperClasses[] = 'f-end';
        }

        $toggleClasses[] = 'btn-icon-right';
        $toggleClasses[] = 'btn-'.($variant !== '' ? $variant : 'secondary').($outline ? '-o' : '');
        $toggleClasses[] = 'wi-switcher';

        if ($size !== '') {
            $toggleClasses[] = 'btn-'.$size;
        }

        foreach ($this->extractClasses($schema['attributes'] ?? null) as $extraClass) {
            $wrapperClasses[] = $extraClass;
        }

        $attributes = $this->renderAttributes($this->attributesWithoutClass($schema['attributes'] ?? null));
        $label = $this->escape($class->getLabel());
        $caret = '<i class="bi bi-chevron-down"></i>';

        $html = '<div class="'.$this->escape(implode(' ', array_values(array_unique(array_filter($wrapperClasses))))).'"'
            .($attributes !== '' ? ' '.$attributes : '')
            .'>';
        $html .= '<button type="button" class="'.$this->escape($this->partClass($class, 'toggle', implode(' ', array_values(array_unique(array_filter($toggleClasses)))))).'"'
            .($disabled ? ' disabled' : '')
            .'>'.$label.' '.$caret.'</button>';
        $html .= '<div class="'.$this->escape($this->partClass($class, 'menu', 'wi-dropdown-list '.($align === 'end' ? 'end' : 'start'))).'">';
        $html .= $this->renderItems($class, $class->getItems());
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function renderItems(object $dropdown, array $items): string
    {
        $html = '';

        foreach ($items as $item) {
            $kind = (string) ($item['kind'] ?? 'link');

            if ($kind === 'divider') {
                $html .= '<div class="dropdown-divider"></div>';
                continue;
            }

            if ($kind === 'header') {
                $html .= '<div class="wi-dropdown-item fw-700">'.$this->escape((string) ($item['label'] ?? '')).'</div>';
                continue;
            }

            if ($kind === 'text') {
                $html .= '<div class="wi-dropdown-item">'.$this->escape((string) ($item['label'] ?? '')).'</div>';
                continue;
            }

            $label = $this->escape((string) ($item['label'] ?? ''));
            $icon = trim((string) ($item['icon'] ?? ''));
            if ($icon !== '') {
                $label = '<i class="'.$this->escape($icon).'"></i> '.$label;
            }

            $html .= $this->renderDropdownAction($dropdown, $item, 'wi-dropdown-item', 'tx-', $label);
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
