<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Elements\Form\Components\Date as DateElement;
use Wonder\Elements\Form\Components\DatePicker;
use Wonder\Elements\Form\Components\InputDatetime;
use Wonder\Elements\Form\Components\InputNumber;
use Wonder\Elements\Form\Components\InputPrice;
use Wonder\Elements\Form\Components\InputText;
use Wonder\Elements\Form\Components\Select;
use Wonder\Themes\Bootstrap\Form\Field;

/**
 * Renderer Bootstrap di `SortableInput`. Replica 1:1 il markup del
 * legacy `sortableInput()` ma compone i campi cella tramite gli
 * Element (`InputText`, `InputNumber`, `Select`, …) invece dell'HTML
 * inline.
 */
class SortableInput extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $title = $this->escape((string) ($this->schema['title'] ?? ''));
        $columns = is_array($this->schema['columns'] ?? null) ? $this->schema['columns'] : [];
        $rows = $this->schema['rows'] ?? null;

        $rowsHtml = '';

        if (is_array($rows) && $rows !== []) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rowsHtml .= $this->renderRow($columns, $row, mode: 'data');
            }
        } else {
            # prima riga vuota di default (replica il branch else del legacy)
            $rowsHtml .= $this->renderRow($columns, ['position' => 1], mode: 'default');
        }

        $templateHtml = $this->renderRow($columns, [], mode: 'template');

        $rawId = (string) ($this->schema['id'] ?? '');

        return <<<HTML
<div class="row g-3">
    <div class="col-12"><h5>{$title}</h5></div>
    <div class="col-12 mt-0">
        <div id="{$id}" class="row g-3 mt-0">
            {$rowsHtml}
            <div id="copy-line-{$id}" class="col-12 visually-hidden">
                {$templateHtml}
            </div>
        </div>
        <div class="col-12">
            <div class="btn btn-secondary float-end" onclick="copyRow(document.querySelector('#{$id}'), document.querySelector('#copy-line-{$id}'));" role="button" data-bs-toggle="tooltip" data-bs-title="Aggiungi linea"><i class="bi bi-plus-lg"></i></div>
        </div>
    </div>
</div>
<script> rowSetArrow(document.querySelector('#{$id}')); </script>
HTML;
    }

    /**
     * Rendering di una singola riga.
     *
     * - mode='data'     → riga proveniente da $rows (con id+position espliciti)
     * - mode='default'  → prima riga vuota (no input hidden id, position=1)
     * - mode='template' → row template (no hidden position, attribute=data-wi-attribute)
     */
    private function renderRow(array $columns, array $row, string $mode): string
    {
        $upDownButtons = $this->buttonUp().$this->buttonDown();
        $deleteButton = $this->buttonDelete();

        if ($mode === 'data') {
            $id = $this->escape((string) ($row['id'] ?? ''));
            $position = (int) ($row['position'] ?? 0);
            $hiddenInputs = '<input type="hidden" name="id[]" value="'.$id.'"><input type="hidden" name="position[]" value="'.$position.'">';
            $orderClass = ' order-'.$position;
        } elseif ($mode === 'default') {
            $hiddenInputs = '<input type="hidden" name="position[]" value="1">';
            $orderClass = ' order-1';
        } else {
            $hiddenInputs = '<input type="hidden" name="position[]" value="">';
            $orderClass = '';
        }

        $cells = '';

        foreach ($columns as $columnName => $column) {
            if (!is_array($column)) {
                continue;
            }

            $col = (int) ($column['col'] ?? 1);
            $cellHtml = $this->renderCell((string) $columnName, $column, $row, $mode);
            $cells .= "<div class=\"col-{$col}\">{$cellHtml}</div>";
        }

        return <<<HTML
<div class="col-12 wi-copy-row{$orderClass}">
    {$hiddenInputs}
    <div class="row g-2">
        <div class="col-1">{$upDownButtons}</div>
        {$cells}
        <div class="col-1">{$deleteButton}</div>
    </div>
</div>
HTML;
    }

    private function renderCell(string $columnName, array $column, array $row, string $mode): string
    {
        $label = (string) ($column['label'] ?? '');
        $type = (string) ($column['type'] ?? 'text');
        $option = is_array($column['option'] ?? null) ? $column['option'] : [];
        $version = $column['version'] ?? null;
        $rawAttribute = (string) ($column['attribute'] ?? '');
        $value = $mode === 'data'
            ? ($row[$columnName] ?? null)
            : ($column['value'] ?? null);

        $inputName = $columnName.'[]';

        # Il template usa il valore di attribute come `data-wi-attribute="…"`
        # invece di applicarlo direttamente: la riga clonata da copyRow()
        # ricostruisce gli attributi reali a runtime.
        $attributeString = $mode === 'template'
            ? 'data-wi-attribute="'.$this->escape($rawAttribute).'"'
            : $rawAttribute;

        $attributes = \Wonder\App\Support\AttributeString::parse($attributeString);

        $element = match ($type) {
            'text'      => new InputText($inputName),
            'number'    => new InputNumber($inputName),
            'price'     => new InputPrice($inputName),
            'select'    => (new Select($inputName))->options($option),
            'date'      => new DatePicker($inputName),
            'date-time' => new InputDatetime($inputName),
            default     => new InputText($inputName),
        };

        $element->label($label)->value($value);

        if ($element instanceof InputText) {
            $element->placeholder($label);
        }

        if ($type === 'select' && $version !== null) {
            # version='old' → legacy_container wrap
            if ($version === 'old' && method_exists($element, 'legacyContainer')) {
                $element->legacyContainer();
            }
        }

        $element->attributes($attributes);

        return $element->render();
    }

    private function buttonUp(): string
    {
        return "<button type='button' class='btn btn-light btn-sm wi-arrow-up' onclick=\"rowOrder(this.parentElement.parentElement.parentElement, 'up')\" style='font-size: .8em;' data-bs-toggle='tooltip' data-bs-title='Sposta linea su'>"
            ."<i class='bi bi-chevron-up'></i></button>";
    }

    private function buttonDown(): string
    {
        return "<button type='button' class='btn btn-light btn-sm wi-arrow-down' onclick=\"rowOrder(this.parentElement.parentElement.parentElement, 'down')\" style='font-size: .8em;' data-bs-toggle='tooltip' data-bs-title='Sposta linea giù'>"
            ."<i class='bi bi-chevron-down'></i></button>";
    }

    private function buttonDelete(): string
    {
        return "<button type='button' class='btn btn-danger btn-sm float-end' onclick=\"rowRemoveModal(this.parentElement.parentElement.parentElement)\" data-bs-toggle='tooltip' data-bs-title='Elimina linea'>"
            ."<i class='bi bi-trash3'></i></button>";
    }
}
