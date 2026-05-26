<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Support\Text\Random;
use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer Wonder di `CheckGroup` (e di rimando `CheckTree`).
 *
 * Replica il markup del frontend storico (`checkbox()` in
 * `frontend/input.php`): contenitore `wi-input-container checkbox
 * compiled` con lista `wi-checkbox-list` di `wi-checkbox-container`.
 * Il container ha sempre la classe `checkbox` anche quando il `type`
 * effettivo è `radio` — è il `<input type>` interno a fare la
 * differenza visiva.
 *
 * Per le option che arrivano come array, supporta sia lo shape del
 * backend (`['name' => …, 'filter' => …]`) sia quello del frontend
 * (`['label' => …, 'attribute' => …]`).
 */
class CheckGroup extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $type = (string) ($this->schema['type'] ?? 'checkbox');
        $name = (string) ($this->schema['name'] ?? '');
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $value = $this->schema['value'] ?? null;
        $label = $this->resolvedLabel();
        $attributes = (array) ($this->schema['attributes'] ?? []);
        $attributesStr = $this->renderAttributes($attributes);
        $fieldName = $type === 'checkbox' ? $name.'[]' : $name;

        $labelHtml = $label !== '' ? "<div class=\"wi-label\">{$this->escape($label)}</div>" : '';
        $optionsHtml = '';

        foreach ($options as $optionValue => $optionLabel) {
            $optionAttribute = '';
            $checkboxLabel = '';

            if (is_array($optionLabel)) {
                if (isset($optionLabel['label'])) {
                    # frontend shape: ['label' => …, 'attribute' => …]
                    $checkboxLabel = (string) $optionLabel['label'];
                    $optionAttribute = (string) ($optionLabel['attribute'] ?? '');
                } else {
                    # backend shape: ['name' => …, 'filter' => …]
                    $checkboxLabel = (string) ($optionLabel['name'] ?? $optionValue);
                    $filters = is_array($optionLabel['filter'] ?? null) ? $optionLabel['filter'] : [];

                    foreach ($filters as $filterKey => $filterValue) {
                        $optionAttribute .= ' data-'.$this->escape((string) $filterKey).'="'.$this->escape((string) $filterValue).'"';
                    }
                }
            } else {
                $checkboxLabel = (string) $optionLabel;
            }

            $checked = '';

            if (is_array($value)) {
                if (in_array($optionValue, $value, true)) {
                    $checked = ' checked';
                }
            } elseif ($value !== null && (string) $value === (string) $optionValue) {
                $checked = ' checked';
            }

            if (str_contains($optionAttribute, 'required')) {
                $checkboxLabel .= '*';
            }

            $optionId = strtolower((new Random('letters'))::generate(10, 'checkbox_'));

            $optionsHtml .= <<<HTML
<div class="wi-checkbox-container">
    <input type="{$this->escape($type)}" id="{$optionId}" class="wi-checkbox" name="{$this->escape($fieldName)}" value="{$this->escape((string) $optionValue)}" data-wi-check="true"{$checked} {$attributesStr} {$optionAttribute}>
    <div class="wi-checkbox-icon"><i class="bi bi-check-lg"></i></div>
    <label for="{$optionId}" class="wi-checkbox-label unselectable">{$this->escape($checkboxLabel)}</label>
</div>
HTML;
        }

        return <<<HTML
<div class="wi-input-container checkbox compiled">
    {$labelHtml}
    <div class="wi-checkbox-list">
        {$optionsHtml}
    </div>
</div>
HTML;
    }
}
