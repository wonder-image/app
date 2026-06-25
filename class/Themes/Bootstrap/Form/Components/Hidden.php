<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class Hidden extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));

        return "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\" {$attributes}>";
    }
}
