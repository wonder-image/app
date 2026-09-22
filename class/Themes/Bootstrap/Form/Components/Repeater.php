<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Input;
use Wonder\App\Support\RepeaterGroups;
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

        // Un form che si compila a mano parte con una riga pronta; uno che le
        // righe le riceve da altrove parte vuoto, o quella riga finta viene
        // postata e a valle diventa un record senza niente dentro.
        if ($value === [] && ($context['start_empty'] ?? false) !== true) {
            $value = ['row_1' => []];
        }

        $groupBy = array_values(array_filter(
            array_map(static fn ($key): string => trim((string) $key), (array) ($context['group_by'] ?? [])),
            static fn (string $key): bool => $key !== '' && RepeaterGroups::columnByKey($columns, $key) !== null
        ));

        // Il raggruppamento fisso decide da solo: una colonna, sempre quella.
        // Una colonna che non esiste non raggruppa niente, come già succede a
        // quelle dichiarate con `repeaterGroupBy()`.
        $groupFixed = trim((string) ($context['group_fixed'] ?? ''));

        if ($groupFixed !== '' && RepeaterGroups::columnByKey($columns, $groupFixed) === null) {
            $groupFixed = '';
        }

        if ($groupFixed !== '') {
            $groupBy = [$groupFixed];
            // Le frecce di riordino, con i gruppi accesi, sposterebbero una
            // riga dentro un ordine già deciso: il render le nasconde, e il
            // loro posto vuoto sballerebbe le larghezze.
            $context['sortable'] = false;
        }

        // Con una riga sola non c'è niente da raggruppare, e la barra sarebbe
        // solo un comando in più da leggere. A meno che i gruppi non siano
        // parte del significato: allora ci sono da subito.
        $groupable = $groupBy !== [] && ($groupFixed !== '' || count($value) > 1);
        $groupTemplateId = $id.'-group-template';
        $groups = $groupable ? RepeaterGroups::of($columns, $value, $groupBy) : [];
        $groupAttrs = $groupable
            ? ' data-wi-group-template="'.$this->escape($groupTemplateId).'"'
                .' data-wi-group-collapsed="'.(!empty($context['group_collapsed']) ? 'true' : 'false').'"'
                .($groupFixed !== '' ? ' data-wi-group-fixed="true"' : '')
            : '';
        // Niente tendina quando la colonna è decisa: non c'è niente da scegliere.
        $groupBarHtml = $groupable && $groupFixed === ''
            ? $this->renderGroupBar($id, $rowId, $groupTemplateId, $columns, $groupBy)
            : '';
        $groupTemplateHtml = $groupable ? $this->renderGroupTemplate($groupTemplateId, $context) : '';
        // La scelta si ricorda sul **nome** del campo, non sull'id: l'id del
        // repeater lo genera il render, cambia a ogni caricamento, e una
        // memoria con una chiave nuova ogni volta non ricorda niente.
        $groupMemory = $this->escape($name);
        $groupFixedAttr = $this->escape($groupFixed);
        $groupInitHtml = $groupable
            ? "<script>window.wiRepeaterGroupInit('{$rowId}', '{$groupTemplateId}', '{$id}-groupby', '{$groupMemory}', '{$groupFixedAttr}');</script>"
            : '';

        $rowsHtml = '';

        foreach ($value as $rowKey => $rowValue) {
            $rowsHtml .= $this->renderRow(
                $columns,
                $name,
                is_array($rowValue) ? $rowValue : [],
                (string) $rowKey,
                false,
                $context,
                $groups[(string) $rowKey] ?? []
            );
        }

        $templateHtml = $this->renderRow($columns, $name, [], '__ROW_KEY__', true, $context);
        $addButton = ($context['add_button'] ?? true) !== false;
        $addButtonHtml = $addButton
            ? '<div class="mt-2 d-flex justify-content-end">'
                .'<button type="button" class="'.$addButtonClass.'"'
                ." onclick=\"window.wiRepeaterAddRow('{$rowId}', '{$templateId}')\">"
                .'<i class="bi bi-plus-lg"></i> '.$addLabel.'</button></div>'
            : '';
        // Senza il bottone la riga resta una sola per sempre: la guardia che
        // impedisce di svuotare il contenitore non ha più senso, perché
        // nessuno potrebbe rimetterne una.
        $rowsAttrs = $groupAttrs.($addButton ? '' : ' data-wi-add-button="false"');
        // Il nome del campo sul contenitore: chi genera righe da fuori trova
        // il repeater senza dipendere da un id che cambia a ogni render.
        $nameAttr = $this->escape($name);
        // Senza etichetta niente titolo: il riquadro che contiene il repeater
        // ha già il suo, e due titoli uguali di fila si leggono male.
        $heading = trim($label) === '' ? '' : "<h6>{$label}</h6>";

        return <<<HTML
