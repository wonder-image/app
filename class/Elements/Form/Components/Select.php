<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

class Select extends Field
{
    public string $type = 'select';

    public function options(array $options): self
    {
        return $this->schema('options', $options);
    }

    public function placeholder(string $label = 'Seleziona'): self
    {
        $options = is_array($this->getSchema('options')) ? $this->getSchema('options') : [];

        return $this->schema('options', ['' => $label] + $options);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
