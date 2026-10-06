<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\MergesClasses;

class ChoiceGroup extends Component
{
    use HasAttributes, MergesClasses;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $attributes['class'] = $this->mergeClasses(['border-0', 'p-0', 'm-0'], $attributes);
        $legend = (string) ($schema['legend'] ?? '');

        return '<fieldset '.$this->renderAttributes($attributes).'>'
            .($legend !== '' ? '<legend class="form-label fw-semibold fs-6">'.$this->escape($legend).'</legend>' : '')
            .'<div class="d-grid gap-2" data-choice-list>'
            .$this->renderComponents($schema['choices'] ?? [])
            .'</div></fieldset>';
    }
}
