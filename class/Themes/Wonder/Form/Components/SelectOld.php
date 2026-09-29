<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

class SelectOld extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->schema['value'] ?? '';
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $attributes = $this->fieldAttributes(['data-wi-check']);
        $class = $this->fieldClass('wi-input');
        $optionsHtml = '';
        $i = 1;

        foreach ($options as $optionValue => $optionLabel) {
            if (is_array($optionLabel)) {
                $optionLabel = (string) ($optionLabel['name'] ?? $optionValue);
            }

            $selected = '';

            if ($value !== null && $value !== '') {
                if ((string) $optionValue === (string) $value) {
                    $selected = ' selected';
                }
            } elseif ($i === 1 && $value !== '') {
                $selected = ' selected';
            }

            $optionsHtml .= '<option value="'.$this->escape((string) $optionValue).'"'.$selected.'>'.$this->escape((string) $optionLabel).'</option>';
            $i++;
        }

        return <<<HTML
<div class="{$this->containerClass('select')}">
    {$this->renderLabel()}
    <select id="{$id}" name="{$name}" class="{$class}" data-wi-check="true" data-wi-label="true"{$attributes}>
        {$optionsHtml}
    </select>
    {$this->renderError()}
</div>
HTML;
    }
}
