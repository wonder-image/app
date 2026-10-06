<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\MergesClasses;
use Wonder\Themes\Wonder\Component;

class Steps extends Component
{
    use MergesClasses;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $attributes['class'] = $this->mergeClasses(['wi-steps'], $attributes);
        $label = (string) ($schema['label'] ?? '');
        $items = '';

        foreach ($schema['steps'] ?? [] as $step) {
            $text = $this->escape((string) $step['label']);

            $items .= match ($step['state']) {
                'done' => '<li class="wi-steps__item is-done">'
                    .($step['href'] !== null ? '<a href="'.$this->escape($step['href']).'">'.$text.'</a>' : $text)
                    .'</li>',
                'current' => '<li class="wi-steps__item" aria-current="step">'.$text.'</li>',
                default => '<li class="wi-steps__item is-disabled" aria-disabled="true">'.$text.'</li>',
            };
        }

        return '<nav'.($label !== '' ? ' aria-label="'.$this->escape($label).'"' : '').'>'
            .'<ol '.$this->renderAttributes($attributes).'>'.$items.'</ol></nav>';
    }
}
