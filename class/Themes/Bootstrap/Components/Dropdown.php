<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Bootstrap\Concerns\CanSpanColumn;
use Wonder\Themes\Concerns\HasAttributes;

class Dropdown extends Component
{
    use CanSpanColumn, HasAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $inline = (bool) ($schema['inline'] ?? false);
        $classes = [];
        $direction = (string) ($schema['direction'] ?? 'down');
        $grouped = (bool) ($schema['grouped'] ?? false);
        $toggleClasses = ['btn', 'dropdown-toggle'];
        $variant = $this->normalizeVariant((string) ($schema['variant'] ?? 'secondary'));
        $size = trim((string) ($schema['size'] ?? ''));
        $disabled = (bool) ($schema['disabled'] ?? false);
        $label = $this->escape($class->getLabel());
        $menuClasses = ['dropdown-menu'];

        $classes[] = $grouped ? 'btn-group' : 'dropdown';

        if ($direction === 'up') {
            $classes[] = 'dropup';
        } elseif ($direction === 'start') {
            $classes[] = 'dropstart';
        } elseif ($direction === 'end') {
            $classes[] = 'dropend';
        }

        if (($schema['outline'] ?? false) === true) {
            $toggleClasses[] = "btn-outline-{$variant}";
        } else {
            $toggleClasses[] = "btn-{$variant}";
        }

        if ($size !== '') {
            $toggleClasses[] = "btn-{$size}";
        }

        if ((string) ($schema['align'] ?? 'start') === 'end') {
            $menuClasses[] = 'dropdown-menu-end';
        }

        if (($schema['dark'] ?? false) === true) {
            $menuClasses[] = 'dropdown-menu-dark';
        }

        foreach ($this->extractClasses($schema['attributes'] ?? null) as $extraClass) {
            $classes[] = $extraClass;
        }

        $attributes = $this->renderAttributes($this->attributesWithoutClass($schema['attributes'] ?? null));

        $html = $inline ? '' : "<div class=\"{$this->getColumnSpan($class->columnSpan)}\">";
        $html .= '<div class="'.$this->escape(implode(' ', array_values(array_unique(array_filter($classes))))).'"'
            .($grouped ? ' role="group"' : '')
            .($attributes !== '' ? ' '.$attributes : '')
            .'>';
        $html .= '<button type="button" class="'.$this->escape(implode(' ', array_values(array_unique(array_filter($toggleClasses))))).'"'
            .' data-bs-toggle="dropdown" aria-expanded="false"'
            .($disabled ? ' disabled' : '')
            .'>'.$label.'</button>';
        $html .= '<ul class="'.$this->escape(implode(' ', array_values(array_unique(array_filter($menuClasses))))).'">';
        $html .= $this->renderItems($class->getItems());
        $html .= '</ul>';
        $html .= '</div>';
        if (!$inline) {
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function renderItems(array $items): string
    {
        $html = '';

        foreach ($items as $item) {
            $kind = (string) ($item['kind'] ?? 'link');

            if ($kind === 'divider') {
                $html .= '<li><hr class="dropdown-divider"></li>';
                continue;
            }

            if ($kind === 'header') {
                $html .= '<li><h6 class="dropdown-header">'.$this->escape((string) ($item['label'] ?? '')).'</h6></li>';
                continue;
            }

            if ($kind === 'text') {
                $html .= '<li><span class="dropdown-item-text">'.$this->escape((string) ($item['label'] ?? '')).'</span></li>';
                continue;
            }

            $classes = ['dropdown-item'];
            if (!empty($item['active'])) {
                $classes[] = 'active';
            }
            if (!empty($item['disabled'])) {
                $classes[] = 'disabled';
            }

            $label = $this->escape((string) ($item['label'] ?? ''));
            $icon = trim((string) ($item['icon'] ?? ''));
            if ($icon !== '') {
                $label = '<i class="'.$this->escape($icon).' me-2"></i>'.$label;
            }

            if ($kind === 'button') {
                $itemAttributes = $this->renderAttributes($item['attributes'] ?? null);
                $html .= '<li><button type="button" class="'.$this->escape(implode(' ', $classes)).'"'
                    .(!empty($item['disabled']) ? ' disabled' : '')
                    .($itemAttributes !== '' ? ' '.$itemAttributes : '')
                    .'>'.$label.'</button></li>';
                continue;
            }

            $extra = '';
            $href = trim((string) ($item['href'] ?? '#'));
            $title = trim((string) ($item['title'] ?? ''));
            $target = trim((string) ($item['target'] ?? ''));
            $rel = trim((string) ($item['rel'] ?? ''));

            if (!empty($item['blank'])) {
                $target = '_blank';
                $rel = trim($rel.' noopener noreferrer');
            }
            if ($target !== '') {
                $extra .= ' target="'.$this->escape($target).'"';
            }
            if ($rel !== '') {
                $extra .= ' rel="'.$this->escape($rel).'"';
            }
            if ($title !== '') {
                $extra .= ' title="'.$this->escape($title).'"';
            }

            $itemAttributes = $this->renderAttributes($item['attributes'] ?? null);
            $html .= '<li><a href="'.$this->escape($href).'" class="'.$this->escape(implode(' ', $classes)).'"'
                .$extra
                .($itemAttributes !== '' ? ' '.$itemAttributes : '')
                .'>'.$label.'</a></li>';
        }

        return $html;
    }

    private function normalizeVariant(string $variant): string
    {
        $variant = strtolower(trim($variant));

        return match ($variant) {
            'white' => 'light',
            'black' => 'dark',
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
}
