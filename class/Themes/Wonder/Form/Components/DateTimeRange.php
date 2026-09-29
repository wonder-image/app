<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class DateTimeRange extends Field
{
    public function renderInput(): string
    {
        $baseId = $this->escape((string) ($this->schema['id'] ?? 'datetime-range'));
        $name = (string) ($this->schema['name'] ?? '');
        $value = is_array($this->schema['value'] ?? null) ? $this->schema['value'] : ['', ''];
        $attributesArray = (array) ($this->schema['attributes'] ?? []);
        $attributes = $this->fieldAttributes(['data-wi-check', 'placeholder'], $attributesArray);
        $fromClass = $this->fieldClass('wi-input wi-datetimerange-from', $attributesArray);
        $toClass = $this->fieldClass('wi-input wi-datetimerange-to', $attributesArray);
        $fromName = $this->escape($name.'-from');
        $toName = $this->escape($name.'-to');
        $fromValue = $this->escape((string) ($value[0] ?? ''));
        $toValue = $this->escape((string) ($value[1] ?? ''));

        return <<<HTML
<div class="{$this->containerClass('datetimerange')}" data-wi-date-time-range="true">
    {$this->renderLabel()}
    <input type="text" id="{$baseId}-from" class="{$fromClass}" name="{$fromName}" placeholder="gg/mm/aaaa h:m" value="{$fromValue}" data-wi-check="true"{$this->labelMarker()}{$attributes}>
    <span class="wi-input-text">-</span>
    <input type="text" id="{$baseId}-to" class="{$toClass}" name="{$toName}" placeholder="gg/mm/aaaa h:m" value="{$toValue}" data-wi-check="true"{$this->labelMarker()}{$attributes}>
    {$this->renderError()}
</div>
HTML;
    }
}
