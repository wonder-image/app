<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

/**
 * Renderer Bootstrap di `CheckTree`: card con search bar opzionale e
 * lista `<ul><li>` annidata data-wi-tree per integrazione con jsTree.
 *
 * I nodi portano solo l'etichetta. Le caselle che il form posta stanno in
 * `[data-wi-tree-values]`, fuori dall'albero: jstree ridisegna i nodi dal
 * loro HTML di partenza e toglie dal DOM i figli dei nodi chiusi, quindi una
 * casella dentro un nodo perderebbe la spunta o sparirebbe dal salvataggio.
 * La lib le tiene allineate alle spunte di jstree.
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
        $escapedFieldName = $this->escape($fieldName);
        $inputHidden = $type === 'checkbox' ? '<input type="hidden" name="'.$escapedFieldName.'">' : '';
        $optionsHtml = $this->renderOptions($options, $value);
        $valuesHtml = $this->renderValues($options, $escapedFieldName, $value, $attributesStr);

        // Di quale risorsa questo albero elenca le righe: chi crea una riga
        // da un altro campo della stessa pagina lo legge per aggiungerla
        // anche qui.
        $listsResource = trim((string) ($this->schema['lists_resource'] ?? ''));
        $listsAttr = $listsResource === ''
            ? ''
            : ' data-wi-qc-resource="'.$this->escape($listsResource).'"';

        // Il campo in cui la lib scrive la voce con la stella.
        $primary = trim((string) ($this->schema['primary_field'] ?? ''));
        $primaryAttr = $primary === '' || $type !== 'checkbox'
            ? ''
            : ' data-wi-tree-primary="'.$this->escape($primary).'"';

        return <<<HTML
<div id="container-{$id}" class="w-100 wi-container-{$type} {$required}"{$listsAttr}>
    <h6>{$label}</h6>
    <div class="card border mt-1">
        {$bar}
        {$inputHidden}
        <div class="d-none" data-wi-tree-values="{$escapedFieldName}">{$valuesHtml}</div>
        <div class="card-body overflow-scroll p-2" style="max-height: 300px;" data-wi-tree="{$type}"{$primaryAttr}>
            {$optionsHtml}
        </div>
    </div>
</div>
HTML;
    }

    private function renderOptions(array $options, mixed $value): string
    {
        $html = '<ul>';

        foreach ($options as $optionValue => $optionName) {
            $listAttribute = $this->isSelected($optionValue, $value)
                ? ' data-jstree=\'{"selected": true }\''
                : '';
            $childHtml = '';

            if (is_array($optionName)) {
                $children = is_array($optionName['child'] ?? null) ? $optionName['child'] : [];
                $optionName = (string) ($optionName['name'] ?? $optionValue);

                if ($children !== []) {
                    $childHtml = $this->renderOptions($children, $value);
                }
            }

            $escapedValue = $this->escape((string) $optionValue);
            $escapedLabel = $this->escape((string) $optionName);

            $html .= "<li id=\"{$escapedValue}\"{$listAttribute}>{$escapedLabel}{$childHtml}</li>";
        }

        $html .= '</ul>';

        return $html;
    }

    /**
     * Una casella per voce, nell'ordine dell'albero: spuntate quelle scelte.
     */
    private function renderValues(array $options, string $escapedName, mixed $value, string $attributes, array &$seen = []): string
    {
        $html = '';

        foreach ($options as $optionValue => $optionName) {
            $key = (string) $optionValue;
            $optionAttribute = trim($attributes);
            $children = [];

            if ($this->isSelected($optionValue, $value)) {
                $optionAttribute .= ' checked';
            }

            if (is_array($optionName)) {
                $filters = is_array($optionName['filter'] ?? null) ? $optionName['filter'] : [];
                $children = is_array($optionName['child'] ?? null) ? $optionName['child'] : [];

                foreach ($filters as $filterKey => $filterValue) {
                    $optionAttribute .= ' data-'.$this->escape((string) $filterKey).'="'.$this->escape((string) $filterValue).'"';
                }
            }

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $escapedValue = $this->escape($key);
                $html .= "<input class=\"d-none\" type=\"checkbox\" name=\"{$escapedName}\" value=\"{$escapedValue}\" {$optionAttribute}>";
            }

            if ($children !== []) {
                $html .= $this->renderValues($children, $escapedName, $value, $attributes, $seen);
            }
        }

        return $html;
    }

    private function isSelected(int|string $optionValue, mixed $value): bool
    {
        // Le chiavi numeriche delle opzioni PHP le rende intere, i valori
        // salvati arrivano stringa: si confronta fra stringhe.
        if (is_array($value)) {
            return in_array((string) $optionValue, array_map('strval', $value), true);
        }

        return $value !== null && (string) $optionValue === (string) $value;
    }
}
