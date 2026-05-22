<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

class DatePicker extends InputText
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $class = $this->inputClass('form-control');
        $placeholder = $this->escape($this->resolvedLabel());

        return "<input class=\"{$class}\" type=\"text\" name=\"{$name}\" id=\"{$id}\" value=\"{$value}\" placeholder=\"{$placeholder}\" data-wi-date=\"true\" {$attributes} />";
    }
}
