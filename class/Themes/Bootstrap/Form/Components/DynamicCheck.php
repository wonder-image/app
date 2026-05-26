<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class DynamicCheck extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), false);
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = (string) ($this->schema['name'] ?? '');
        $type = (string) ($this->schema['type'] ?? 'checkbox');
        $url = (string) ($this->schema['url'] ?? '');
        $rawValue = $this->schema['value'] ?? '';
        $value = is_scalar($rawValue) ? (string) $rawValue : json_encode($rawValue);
        $attributes = (array) ($this->schema['attributes'] ?? []);
        $isRequired = !empty($attributes['required']);
        $required = $isRequired ? "wi-{$type}-required" : '';
        unset($attributes['required']);
        $label = $this->escape($this->resolvedLabel());

        $fieldName = $type === 'checkbox' ? $name.'[]' : $name;
        $inputHidden = $type === 'checkbox'
            ? '<input type="hidden" name="'.$this->escape($fieldName).'">'
            : '';

        $attributeString = $this->renderAttributes($attributes);
        $escapedName = $this->escape($fieldName);
        $escapedValue = $this->escape((string) $value);
        $escapedUrl = $this->escape($url);

        return <<<HTML
<div id="container-{$id}" class="w-100 wi-container-{$type} {$required}">
    <h6>{$label}</h6>
    {$inputHidden}
    <div class="card border mt-1">
        <input type="text" class="form-control card-header m-0 border-0 border-bottom bg-body" placeholder="Cerca..." aria-label="Cerca..." data-wi-name="{$escapedName}" data-wi-value="{$escapedValue}" data-wi-search="true" data-wi-search-{$type}="true" data-wi-search-url="{$escapedUrl}" data-wi-attribute="{$attributeString}">
        <div class="card-body overflow-scroll p-2" style="height: 120px;"></div>
        <div class="card-footer border-top text-body-secondary">Cerca risultati</div>
    </div>
</div>
HTML;
    }
}
