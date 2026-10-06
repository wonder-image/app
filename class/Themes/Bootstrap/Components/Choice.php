<?php

namespace Wonder\Themes\Bootstrap\Components;

use Wonder\Themes\Bootstrap\Component;
use Wonder\Themes\Concerns\HasAttributes;
use Wonder\Themes\Concerns\MergesClasses;

class Choice extends Component
{
    use HasAttributes, MergesClasses;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $attributes['class'] = $this->mergeClasses(['card'], $attributes);

        $input = $this->renderAttributes([
            'class' => 'form-check-input',
            'type' => (string) $schema['type'],
            'name' => (string) $schema['name'],
            'value' => (string) $schema['value'],
            'checked' => (bool) $schema['checked'],
            'disabled' => (bool) $schema['disabled'],
            'data-choice-input' => true,
        ]);

        return '<label '.$this->renderAttributes($attributes).'>'
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
