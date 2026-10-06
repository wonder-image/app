<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\RendersComponentAttributes;
use Wonder\Themes\Wonder\Component;

class ChoiceGroup extends Component
{
    use RendersComponentAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $legend = (string) ($schema['legend'] ?? '');

        return '<fieldset '.$this->renderComponentAttributes($class, ['wi-choice-group']).'>'
            .($legend !== '' ? '<legend class="wi-choice-group__legend">'.$this->escape($legend).'</legend>' : '')
            .'<div class="wi-choice-group__list" data-choice-list>'
            .$this->renderComponents($schema['choices'] ?? [])
            .'</div></fieldset>';
    }
}
