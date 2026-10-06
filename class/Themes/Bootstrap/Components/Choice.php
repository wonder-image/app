<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\RendersComponentAttributes;

class Choice extends Component
{
    use RendersComponentAttributes;

    public function render($class): string
    {
        $schema = $class->getSchema();

        $input = $this->renderAttributes([
            'class' => 'form-check-input',
            'type' => (string) $schema['type'],
            'name' => (string) $schema['name'],
            'value' => (string) $schema['value'],
            'checked' => (bool) $schema['checked'],
            'disabled' => (bool) $schema['disabled'],
            'data-choice-input' => true,
        ]);

        return '<label '.$this->renderComponentAttributes($class, ['card', 'user-select-none']).'>'
            .'<span class="card-body d-flex align-items-start gap-3">'
            .'<span class="form-check m-0"><input '.$input.'></span>'
            .'<span class="flex-grow-1">'
            .$this->part('d-block fw-semibold', 'data-choice-title', (string) $schema['title'])
            .$this->part('d-block small text-body-secondary', 'data-choice-text', (string) $schema['text'])
            .'</span>'
            .$this->part('text-nowrap fw-semibold', 'data-choice-aside', (string) $schema['aside'])
            .'</span></label>';
    }

    private function part(string $class, string $hook, string $value): string
    {
        return '<span class="'.$class.'" '.$hook.($value === '' ? ' hidden' : '').'>'.$this->escape($value).'</span>';
    }
}