<div id="{$id}" class="w-100 wi-input-repeater" data-wi-repeater="{$nameAttr}">
    {$heading}
    {$groupBarHtml}
    <div id="{$rowId}" class="row g-2"{$rowsAttrs}>
        {$rowsHtml}
    </div>
    <template id="{$templateId}">{$templateHtml}</template>
    {$groupTemplateHtml}
    {$addButtonHtml}
    {$this->script()}
    {$groupInitHtml}
</div>
HTML;
    }

    private function renderRow(array $columns, string $name, array $rowValue, string $rowKey, bool $template, array $context, array $groupData = []): string
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

        $groupAttrs = '';

        foreach ($groupData as $columnKey => $info) {
            $columnKey = $this->escape((string) $columnKey);
            $groupAttrs .= ' data-wi-group-'.$columnKey.'="'.$this->escape((string) ($info['value'] ?? '')).'"'
                .' data-wi-group-label-'.$columnKey.'="'.$this->escape((string) ($info['label'] ?? '')).'"';
        }

        // Le colonne dichiarate avanzate escono dalla riga: stanno in un
        // blocco a tutta larghezza che si apre da un bottone.
        $advanced = array_values(array_filter(array_map(
            static fn ($key): string => trim((string) $key),
            (array) ($context['advanced'] ?? [])
        ), static fn (string $key): bool => $key !== ''));

        $html = "<div class=\"col-12 wi-repeater-row{$rowClass}\" data-wi-row-key=\"{$this->escape($rowKey)}\"{$groupAttrs}>";
        $html .= '<div class="card border-0 bg-light-subtle"><div class="card-body"><div class="row g-2 align-items-start">';

        $advancedHtml = '';
        $advancedFilled = false;

        foreach ($columns as $column) {
            [$fieldHtml, $isHidden, $col] = $this->renderColumn($column, $name, $rowValue, $rowKey, $context);

            if ($isHidden) {
                $html .= $fieldHtml;
                continue;
            }

            $key = $this->columnKey($column);

            if ($key !== '' && in_array($key, $advanced, true)) {
                $advancedHtml .= '<div class="col-'.$col.'">'.$fieldHtml.'</div>';

                if (!$template && $this->carriesValue($rowValue[$key] ?? null)) {
                    $advancedFilled = true;
                }

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
        $html .= '</div></div>';

        if ($advancedHtml !== '') {
            $label = trim((string) ($context['advanced_label'] ?? '')) ?: 'Compila le informazioni avanzate';
            $open = $advancedFilled ? '' : ' d-none';
            $chevron = $advancedFilled ? 'bi-chevron-up' : 'bi-chevron-down';

            $html .= '<div class="col-12">'
                .'<button type="button" class="btn btn-link btn-sm px-0 text-decoration-none wi-repeater-advanced-toggle"'
                .' onclick="window.wiRepeaterToggleAdvanced(this)">'
                .'<i class="bi '.$chevron.' me-1"></i>'.$this->escape($label)
                .'</button>'
                .'</div>';
            $html .= '<div class="col-12 wi-repeater-advanced'.$open.'"><div class="row g-2 align-items-start">'
                .$advancedHtml
                .'</div></div>';
        }

        $html .= '</div></div></div></div>';

        return $html;
    }

    /** La chiave di una colonna, qualunque delle due forme abbia. */
    private function columnKey(mixed $column): string
    {
        if ($column instanceof Input) {
            return trim((string) $column->name);
        }

        return is_array($column) ? trim((string) ($column['name'] ?? '')) : '';
    }

    /** Se una casella avanzata porta già qualcosa: allora la riga nasce aperta. */
    private function carriesValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }

        if ($value === null || is_bool($value)) {
            return $value === true;
        }

        return trim((string) $value) !== '';
    }

    private function renderColumn(mixed $column, string $name, array $rowValue, string $rowKey, array $context): array
    {
        if ($column instanceof Input) {
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

    /**
     * Colonna dichiarata in forma di array (`['name' => ..., 'helper' => ...]`)
     * invece che come `RepeaterColumn`.
     *
     * I type-helper di `FormField` non mutano più `$this`: ritornano l'istanza
     * `Input*` del tipo scelto (vedi `Input::morphInto()`). Il risultato del
     * `match` va quindi *riassegnato*, non scartato.
     */
    private function buildField(array $column, string $name, array $rowValue, string $rowKey, array $context): Input
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

        return match ($helper) {
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

    /**
     * Quante delle dodici colonne prende una casella.
     *
     * Chi non dichiara niente prende tutto lo spazio che resta: una riga di
     * una casella sola non ha bisogno di dirlo. Chi dichiara un numero lo
     * ottiene, uno compreso: `columnSpan(1)` vale un dodicesimo, non tutta la
     * riga. Il non dichiarato si riconosce da `hasExplicitColumnSpan()`, non
     * dal valore: il valore di partenza è già `1`. Prima l'uno contava come
     * "non dichiarato" e prezzi, quantità e stati — le caselle che stanno in
     * un dodicesimo — venivano fuori larghi quanto la riga, uno sotto
     * l'altro.
     */
    private function resolvedColumnWidth(Input $field): int
    {
        $span = $field->columnSpan['default'] ?? null;

        if (!$field->hasExplicitColumnSpan() || !is_numeric($span)) {
            return 11;
        }

        return max(1, min(12, (int) $span));
    }

    /** La barra "Raggruppa per": una voce per colonna dichiarata, più "Nessuno". */
    private function renderGroupBar(string $id, string $rowsId, string $templateId, array $columns, array $groupBy): string
    {
        $options = '<option value="">Nessuno</option>';

        foreach ($groupBy as $key) {
            $column = RepeaterGroups::columnByKey($columns, $key);
            $label = RepeaterGroups::labelOfColumn($column);
            $options .= '<option value="'.$this->escape($key).'">'
                .$this->escape($label !== '' ? $label : $key).'</option>';
        }

        return '<div class="wi-repeater-groupbar d-flex align-items-center gap-2 mb-2">'
            .'<label class="form-label mb-0 small text-body-secondary" for="'.$this->escape($id).'-groupby">Raggruppa per</label>'
            .'<select id="'.$this->escape($id).'-groupby" class="form-select form-select-sm w-auto wi-repeater-groupby"'
            .' onchange="window.wiRepeaterGroupApply(\''.$rowsId.'\', \''.$templateId.'\', this.value)">'
            .$options
            .'</select></div>';
    }

    /**
     * Il modello della testata di gruppo.
     *
     * Sta in un `<template>` e non fra le righe di proposito: una testata nel
     * contenitore sarebbe una riga finta, che il posting e i conteggi
     * dovrebbero imparare a saltare.
     */
    private function renderGroupTemplate(string $templateId, array $context): string
    {
        $command = is_array($context['group_command'] ?? null) ? $context['group_command'] : [];
        $countLabel = is_array($context['group_count_label'] ?? null) ? $context['group_count_label'] : [];
        $singular = $this->escape((string) ($countLabel['singular'] ?? 'riga'));
        $plural = $this->escape((string) ($countLabel['plural'] ?? 'righe'));
        $commandHtml = '';

        if (($command['column'] ?? '') !== '') {
            $commandHtml = '<div class="ms-auto wi-repeater-group-command" style="max-width:14rem"'
                .' data-wi-command-column="'.$this->escape((string) $command['column']).'">'
                .'<input type="text" class="form-control form-control-sm"'
                .' placeholder="'.$this->escape((string) ($command['label'] ?? '')).'"'
                .' oninput="window.wiRepeaterGroupCommand(this)">'
                .'</div>';
        }

        return '<template id="'.$this->escape($templateId).'">'
            .'<div class="col-12 wi-repeater-group-header"'
            .' data-wi-count-singular="'.$singular.'" data-wi-count-plural="'.$plural.'">'
            .'<div class="card border-0 bg-body-secondary"><div class="card-body py-2 d-flex align-items-center gap-2">'
            .'<button type="button" class="btn btn-sm btn-link text-decoration-none p-0 wi-repeater-group-toggle"'
            .' onclick="window.wiRepeaterGroupToggle(this)"><i class="bi bi-chevron-down"></i></button>'
            .'<strong class="wi-repeater-group-label"></strong>'
            .'<span class="small text-body-secondary wi-repeater-group-count"></span>'
            .$commandHtml
            .'</div></div></div></template>';
    }

    private function script(): string
    {
        return <<<'HTML'
<script>
    window.wiRepeaterAddRow = window.wiRepeaterAddRow || function (containerId, templateId, rowKey) {
        const container = document.getElementById(containerId);
        const template = document.getElementById(templateId);
        if (!container || !template) return null;

        // La chiave finisce dentro i `name` dei campi: una virgoletta o una
        // parentesi quadra li spezzerebbe, e il posting diventerebbe
        // imprevedibile. Si normalizza qui, non fidandosi di chi chiama.
        rowKey = String(rowKey === null || rowKey === undefined ? '' : rowKey)
            .trim()
            .replace(/[^A-Za-z0-9_-]/g, '');

        if (rowKey === '') {
            rowKey = 'row_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
        }

        // Due righe con la stessa chiave si fondono già dentro il posting: la
        // seconda cancellerebbe la prima senza che nessuno se ne accorga.
        if (container.querySelector('.wi-repeater-row[data-wi-row-key="' + rowKey + '"]')) {
            return null;
        }

        const fragment = template.content.cloneNode(true);
        const row = fragment.querySelector('.wi-repeater-row');
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

        // La riga nuova è solo HTML: senza questo i campi che diventano un
        // widget (caricamento file, editor, albero, select con ricerca)
        // restano il campo grezzo, mentre nelle righe già in pagina no.
        if (row && typeof window.setInput === 'function') {
            window.setInput(row);
        }

        if (typeof window.wiRepeaterGroupRefresh === 'function') {
            window.wiRepeaterGroupRefresh(container);
        }

        // Chi ha chiesto la riga la vuole anche riempire.
        return row;
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

    window.wiRepeaterToggleAdvanced = window.wiRepeaterToggleAdvanced || function (button) {
        const row = button.closest('.wi-repeater-row');
        if (!row) return;
        const box = row.querySelector('.wi-repeater-advanced');
        if (!box) return;
        const aperto = box.classList.toggle('d-none') === false;
        const icona = button.querySelector('i');
        if (icona) {
            icona.classList.toggle('bi-chevron-down', !aperto);
            icona.classList.toggle('bi-chevron-up', aperto);
        }
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
            // Svuotare invece di togliere ha senso finché si può aggiungerne
            // un'altra: senza il bottone resterebbe una riga vuota per sempre.
            const canAddRows = container.dataset.wiAddButton !== 'false';

            if (canAddRows && container.querySelectorAll('.wi-repeater-row:not(.d-none)').length <= 1) {
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

            if (typeof window.wiRepeaterGroupRefresh === 'function') {
                window.wiRepeaterGroupRefresh(container);
            }
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
    /*
     * Righe raggruppate.
     *
     * Il DOM delle righe non si sposta mai: le testate si aggiungono in fondo
     * al contenitore e l'ordine visivo lo fa `order` di flexbox. Così il
     * posting, le posizioni e il riordino restano quelli di sempre, e togliere
     * il raggruppamento è azzerare due proprietà.
     */
    window.wiRepeaterGroupValue = window.wiRepeaterGroupValue || function (row, columnKey) {
        const field = row.querySelector('[name$="[' + columnKey + ']"], [name="' + columnKey + '[]"]');

        if (field) {
            const value = String(field.value === null || field.value === undefined ? '' : field.value);
            let label = value;

            if (field.tagName === 'SELECT' && field.selectedIndex >= 0) {
                label = field.options[field.selectedIndex].textContent.trim();
            }

            return { value: value, label: value === '' ? '' : label };
        }

        return {
            value: row.getAttribute('data-wi-group-' + columnKey) || '',
            label: row.getAttribute('data-wi-group-label-' + columnKey) || ''
        };
    };

    window.wiRepeaterGroupApply = window.wiRepeaterGroupApply || function (rowsId, templateId, columnKey) {
        const container = document.getElementById(rowsId);
        if (!container) return;

        columnKey = columnKey || '';
        container.dataset.wiGroupColumn = columnKey;
        container.dataset.wiGroupTemplate = templateId;

        // La memoria serve a ricordare una scelta: con la colonna fissa non
        // c'è nessuna scelta da ricordare, e scriverla qui la preselezionerebbe
        // altrove, dove invece si sceglie.
        if (container.dataset.wiGroupFixed !== 'true') {
            const memory = container.dataset.wiGroupMemory || rowsId;
            try { window.localStorage.setItem('wi-repeater-group:' + memory, columnKey); } catch (error) {}
        }

        container.querySelectorAll(':scope > .wi-repeater-group-header').forEach(function (header) {
            header.remove();
        });

        const rows = Array.prototype.slice.call(container.querySelectorAll(':scope > .wi-repeater-row'));

        rows.forEach(function (row) {
            row.style.order = '';
            row.style.display = '';
            row.querySelectorAll('.wi-repeater-move-up, .wi-repeater-move-down').forEach(function (button) {
                button.classList.toggle('d-none', columnKey !== '');
            });
        });

        if (columnKey === '') return;

        const template = document.getElementById(templateId);
        if (!template) return;

        const order = [];
        const buckets = new Map();

        rows.forEach(function (row) {
            const found = window.wiRepeaterGroupValue(row, columnKey);

            if (!buckets.has(found.value)) {
                buckets.set(found.value, { label: found.label, rows: [] });
                order.push(found.value);
            }

            buckets.get(found.value).rows.push(row);
        });

        const collapsed = container.dataset.wiGroupCollapsed === 'true';
        let position = 0;

        order.forEach(function (key) {
            const bucket = buckets.get(key);
            const fragment = template.content.cloneNode(true);
            const header = fragment.querySelector('.wi-repeater-group-header');
            const count = bucket.rows.length;

            // Un template che non è quello delle testate: meglio nessun gruppo
            // che una pagina che si ferma a metà.
            if (!header) return;

            header.dataset.wiGroupKey = key;
            header.style.order = String(position++);
            header.querySelector('.wi-repeater-group-label').textContent = bucket.label || 'Senza scelta';
            header.querySelector('.wi-repeater-group-count').textContent =
                count + ' ' + (count === 1
                    ? (header.dataset.wiCountSingular || 'riga')
                    : (header.dataset.wiCountPlural || 'righe'));

            if (collapsed) {
                header.classList.add('wi-repeater-group-closed');
                const icon = header.querySelector('.wi-repeater-group-toggle i');
                if (icon) icon.className = 'bi bi-chevron-right';
            }

            container.appendChild(fragment);

            bucket.rows.forEach(function (row) {
                row.style.order = String(position++);
                row.style.display = collapsed ? 'none' : '';
            });
        });
    };

    window.wiRepeaterGroupToggle = window.wiRepeaterGroupToggle || function (button) {
        const header = button.closest('.wi-repeater-group-header');
        if (!header) return;

        const container = header.parentElement;
        const columnKey = container.dataset.wiGroupColumn || '';
        const key = header.dataset.wiGroupKey || '';
        const closed = header.classList.toggle('wi-repeater-group-closed');

        Array.prototype.slice.call(container.querySelectorAll(':scope > .wi-repeater-row')).forEach(function (row) {
            if (window.wiRepeaterGroupValue(row, columnKey).value === key) {
                row.style.display = closed ? 'none' : '';
            }
        });

        const icon = button.querySelector('i');
        if (icon) icon.className = closed ? 'bi bi-chevron-right' : 'bi bi-chevron-down';
    };

    window.wiRepeaterGroupCommand = window.wiRepeaterGroupCommand || function (input) {
        const header = input.closest('.wi-repeater-group-header');
        const box = input.closest('.wi-repeater-group-command');
        if (!header || !box) return;

        const container = header.parentElement;
        const columnKey = container.dataset.wiGroupColumn || '';
        const target = box.dataset.wiCommandColumn || '';
        const key = header.dataset.wiGroupKey || '';
        if (target === '') return;

        Array.prototype.slice.call(container.querySelectorAll(':scope > .wi-repeater-row')).forEach(function (row) {
            if (window.wiRepeaterGroupValue(row, columnKey).value !== key) return;

            const field = row.querySelector('[name$="[' + target + ']"], [name="' + target + '[]"]');
            if (!field) return;

            window.wiRepeaterSetFieldValue(field, input.value);
        });
    };

    /*
     * Scrivere in un campo che un widget si è preso.
     *
     * I campi numerici del pannello sono di AutoNumeric: assegnare `value` gli
     * lascia lo stato vecchio, e al salvataggio riscrive lui. Il valore va
     * quindi passato alla sua API, e in forma grezza — `set('31,50')` svuota
     * il campo, `set(31.5)` no.
     */
    window.wiRepeaterSetFieldValue = window.wiRepeaterSetFieldValue || function (field, value) {
        const numeric = (typeof window.AutoNumeric !== 'undefined'
            && typeof window.AutoNumeric.getAutoNumericElement === 'function')
            ? window.AutoNumeric.getAutoNumericElement(field)
            : null;

        if (!numeric) {
            field.value = value;
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        const raw = window.wiRepeaterNumberFromText(value);

        if (raw === null) {
            numeric.clear();
            return;
        }

        numeric.set(raw);
    };

    /*
     * Il numero dietro a quello che si è scritto.
     *
     * Chi compila scrive "31,50" o "1.299,90"; qualcun altro scrive "31.50".
     * Con tutti e due i separatori l'ultimo è quello decimale.
     */
    window.wiRepeaterNumberFromText = window.wiRepeaterNumberFromText || function (value) {
        let text = String(value === null || value === undefined ? '' : value).trim();
        if (text === '') return null;

        text = text.replace(/[^0-9,.-]/g, '');
        const lastComma = text.lastIndexOf(',');
        const lastDot = text.lastIndexOf('.');

        if (lastComma > -1 && lastDot > -1) {
            const decimal = lastComma > lastDot ? ',' : '.';
            const thousands = decimal === ',' ? '.' : ',';
            text = text.split(thousands).join('');
            text = text.replace(decimal, '.');
        } else if (lastComma > -1) {
            text = text.replace(',', '.');
        }

        const number = parseFloat(text);

        return isNaN(number) ? null : number;
    };

    window.wiRepeaterGroupRefresh = window.wiRepeaterGroupRefresh || function (container) {
        if (!container || !container.dataset || !container.dataset.wiGroupColumn) return;

        window.wiRepeaterGroupApply(
            container.id,
            container.dataset.wiGroupTemplate || '',
            container.dataset.wiGroupColumn
        );
    };

    window.wiRepeaterGroupInit = window.wiRepeaterGroupInit || function (rowsId, templateId, selectId, memoryKey, fixedColumn) {
        const container = document.getElementById(rowsId);
        const select = document.getElementById(selectId);
        fixedColumn = String(fixedColumn || '');
        if (!container || (!select && fixedColumn === '')) return;

        container.dataset.wiGroupMemory = memoryKey || rowsId;

        let remembered = fixedColumn;

        // Con la colonna decisa non si legge niente da nessuna parte: quella è.
        if (fixedColumn === '') {
            try { remembered = window.localStorage.getItem('wi-repeater-group:' + container.dataset.wiGroupMemory) || ''; } catch (error) {}

            const known = Array.prototype.slice.call(select.options).some(function (option) {
                return option.value === remembered;
            });

            if (!known) remembered = '';
        }

        if (!container.dataset.wiGroupBound) {
            container.dataset.wiGroupBound = 'true';
            container.addEventListener('change', function (event) {
                const columnKey = container.dataset.wiGroupColumn || '';
                const name = event.target && event.target.name ? event.target.name : '';
                if (columnKey === '' || name === '') return;

                if (name.endsWith('[' + columnKey + ']') || name === columnKey + '[]') {
                    window.wiRepeaterGroupRefresh(container);
                }
            });
        }

        if (select) select.value = fixedColumn === '' ? remembered : '';

        container.dataset.wiGroupTemplate = templateId;
        window.wiRepeaterGroupApply(rowsId, templateId, remembered);
    };
</script>
HTML;
    }
}
