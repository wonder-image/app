<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class Select extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->schema['value'] ?? '';
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $optionHtml = '';
        $multiple = !empty($this->schema['attributes']['multiple']);
        $inputName = $multiple ? $name.'[]' : $name;
        $inputHidden = $multiple ? '<input type="hidden" name="'.$inputName.'">' : '';

        foreach ($options as $optionValue => $optionLabel) {
            if (is_array($optionLabel)) {
                $optionLabel = (string) ($optionLabel['name'] ?? $optionValue);
            }

            $selected = '';

            if (is_array($value)) {
                $selected = in_array((string) $optionValue, array_map('strval', $value), true) ? ' selected' : '';
            } elseif ((string) $optionValue === (string) $value) {
                $selected = ' selected';
            }
            $optionHtml .= '<option value="'.$this->escape((string) $optionValue).'"'.$selected.'>'.$this->escape((string) $optionLabel).'</option>';
        }

        # Il `select` Wonder ha sempre la classe `compiled` (a differenza
        # di altri campi che la aggiungono solo se hasValue): è il JS di
        # styling che si aspetta lo stato "compiled" come baseline.
        $containerClass = $this->containerClass('select');

        if (!str_contains($containerClass, ' compiled')) {
            $containerClass .= ' compiled';
        }

        return <<<HTML
<div class="{$containerClass}" data-wi-select="true">
    {$this->renderLabel()}
    {$inputHidden}
    <select id="{$id}" name="{$inputName}" class="wi-input d-none" data-wi-label="true" {$attributes}>
        {$optionHtml}
    </select>
    {$this->renderError()}
</div>
HTML;
    }
}
