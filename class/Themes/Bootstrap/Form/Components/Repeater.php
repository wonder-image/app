<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\App\ResourceSchema\FormField;
use Wonder\Themes\Bootstrap\Form\Field;

class Repeater extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = (string) ($this->schema['name'] ?? '');
        $label = $this->escape($this->resolvedLabel());
        $value = $this->normalizeRows($this->schema['value'] ?? null);
        $context = is_array($this->schema['context'] ?? null) ? $this->schema['context'] : [];
        $columns = is_array($this->schema['columns'] ?? null) ? $this->schema['columns'] : [];
        $rowId = $id.'-rows';
        $templateId = $id.'-template';
        $addLabel = $this->escape((string) ($context['add_label'] ?? 'Aggiungi linea'));
        $addButtonClass = $this->escape((string) ($context['add_button_class'] ?? 'btn btn-secondary'));

        if ($columns === []) {
            $columns = [[
                'name' => $name,
                'label' => '',
                'helper' => 'text',
                'col' => 11,
                'attribute' => '',
                'options' => [],
                'search_bar' => false,
                'version' => null,
            ]];
        }

        if ($value === []) {
            $value = ['row_1' => []];
        }

        $rowsHtml = '';

        foreach ($value as $rowKey => $rowValue) {
            $rowsHtml .= $this->renderRow($columns, $name, is_array($rowValue) ? $rowValue : [], (string) $rowKey, false, $context);
        }

        $templateHtml = $this->renderRow($columns, $name, [], '__ROW_KEY__', true, $context);

        return <<<HTML
<div id="{$id}" class="w-100 wi-input-repeater">
    <h6>{$label}</h6>
    <div id="{$rowId}" class="row g-2">
        {$rowsHtml}
    </div>
    <template id="{$templateId}">{$templateHtml}</template>
    <div class="mt-2 d-flex justify-content-end">
        <button type="button" class="{$addButtonClass}" onclick="window.wiRepeaterAddRow('{$rowId}', '{$templateId}')"><i class="bi bi-plus-lg"></i> {$addLabel}</button>
    </div>
    {$this->script()}
