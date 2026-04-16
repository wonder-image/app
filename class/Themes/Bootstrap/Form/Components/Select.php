<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class Select extends Field
{
    public function renderInput(): string
    {
        $id = $this->schema['id'];
        $name = $this->schema['name'];
        $value = (string) ($this->schema['value'] ?? '');
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $attributes = $this->renderAttributes($this->schema['attributes']);

        $html = "<select class=\"form-select\" name=\"{$name}\" id=\"{$id}\" {$attributes}>";

        foreach ($options as $optionValue => $label) {
            $selected = ((string) $optionValue === $value) ? ' selected' : '';
            $html .= '<option value="'.htmlspecialchars((string) $optionValue, ENT_QUOTES, 'UTF-8').'"'.$selected.'>'
                .htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                .'</option>';
        }

        $html .= '</select>';

        return $html;
    }
}
