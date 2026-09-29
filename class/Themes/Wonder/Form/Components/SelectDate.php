<?php

namespace Wonder\Themes\Wonder\Form\Components;

/**
 * Renderer Wonder di `SelectDate`. Differenza rispetto a `DatePicker`:
 * include la validazione inline `on change` con i messaggi di errore
 * "deve essere minore/maggiore del…" usati nel frontend storico.
 */
class SelectDate extends DatePicker
{
    public function renderInput(): string
    {
        \Wonder\App\Dependencies::moment();
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $attributes = $this->fieldAttributes(['data-wi-check', 'data-wi-date-label', 'data-wi-date-picker', 'data-wi-select-date', 'placeholder'], $attributesArray);
        $class = $this->fieldClass($this->inputClass(), $attributesArray);
        $rawLabel = strtolower(str_replace('*', '', $this->resolvedLabel()));
        $label = $this->escape($rawLabel);

        return <<<HTML
<div class="{$this->containerClass('date')}">
    {$this->renderLabel()}
    <input type="text" id="{$id}" class="{$class}" name="{$name}" placeholder="gg/mm/aaaa" data-wi-check="true" data-wi-date-picker="true" data-wi-select-date="true" data-wi-date-label="{$label}"{$this->labelMarker()}{$attributes} value="{$value}">
    {$this->renderError()}
</div>
HTML;
    }
}
