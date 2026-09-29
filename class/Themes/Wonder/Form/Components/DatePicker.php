<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class DatePicker extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->escape((string) ($this->schema['value'] ?? ''));
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $attributes = $this->fieldAttributes(['data-wi-check', 'data-wi-date-picker', 'placeholder'], $attributesArray);
        $class = $this->fieldClass($this->inputClass(), $attributesArray);

        return <<<HTML
<div class="{$this->containerClass('date')}">
    {$this->renderLabel()}
    <input type="text" id="{$id}" class="{$class}" name="{$name}" placeholder="gg/mm/aaaa" value="{$value}" data-wi-check="true" data-wi-date-picker="true"{$this->labelMarker()}{$attributes}>
    {$this->renderError()}
</div>
HTML;
    }
}
