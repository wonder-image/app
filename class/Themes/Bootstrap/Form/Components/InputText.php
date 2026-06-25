<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class InputText extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $type = $this->escape((string) ($this->schema['type'] ?? 'text'));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $class = $this->inputClass('form-control');

        return "<input class=\"{$class}\" type=\"{$type}\" name=\"{$name}\" id=\"{$id}\" value=\"{$value}\" {$attributes} />";
    }
}
