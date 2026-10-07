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
        $list = match ((string) ($schema['variant'] ?? '')) {
            'segmented' => 'btn-group w-100',
            'list' => 'list-group',
            default => 'd-grid gap-2',
        };

        return '<fieldset '.$this->renderComponentAttributes($class, ['border-0', 'p-0', 'm-0']).'>'
            .($legend !== '' ? '<legend class="form-label fw-semibold fs-6">'.$this->escape($legend).'</legend>' : '')
            .'<div class="'.$list.'" data-choice-list>'
            .$this->renderComponents($schema['choices'] ?? [])
            .'</div></fieldset>';
    }
}
