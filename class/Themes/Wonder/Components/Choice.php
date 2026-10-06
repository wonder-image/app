<?php

namespace Wonder\Themes\Wonder\Components;

use Wonder\Themes\Concerns\MergesClasses;
use Wonder\Themes\Wonder\Component;

class Choice extends Component
{
    use MergesClasses;

    public function render($class): string
    {
        $schema = $class->getSchema();
        $attributes = is_array($schema['attributes'] ?? null) ? $schema['attributes'] : [];
        $attributes['class'] = $this->mergeClasses(['wi-choice'], $attributes);

        $input = $this->renderAttributes([
            'type' => (string) $schema['type'],
            'name' => (string) $schema['name'],
            'value' => (string) $schema['value'],
            'checked' => (bool) $schema['checked'],
            'disabled' => (bool) $schema['disabled'],
            'data-choice-input' => true,
        ]);

        return '<label '.$this->renderAttributes($attributes).'>'
            .'<input '.$input.'>'
            .'<span class="wi-choice__body">'
            .$this->part('wi-choice__title', 'data-choice-title', (string) $schema['title'])
            .$this->part('wi-choice__text', 'data-choice-text', (string) $schema['text'])
            .'</span>'
            .$this->part('wi-choice__aside', 'data-choice-aside', (string) $schema['aside'])
            .'</label>';
    }

    private function part(string $class, string $hook, string $value): string
    {
        return '<span class="'.$class.'" '.$hook.($value === '' ? ' hidden' : '').'>'.$this->escape($value).'</span>';
    }
}
