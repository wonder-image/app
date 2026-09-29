<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class DateRange extends Field
{
    public function renderInput(): string
    {
        $baseId = $this->escape((string) ($this->schema['id'] ?? 'date-range'));
        $name = (string) ($this->schema['name'] ?? '');
        $value = is_array($this->schema['value'] ?? null) ? $this->schema['value'] : ['', ''];
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $attributes = $this->fieldAttributes(['data-wi-check', 'readonly', 'placeholder'], $attributesArray);
        $fromClass = $this->fieldClass('wi-input wi-daterange-from', $attributesArray);
        $toClass = $this->fieldClass('wi-input wi-daterange-to', $attributesArray);
        $fromName = $this->escape($name.'_from');
        $toName = $this->escape($name.'_to');
        $fromValue = $this->escape((string) ($value[0] ?? ''));
        $toValue = $this->escape((string) ($value[1] ?? ''));

        return <<<HTML
<div class="{$this->containerClass('daterange')}" data-wi-date-range="true">
    {$this->renderLabel()}
    <input type="text" id="{$baseId}-from" class="{$fromClass}" name="{$fromName}" value="{$fromValue}" placeholder="gg/mm/aaaa" data-wi-check="true"{$this->labelMarker()} readonly{$attributes}>
    <span class="wi-input-text">-</span>
    <input type="text" id="{$baseId}-to" class="{$toClass}" name="{$toName}" value="{$toValue}" placeholder="gg/mm/aaaa" data-wi-check="true"{$this->labelMarker()} readonly{$attributes}>
    {$this->renderError()}
</div>
HTML;
    }
}
