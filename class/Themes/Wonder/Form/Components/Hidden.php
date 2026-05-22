<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class Hidden extends Field
{
    public function renderInput(): string
    {
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));

        return "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\" {$attributes}>";
    }
}
