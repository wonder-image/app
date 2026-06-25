<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class Textarea extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $class = $this->inputClass();

        return <<<HTML
<div class="{$this->containerClass('textarea')}">
    {$this->renderLabel()}
    <textarea name="{$name}" id="{$id}" class="{$class}" data-wi-label="true" {$attributes}>{$value}</textarea>
    {$this->renderError()}
</div>
HTML;
    }
}
