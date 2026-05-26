<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;
use Wonder\Support\Text\Random;

class TextList extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $rawName = (string) ($this->schema['name'] ?? '');
        $value = $this->schema['value'] ?? null;
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));

        $inputValue = '';
        $optionsHtml = '';
        $listValues = [];

        foreach ($options as $optionValue => $optionLabel) {
            $rowId = strtolower((new Random('letters'))::generate(10, 'value_'));
            $isSelected = $value !== null && (string) $value === (string) $optionValue;
            $checked = $isSelected ? ' checked' : '';
            $rowClass = $isSelected ? ' checked' : '';

            if ($isSelected) {
                $inputValue = (string) $optionLabel;
            }

            $listValues[] = (string) $optionLabel;

            $escapedValue = $this->escape((string) $optionValue);
            $escapedLabel = $this->escape((string) $optionLabel);

            $optionsHtml .= "<div class=\"wi-input-list-value{$rowClass}\" data-wi-list-value=\"true\">"
                ."<input id=\"{$rowId}\" data-wi-keyword=\"{$escapedLabel} {$escapedValue}\" data-wi-input=\"{$id}\" data-wi-name=\"{$escapedLabel}\" type=\"radio\" name=\"{$name}\" value=\"{$escapedValue}\"{$checked}>"
                ."{$escapedLabel}"
                ."</div>";
        }

        $escapedInputValue = $this->escape($inputValue);
        $listArray = $this->escape(strtolower(str_replace("'", '', implode('|', $listValues))));

        return <<<HTML
<div class="{$this->containerClass('text-list')}">
    {$this->renderLabel()}
    <input type="text" id="{$id}" class="wi-input {$rawName}-value" value="{$escapedInputValue}" data-wi-label="true" data-wi-name="{$rawName}-text" data-wi-list-input="true" data-wi-list-array="{$listArray}" {$attributes}>
    {$this->renderError()}
    <div id="list_{$id}" class="wi-input-list no-scrollbar">
        {$optionsHtml}
    </div>
</div>
HTML;
    }
}
