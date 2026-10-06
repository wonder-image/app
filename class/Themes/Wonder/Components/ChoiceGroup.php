<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\MergesClasses;
use Wonder\Themes\Wonder\Component;

class ChoiceGroup extends Component
{
    use MergesClasses;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $attributes['class'] = $this->mergeClasses(['wi-choice-group'], $attributes);
        $legend = (string) ($schema['legend'] ?? '');

        return '<fieldset '.$this->renderAttributes($attributes).'>'
            .($legend !== '' ? '<legend class="wi-choice-group__legend">'.$this->escape($legend).'</legend>' : '')
            .'<div class="wi-choice-group__list" data-choice-list>'
            .$this->renderComponents($schema['choices'] ?? [])
            .'</div></fieldset>';
    }
}
