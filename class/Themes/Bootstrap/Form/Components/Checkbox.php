<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class Checkbox extends Field
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
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $checked = !empty($this->schema['attributes']['checked']) ? ' checked' : '';
        $requiredClass = !empty($this->schema['attributes']['required']) ? ' wi-checkbox-required' : '';
        $label = $this->escape($this->resolvedLabel());

        return <<<HTML
<div id="container-{$id}" class="w-100 wi-container-checkbox{$requiredClass}">
    <input type="hidden" name="{$name}">
    <div class="input-group">
        <span class="input-group-text"><input class="form-check-input mt-0" type="checkbox" name="{$name}" id="{$id}"{$checked} {$attributes}></span>
        <label for="{$id}" class="form-control user-select-none">{$label}</label>
    </div>
</div>
HTML;
    }
}
