<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class CheckGroup extends Field
{
    public function renderInput(): string
    {
        $type = (string) ($this->schema['type'] ?? 'checkbox');
        $name = (string) ($this->schema['name'] ?? '');
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $value = $this->schema['value'] ?? null;
        $searchBar = !empty($this->schema['search_bar']);
        $fieldName = $type === 'checkbox' ? $name.'[]' : $name;
        $hidden = $type === 'checkbox' ? '<input type="hidden" name="'.$this->escape($fieldName).'">' : '';
        $search = $searchBar ? '<input type="text" class="wi-input" placeholder="Cerca..." data-wi-search="true">' : '';
        $items = '';

        foreach ($options as $optionValue => $optionLabel) {
            if (is_array($optionLabel)) {
                $optionLabel = (string) ($optionLabel['name'] ?? $optionValue);
            }

            $checked = '';

            if (is_array($value)) {
                $checked = in_array($optionValue, $value, true) ? ' checked' : '';
            } elseif ($value !== null && (string) $value === (string) $optionValue) {
                $checked = ' checked';
            }

            $items .= '<label class="wi-label d-b"><input type="'.$this->escape($type).'" name="'.$this->escape($fieldName).'" value="'.$this->escape((string) $optionValue).'"'.$checked.'> '.$this->escape((string) $optionLabel).'</label>';
        }

        return <<<HTML
<div class="{$this->containerClass($type)}">
    {$this->renderLabel()}
    {$hidden}
    {$search}
    <div class="wi-input-group">{$items}</div>
    {$this->renderError()}
</div>
HTML;
    }
}
