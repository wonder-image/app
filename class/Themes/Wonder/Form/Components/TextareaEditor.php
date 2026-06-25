<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class TextareaEditor extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = (string) ($this->schema['value'] ?? '');
        $version = $this->escape((string) ($this->schema['version'] ?? ''));
        $folder = $this->escape((string) ($this->schema['folder'] ?? ''));
        $encodedValue = $this->escape($value !== '' ? base64_encode($value) : '');
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $escapedValue = $this->escape($value);

        return <<<HTML
<div class="{$this->containerClass('textarea')}">
    {$this->renderLabel()}
    <textarea id="{$id}" class="d-none" name="{$name}" data-wi-value="{$encodedValue}" data-wi-check="true"{$this->labelMarker()} data-wi-textarea="{$version}" data-wi-folder="{$folder}" {$attributes}>{$escapedValue}</textarea>
    {$this->renderError()}
</div>
HTML;
    }
}
