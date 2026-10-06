<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersComponentAttributes;

class ChoiceGroup extends Component
{
    use RendersComponentAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $legend = (string) ($schema['legend'] ?? '');

        return '<fieldset '.$this->renderComponentAttributes($class, ['border-0', 'p-0', 'm-0']).'>'
            .($legend !== '' ? '<legend class="form-label fw-semibold fs-6">'.$this->escape($legend).'</legend>' : '')
            .'<div class="d-grid gap-2" data-choice-list>'
            .$this->renderComponents($schema['choices'] ?? [])
            .'</div></fieldset>';
    }
}
