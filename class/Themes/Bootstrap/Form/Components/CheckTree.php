<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

/**
 * Renderer Bootstrap di `CheckTree`: card con search bar opzionale e
 * lista `<ul><li>` annidata data-wi-tree per integrazione con jsTree.
 */
class CheckTree extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), false);
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = (string) ($this->schema['name'] ?? '');
        $type = (string) ($this->schema['type'] ?? 'checkbox');
        $attributes = (array) ($this->schema['attributes'] ?? []);
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $value = $this->schema['value'] ?? null;
        $searchBar = !empty($this->schema['search_bar']);
        $label = $this->escape($this->resolvedLabel());
        $isRequired = !empty($attributes['required']);
        $required = $isRequired ? "wi-{$type}-required" : '';
        unset($attributes['required']);
        $attributesStr = $this->renderAttributes($attributes);

        $bar = $searchBar
            ? "<input type='text' class='form-control card-header m-0 border-0 border-bottom bg-body' placeholder='Cerca...' aria-label='Cerca...' data-wi-search='true' >"
            : '';

        $fieldName = $type === 'checkbox' ? $name.'[]' : $name;
        $inputHidden = $type === 'checkbox' ? '<input type="hidden" name="'.$this->escape($fieldName).'">' : '';
        $optionsHtml = $this->renderOptions($options, $fieldName, $value, $attributesStr);

        return <<<HTML
<div id="container-{$id}" class="w-100 wi-container-{$type} {$required}">
    <h6>{$label}</h6>
    <div class="card border mt-1">
        {$bar}
        {$inputHidden}
        <div class="card-body overflow-scroll p-2" style="max-height: 300px;" data-wi-tree="{$type}">
            {$optionsHtml}
        </div>
    </div>
</div>
HTML;
    }

    private function renderOptions(array $options, string $name, mixed $value, string $attributes): string
    {
        $html = '<ul>';

        foreach ($options as $optionValue => $optionName) {
            $optionAttribute = trim($attributes);
            $listAttribute = '';
            $childHtml = '';

            if (is_array($value)) {
                if (in_array($optionValue, $value, true)) {
                    $optionAttribute .= ' checked';
                    $listAttribute .= ' data-jstree=\'{"selected": true }\'';
                }
            } elseif ($value !== null && (string) $optionValue === (string) $value) {
                $optionAttribute .= ' checked';
                $listAttribute .= ' data-jstree=\'{"selected": true }\'';
            }

            if (is_array($optionName)) {
                $filters = is_array($optionName['filter'] ?? null) ? $optionName['filter'] : [];
                $children = is_array($optionName['child'] ?? null) ? $optionName['child'] : [];
                $optionName = (string) ($optionName['name'] ?? $optionValue);

                foreach ($filters as $key => $filterValue) {
                    $optionAttribute .= ' data-'.$this->escape((string) $key).'="'.$this->escape((string) $filterValue).'"';
                }

                if ($children !== []) {
                    $childHtml = $this->renderOptions($children, $name, $value, $attributes);
                }
            }

            $escapedValue = $this->escape((string) $optionValue);
            $escapedName = $this->escape((string) $name);
            $escapedLabel = $this->escape((string) $optionName);

            $html .= "<li id=\"{$escapedValue}\"{$listAttribute}>"
                ."<input class=\"d-none\" type=\"checkbox\" name=\"{$escapedName}\" value=\"{$escapedValue}\" {$optionAttribute}>"
                ."{$escapedLabel}{$childHtml}</li>";
        }

        $html .= '</ul>';

        return $html;
    }
}