</div>
HTML;
    }

    private function renderRow(array $columns, string $name, array $rowValue, string $rowKey, bool $template, array $context): string
    {
        $sortable = !empty($context['sortable']);
        $rowClass = $template ? ' d-none' : '';
        $deleteAttrs = sprintf(
            ' data-wi-delete-title="%s" data-wi-delete-text="%s" data-wi-delete-cancel-label="%s" data-wi-delete-confirm-label="%s" data-wi-delete-confirm-class="%s"',
            $this->escape((string) ($context['delete_modal_title'] ?? 'Conferma eliminazione')),
            $this->escape((string) ($context['delete_modal_text'] ?? "Confermi l'eliminazione della riga?")),
            $this->escape((string) ($context['delete_modal_cancel_label'] ?? 'Annulla')),
            $this->escape((string) ($context['delete_modal_confirm_label'] ?? 'Elimina')),
            $this->escape((string) ($context['delete_modal_confirm_class'] ?? 'btn btn-danger')),
        );

        $html = "<div class=\"col-12 wi-repeater-row{$rowClass}\" data-wi-row-key=\"{$this->escape($rowKey)}\">";
        $html .= '<div class="card border-0 bg-light-subtle"><div class="card-body"><div class="row g-2 align-items-start">';

        foreach ($columns as $column) {
            [$fieldHtml, $isHidden, $col] = $this->renderColumn($column, $name, $rowValue, $rowKey, $context);

            if ($isHidden) {
                $html .= $fieldHtml;
                continue;
            }

            $html .= '<div class="col-'.$col.'">'.$fieldHtml.'</div>';
        }

        $actionColumnClass = $sortable ? 'col-3' : 'col-1';
        $html .= "<div class=\"{$actionColumnClass} d-flex align-items-stretch\"><div class=\"d-flex flex-row gap-2 w-100\">";

        if ($sortable) {
            $html .= '<button type="button" class="btn btn-outline-secondary flex-fill wi-repeater-move-up" onclick="window.wiRepeaterMoveRowUp(this)"><i class="bi bi-chevron-up"></i></button>';
            $html .= '<button type="button" class="btn btn-outline-secondary flex-fill wi-repeater-move-down" onclick="window.wiRepeaterMoveRowDown(this)"><i class="bi bi-chevron-down"></i></button>';
        }

        $html .= '<button type="button" class="btn btn-danger flex-fill wi-repeater-delete" onclick="window.wiRepeaterRemoveRow(this)"'.$deleteAttrs.'><i class="bi bi-trash3"></i></button>';
        $html .= '</div></div></div></div></div></div>';

        return $html;
    }

    private function renderColumn(mixed $column, string $name, array $rowValue, string $rowKey, array $context): array
    {
        if ($column instanceof FormField) {
            $field = clone $column;
            $columnName = trim((string) $field->name);
            $fieldValue = $rowValue[$columnName] ?? $field->get('value');
            $inputName = !empty($context['nested'])
                ? "{$name}[{$rowKey}][{$columnName}]"
                : $columnName.'[]';

            $field->inputName($inputName)->value($fieldValue);

            return [
                $field->render('bootstrap'),
                $field->get('helper') === 'hidden',
                $this->resolvedColumnWidth($field),
            ];
        }

        $column = is_array($column) ? $column : [];
        $field = $this->buildField($column, $name, $rowValue, $rowKey, $context);

        return [
            $field->render('bootstrap'),
            ($column['helper'] ?? 'text') === 'hidden',
            max(1, min(12, (int) ($column['col'] ?? 11))),
        ];
    }

    private function buildField(array $column, string $name, array $rowValue, string $rowKey, array $context): FormField
    {
        $columnName = trim((string) ($column['name'] ?? $name));
        $helper = trim((string) ($column['helper'] ?? 'text'));
        $field = FormField::key($columnName);
        $fieldValue = $rowValue[$columnName] ?? ($column['value'] ?? null);
        $inputName = !empty($context['nested'])
            ? "{$name}[{$rowKey}][{$columnName}]"
            : $columnName.'[]';

        $field->inputName($inputName)->value($fieldValue);

        if (($column['label'] ?? '') !== '') {
            $field->label((string) $column['label']);
        }

        if (($column['attribute'] ?? '') !== '') {
            $field->attribute((string) $column['attribute']);
        }

        $options = is_array($column['options'] ?? null) ? $column['options'] : [];
        $searchBar = (bool) ($column['search_bar'] ?? false);
        $version = isset($column['version']) ? (string) $column['version'] : null;

        match ($helper) {
            'hidden' => $field->hidden(),
            'select' => $field->select($options, $version),
            'selectSearch' => $field->selectSearch($options, false, $version),
            'radio' => $field->radio($options, $searchBar),
            'checkbox' => $options !== [] ? $field->checkbox()->options($options)->searchBar($searchBar) : $field->checkbox(),
            'color' => $field->color(),
            'textarea' => $field->textarea($version),
            'textDate' => $field->textDate(),
            'dateInput' => $field->dateInput(isset($column['date_min']) ? (string) $column['date_min'] : null, isset($column['date_max']) ? (string) $column['date_max'] : null),
            'timeInput' => $field->timeInput((int) ($column['time_step'] ?? 900)),
            'url' => $field->url(),
            'email' => $field->email(),
            'phone', 'tel' => $field->phone(),
            'number' => $field->number(),
            'price' => $field->price(),
            'percentige' => $field->percentige(),
            'textDatetime' => $field->textDatetime(),
            'inputFile' => $field->file((string) ($column['file'] ?? 'image')),
            'inputFileDragDrop' => $field->fileDragDrop((string) ($column['file'] ?? 'image'), (string) ($column['uploader'] ?? 'classic')),
            'inputCountry' => $field->country(isset($column['state_field']) ? (string) $column['state_field'] : null),
            'inputStates' => $field->states(isset($column['country']) ? (string) $column['country'] : null),
            'inputPhonePrefix' => $field->phonePrefix(),
            default => $field->text(),
        };

        return $field;
    }

    private function normalizeRows(mixed $value): array
    {
        $rows = [];

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return [];
        }

        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        if ($isAssoc) {
            return $value;
        }

        $index = 1;
        foreach ($value as $row) {
            $rows['row_'.$index] = is_array($row) ? $row : [];
            $index++;
        }

        return $rows;
    }

    private function resolvedColumnWidth(FormField $field): int
    {
        $span = $field->columnSpan['default'] ?? null;

        if (!is_numeric($span)) {
            return 11;
        }

        $span = (int) $span;

        if ($span <= 1) {
            return 11;
        }

        return max(1, min(12, $span));
    }

    private function script(): string
    {
        return <<<'HTML'
<script>
    window.wiRepeaterAddRow = window.wiRepeaterAddRow || function (containerId, templateId) {
        const container = document.getElementById(containerId);
        const template = document.getElementById(templateId);
        if (!container || !template) return;
        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('.wi-repeater-row');
        const rowKey = 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
        if (row) {
            row.classList.remove('d-none');
            row.setAttribute('data-wi-row-key', rowKey);
        }
        fragment.querySelectorAll('[name]').forEach((input) => {
            input.name = input.name.replaceAll('__ROW_KEY__', rowKey);
        });
        fragment.querySelectorAll('[data-wi-row-key]').forEach((element) => {
            if (element.getAttribute('data-wi-row-key') === '__ROW_KEY__') {
                element.setAttribute('data-wi-row-key', rowKey);
            }
        });
        container.appendChild(fragment);
    };

    window.wiRepeaterEnsureDeleteModal = window.wiRepeaterEnsureDeleteModal || function (config = {}) {
        let modalEl = document.getElementById('wi-repeater-delete-modal');
        const defaults = {
            title: "Conferma eliminazione",
            text: "Confermi l'eliminazione della riga?",
            cancelLabel: "Annulla",
            confirmLabel: "Elimina",
            confirmClass: "btn btn-danger",
        };
        const options = { ...defaults, ...config };
        if (!modalEl) {
            modalEl = document.createElement('div');
            modalEl.id = 'wi-repeater-delete-modal';
            modalEl.className = 'modal fade';
            modalEl.tabIndex = -1;
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" data-wi-modal-title="true"></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0" data-wi-modal-text="true"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-wi-modal-cancel="true"></button>
                            <button type="button" data-wi-confirm-delete="true"></button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modalEl);
        }
        modalEl.querySelector('[data-wi-modal-title="true"]').textContent = options.title;
        modalEl.querySelector('[data-wi-modal-text="true"]').textContent = options.text;
        modalEl.querySelector('[data-wi-modal-cancel="true"]').textContent = options.cancelLabel;
        const confirmBtn = modalEl.querySelector('[data-wi-confirm-delete="true"]');
        confirmBtn.textContent = options.confirmLabel;
        confirmBtn.className = options.confirmClass;
        return modalEl;
    };

    window.wiRepeaterConfirmDelete = window.wiRepeaterConfirmDelete || function (onConfirm, config = {}) {
        if (!window.bootstrap || !window.bootstrap.Modal) {
            const fallbackText = config.text || "Confermi l'eliminazione della riga?";
            if (window.confirm(fallbackText)) onConfirm();
            return;
        }
        const modalEl = window.wiRepeaterEnsureDeleteModal(config);
        const confirmBtn = modalEl.querySelector('[data-wi-confirm-delete="true"]');
        const modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
        confirmBtn.onclick = function () {
            modal.hide();
            onConfirm();
        };
        modal.show();
    };

    window.wiRepeaterRemoveRow = window.wiRepeaterRemoveRow || function (button) {
        const row = button.closest('.wi-repeater-row');
        const container = row ? row.parentElement : null;
        if (!row || !container) return;
        const deleteConfig = {
            title: button.getAttribute('data-wi-delete-title') || 'Conferma eliminazione',
            text: button.getAttribute('data-wi-delete-text') || "Confermi l'eliminazione della riga?",
            cancelLabel: button.getAttribute('data-wi-delete-cancel-label') || 'Annulla',
            confirmLabel: button.getAttribute('data-wi-delete-confirm-label') || 'Elimina',
            confirmClass: button.getAttribute('data-wi-delete-confirm-class') || 'btn btn-danger',
        };
        window.wiRepeaterConfirmDelete(function () {
            if (container.querySelectorAll('.wi-repeater-row:not(.d-none)').length <= 1) {
                row.querySelectorAll('input, textarea, select').forEach((input) => {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        input.checked = false;
                    } else {
                        input.value = '';
                    }
                });
                return;
            }
            row.remove();
        }, deleteConfig);
    };

    window.wiRepeaterMoveRowUp = window.wiRepeaterMoveRowUp || function (button) {
        const row = button.closest('.wi-repeater-row');
        const previous = row ? row.previousElementSibling : null;
        if (!row || !previous) return;
        row.parentElement.insertBefore(row, previous);
    };

    window.wiRepeaterMoveRowDown = window.wiRepeaterMoveRowDown || function (button) {
        const row = button.closest('.wi-repeater-row');
        const next = row ? row.nextElementSibling : null;
        if (!row || !next) return;
        row.parentElement.insertBefore(next, row);
    };
</script>
HTML;
    }
}
