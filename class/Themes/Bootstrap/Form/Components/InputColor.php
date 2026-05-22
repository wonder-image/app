<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class InputColor extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), false);
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $class = $this->inputClass('form-control');
        $iconStyle = $value !== '' ? ' style="color: '.$value.';"' : '';
        $label = $this->escape($this->resolvedLabel());

        return <<<HTML
<label class="h6 form-label" for="{$id}">{$label}</label>
<div class="input-group mt-1">
    <span class="input-group-text"><i class="bi bi-circle-fill wi-show-color"{$iconStyle}></i></span>
    <input type="text" class="{$class}" id="{$id}" aria-describedby="{$id}-color" name="{$name}" value="{$value}" placeholder="{$label}" {$attributes}>
</div>
HTML;
    }
}
