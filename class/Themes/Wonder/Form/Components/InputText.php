<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class InputText extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $type = $this->escape((string) ($this->schema['type'] ?? 'text'));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $class = $this->inputClass();
        $typeClass = strtolower((string) ($this->schema['type'] ?? 'text'));

        return <<<HTML
<div class="{$this->containerClass($typeClass)}">
    {$this->renderLabel()}
    <input type="{$type}" id="{$id}" class="{$class}" name="{$name}" value="{$value}"{$this->labelMarker()} {$attributes}>
    {$this->renderError()}
</div>
HTML;
    }
}
