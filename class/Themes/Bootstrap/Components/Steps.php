<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\MergesClasses;

class Steps extends Component
{
    use HasAttributes, MergesClasses;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $attributes['class'] = $this->mergeClasses(['breadcrumb', 'mb-0'], $attributes);
        $label = (string) ($schema['label'] ?? '');
        $items = '';

        foreach ($schema['steps'] ?? [] as $step) {
            $text = $this->escape((string) $step['label']);

            $items .= match ($step['state']) {
                'done' => '<li class="breadcrumb-item">'
                    .($step['href'] !== null ? '<a href="'.$this->escape($step['href']).'">'.$text.'</a>' : $text)
                    .'</li>',
                'current' => '<li class="breadcrumb-item active" aria-current="step">'.$text.'</li>',
                default => '<li class="breadcrumb-item text-body-tertiary" aria-disabled="true">'.$text.'</li>',
            };
        }

        return '<nav'.($label !== '' ? ' aria-label="'.$this->escape($label).'"' : '').'>'
            .'<ol '.$this->renderAttributes($attributes).'>'.$items.'</ol></nav>';
    }
}
