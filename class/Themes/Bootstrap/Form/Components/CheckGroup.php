<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;
use Wonder\Themes\Concerns\RendersOptionVisual;

class CheckGroup extends Field
{
    use RendersOptionVisual;

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
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $value = $this->schema['value'] ?? null;
        $searchBar = !empty($this->schema['search_bar']);
        $required = !empty($this->schema['attributes']['required']) ? "wi-{$type}-required" : '';
        $label = $this->escape($this->resolvedLabel());
        $bar = $searchBar ? "<input type='text' class='form-control card-header m-0 border-0 border-bottom bg-body' placeholder='Cerca...' aria-label='Cerca...' data-wi-search='true' >" : '';
        $fieldName = $type === 'checkbox' ? $name.'[]' : $name;
        $inputHidden = $type === 'checkbox' ? '<input type="hidden" name="'.$this->escape($fieldName).'">' : '';

        if (!empty($this->schema['pills'])) {
            // Il "+" della creazione rapida è l'ultima pillola della fila.
            $pillsHtml = $this->renderPills($options, $type, $fieldName, $value, $attributes)
                .$this->inlineQuickCreateButton();

            return <<<HTML
<div id="container-{$id}" class="w-100 wi-container-{$type} wi-check-pills {$required}">
    <h6 class="small text-body-secondary mb-1">{$label}</h6>
    {$inputHidden}
    <div class="d-flex flex-wrap gap-1">
        {$pillsHtml}
    </div>
</div>
HTML;
        }

        $optionsHtml = $this->renderOptions($options, $type, $fieldName, $value, $attributes);

        return <<<HTML
<div id="container-{$id}" class="w-100 wi-container-{$type} {$required}">
    <h6>{$label}</h6>
    {$inputHidden}
    <div class="card border mt-1">
        {$bar}
        <div class="card-body overflow-scroll p-2" style="height: 120px;">
            {$optionsHtml}
        </div>
    </div>
</div>
HTML;
    }

    /**
     * Le voci come pillole: la spunta è il bottone stesso.
     *
     * Niente annidamento e niente barra di ricerca — le pillole servono agli
     * elenchi corti, quelli che si leggono tutti in una riga; per un elenco
     * lungo resta il riquadro che scorre.
     */
    private function renderPills(array $options, string $type, string $name, mixed $value, string $attributes): string
    {
        $html = '';

        foreach ($options as $optionValue => $optionName) {
            $visual = $this->optionVisual($optionName);

            if (is_array($optionName)) {
                $optionName = (string) ($optionName['name'] ?? $optionValue);
            }

            $checked = '';

            if (is_array($value)) {
                $checked = in_array((string) $optionValue, array_map('strval', $value), true) ? ' checked' : '';
            } elseif ($value !== null && (string) $value === (string) $optionValue) {
                $checked = ' checked';
            }

            $escapedValue = $this->escape((string) $optionValue);
            $escapedLabel = $this->escape((string) $optionName);
            $escapedId = $this->escape($type.'-'.$name.'-'.$optionValue);
            $escapedType = $this->escape($type);
            $escapedName = $this->escape($name);
            $optionAttributes = trim($attributes);

            $html .= <<<HTML
<input class="btn-check" type="{$escapedType}" name="{$escapedName}" value="{$escapedValue}" id="{$escapedId}" autocomplete="off" data-wi-check="true" {$optionAttributes}{$checked}>
<label class="btn btn-sm btn-outline-secondary wi-check-label user-select-none" for="{$escapedId}">{$visual}{$escapedLabel}</label>
HTML;
        }

        return $html;
    }

    private function renderOptions(array $options, string $type, string $name, mixed $value, string $attributes): string
    {
        $html = '';

        foreach ($options as $optionValue => $optionName) {
            $optionAttributes = trim($attributes);
            $childHtml = '';
            $visual = $this->optionVisual($optionName);

            if (is_array($optionName)) {
                $filters = is_array($optionName['filter'] ?? null) ? $optionName['filter'] : [];
                $children = is_array($optionName['child'] ?? null) ? $optionName['child'] : [];
                $optionName = (string) ($optionName['name'] ?? $optionValue);

                foreach ($filters as $key => $filterValue) {
                    $optionAttributes .= ' data-'.$this->escape((string) $key).'="'.$this->escape((string) $filterValue).'"';
                }

                if ($children !== []) {
                    $childHtml .= "<div class='w-100 ps-3'>";
                    $childHtml .= $this->renderOptions($children, $type, $name, $value, $attributes);
                    $childHtml .= '</div>';
                }
            }

            $checked = '';

            if (is_array($value)) {
                $checked = in_array((string) $optionValue, array_map('strval', $value), true) ? ' checked' : '';
            } elseif ($value !== null && (string) $value === (string) $optionValue) {
                $checked = ' checked';
            }

            $escapedValue = $this->escape((string) $optionValue);
            $escapedLabel = $this->escape((string) $optionName);
            $escapedId = $this->escape($type.'-'.$name.'-'.$optionValue);

            $html .= <<<HTML
<div class="w-100">
    <div id="{$this->escape($name)}-{$escapedValue}" class="form-check">
        <input class="form-check-input" type="{$this->escape($type)}" name="{$this->escape($name)}" value="{$escapedValue}" id="{$escapedId}" data-wi-check="true" {$optionAttributes}{$checked}>
        <label class="form-check-label wi-check-label user-select-none" for="{$escapedId}">{$visual}{$escapedLabel}</label>
    </div>
    {$childHtml}
</div>
HTML;
        }

        return $html;
    }
}
