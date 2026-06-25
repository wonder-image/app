<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

class Select extends Field
{
    /**
     * Override del render del parent: per la modalità "legacy_container"
     * (vecchia API `select(..., 'old', ...)`) produciamo un wrapping
     * specifico con label sopra il select invece del pattern floating.
     */
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        if (!empty($this->schema['legacy_container'])) {
            return $this->renderLegacyContainer();
        }

        return parent::render($class);
    }

    private function renderLegacyContainer(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $label = $this->escape($this->resolvedLabel());
        $select = $this->renderInput();

        return <<<HTML
<div>
    <div id="container-{$id}" class="w-100 wi-container-select">
        <label for="{$id}" class="h6 form-label">{$label}</label>
        {$select}
    </div>
    {$this->renderError()}
</div>
HTML;
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $value = $this->schema['value'] ?? '';
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $legacy = !empty($this->schema['legacy_container']);
        $class = $this->inputClass($legacy ? 'form-select mt-1' : 'form-select');
        $multiple = !empty($this->schema['attributes']['multiple']);
        $inputName = $multiple ? $name.'[]' : $name;
        $inputHidden = $multiple ? "<input type=\"hidden\" name=\"{$inputName}\">" : '';

        $html = $inputHidden;
        $html .= "<select class=\"{$class}\" name=\"{$inputName}\" id=\"{$id}\" {$attributes}>";

        foreach ($options as $optionValue => $label) {
            $dataAttributes = '';

            if (is_array($label)) {
                $filters = is_array($label['filter'] ?? null) ? $label['filter'] : [];
                $label = (string) ($label['name'] ?? $optionValue);

                foreach ($filters as $filterKey => $filterValue) {
                    $dataAttributes .= ' data-'.$this->escape((string) $filterKey).'="'.$this->escape((string) $filterValue).'"';
                }
            }

            $selected = '';

            if (is_array($value)) {
                $selected = in_array((string) $optionValue, array_map('strval', $value), true) ? ' selected' : '';
            } elseif ((string) $optionValue === (string) $value) {
                $selected = ' selected';
            }
            $html .= '<option value="'.$this->escape((string) $optionValue).'"'.$selected.$dataAttributes.'>'
                .$this->escape((string) $label)
                .'</option>';
        }

        $html .= '</select>';

        return $html;
    }
}
