<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class TextareaEditor extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $label = $this->escape($this->resolvedLabel());
        $value = (string) ($this->schema['value'] ?? '');
        $version = $this->escape((string) ($this->schema['version'] ?? ''));
        $folder = $this->escape((string) ($this->schema['folder'] ?? ''));
        $encodedValue = $this->escape($value !== '' ? base64_encode($value) : '');
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $escapedValue = $this->escape($value);

        return <<<HTML
<div>
    <div class="form-floating">
        <h6 class="mb-1">{$label}</h6>
        <textarea id="{$id}" class="d-none" name="{$name}" data-wi-value="{$encodedValue}" data-wi-check="true" data-wi-textarea="{$version}" data-wi-folder="{$folder}" {$attributes}>{$escapedValue}</textarea>
    </div>
    {$this->renderError()}
</div>
HTML;
    }
}
