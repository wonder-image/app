<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class Checkbox extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $checked = !empty($this->schema['attributes']['checked']) ? ' checked' : '';

        return <<<HTML
<div class="{$this->containerClass('checkbox')}">
    <input type="hidden" name="{$name}">
    <label for="{$id}" class="wi-label">{$this->escape($this->resolvedLabel())}</label>
    <input type="checkbox" id="{$id}" class="{$this->inputClass()}" name="{$name}"{$checked} {$attributes}>
    {$this->renderError()}
</div>
HTML;
    }
}
