<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Support\Text\Random;
use Wonder\Themes\Concerns\RendersOptionVisual;
use Wonder\Themes\Support\PageAssets;
use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer Wonder di `CheckGroup` (e di rimando `CheckTree`).
 *
 * Replica il markup del frontend storico (`checkbox()` in
 * `frontend/input.php`): contenitore `wi-input-container checkbox
 * compiled` con lista `wi-checkbox-list` di `wi-checkbox-container`.
 * Il container ha sempre la classe `checkbox` anche quando il `type`
 * effettivo è `radio` — è il `<input type>` interno a fare la
 * differenza visiva.
 *
 * Con `pills()` le voci diventano pillole in linea (`wi-check-pill`): la
 * lib non ha uno stile per le pillole, quindi il piccolo CSS esce una volta
 * per pagina da `PageAssets`, scritto con i token della lib. Lo stesso CSS
 * dà a ogni voce della lista un contesto posizionato che contiene i float:
 * le regole della lib danno a input e label `float: left` e alla spunta
 * `position: absolute` senza offset, e senza quel contesto le voci si
 * sovrappongono. In entrambe le modalità l'opzione può portare un segno
 * davanti al nome (`color`, `icon`, `image`), come nel backend.
 *
 * Per le option che arrivano come array, supporta sia lo shape del
 * backend (`['name' => …, 'filter' => …]`) sia quello del frontend
 * (`['label' => …, 'attribute' => …]`).
 */
class CheckGroup extends Field
{
    use RendersOptionVisual;

    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderInput();
    }

    public function renderInput(): string
    {
        $type = (string) ($this->schema['type'] ?? 'checkbox');
        $name = (string) ($this->schema['name'] ?? '');
        $options = is_array($this->schema['options'] ?? null) ? $this->schema['options'] : [];
        $value = $this->schema['value'] ?? null;
        $label = $this->resolvedLabel();
        $fieldName = $type === 'checkbox' ? $name.'[]' : $name;
        $pills = !empty($this->schema['pills']);

        $labelHtml = $label !== '' ? "<div class=\"wi-label\">{$this->escape($label)}</div>" : '';

        if ($pills) {
            // Senza etichetta nello schema niente titolo: da solo l'asterisco
            // dell'obbligatorio non lo è.
            if (trim((string) ($this->schema['label'] ?? '')) === '') {
                $labelHtml = '';
            }

            return PageAssets::once('wi-check-group', $this->style()).<<<HTML
<div class="wi-input-container checkbox compiled">
    {$labelHtml}
    <div class="wi-check-pills">
        {$this->renderPills($options, $type, $fieldName, $value)}
    </div>
</div>
HTML;
        }

        return PageAssets::once('wi-check-group', $this->style()).<<<HTML
<div class="wi-input-container checkbox compiled">
    {$labelHtml}
    <div class="wi-checkbox-list">
        {$this->renderOptions($options, $type, $fieldName, $value)}
    </div>
</div>
HTML;
    }

    private function renderOptions(array $options, string $type, string $fieldName, mixed $value): string
    {
        $html = '';
        $inputClass = $this->fieldClass('wi-checkbox');

        foreach ($options as $optionValue => $optionLabel) {
            [$checkboxLabel, $optionAttribute] = $this->normalizeOption($optionValue, $optionLabel);
            $checked = $this->isChecked($optionValue, $value) ? ' checked' : '';
            $attributesStr = $this->fieldAttributes($checked === '' ? ['data-wi-check'] : ['data-wi-check', 'checked']);
            $visual = $this->optionVisual($optionLabel);
            $optionId = $this->optionId();

            $html .= <<<HTML
<div class="wi-checkbox-container">
    <input type="{$this->escape($type)}" id="{$optionId}" class="{$inputClass}" name="{$this->escape($fieldName)}" value="{$this->escape((string) $optionValue)}" data-wi-check="true"{$checked}{$attributesStr}{$optionAttribute}>
    <div class="wi-checkbox-icon"><i class="bi bi-check-lg"></i></div>
    <label for="{$optionId}" class="wi-checkbox-label unselectable">{$visual}{$this->escape($checkboxLabel)}</label>
</div>
HTML;
        }

        return $html;
    }

    /**
     * Le voci come pillole: la spunta è il bottone stesso. L'`<input>` resta
     * nel flusso (trasparente, sopra la pillola) così tastiera e lettori di
     * schermo lo vedono; lo stato si legge dal fratello `label`.
     */
    private function renderPills(array $options, string $type, string $fieldName, mixed $value): string
    {
        $html = '';
        $inputClass = $this->fieldClass('wi-check-pill-input');

        foreach ($options as $optionValue => $optionLabel) {
            [$pillLabel, $optionAttribute] = $this->normalizeOption($optionValue, $optionLabel);
            $checked = $this->isChecked($optionValue, $value) ? ' checked' : '';
            $attributesStr = $this->fieldAttributes($checked === '' ? ['data-wi-check'] : ['data-wi-check', 'checked']);
            $visual = $this->optionVisual($optionLabel);
            $optionId = $this->optionId();

            $html .= <<<HTML
<div class="wi-check-pill">
    <input type="{$this->escape($type)}" id="{$optionId}" class="{$inputClass}" name="{$this->escape($fieldName)}" value="{$this->escape((string) $optionValue)}" data-wi-check="true"{$checked}{$attributesStr}{$optionAttribute}>
    <label for="{$optionId}" class="wi-check-pill-label unselectable">{$visual}{$this->escape($pillLabel)}</label>
</div>
HTML;
        }

        return $html;
    }

    /**
     * Nome e attributi di un'opzione, dallo shape del frontend
     * (`['label' => …, 'attribute' => …]`), da quello del backend
     * (`['name' => …, 'filter' => …]`) o da una semplice stringa.
     *
     * @return array{0: string, 1: string} etichetta (con `*` se obbligatoria) e attributi già escapati, con lo spazio iniziale
     */
    private function normalizeOption(int|string $optionValue, mixed $optionLabel): array
    {
        $label = '';
        $attribute = '';

        if (is_array($optionLabel)) {
            if (isset($optionLabel['label'])) {
                $label = (string) $optionLabel['label'];
                $attribute = trim((string) ($optionLabel['attribute'] ?? ''));
                $attribute = $attribute === '' ? '' : ' '.$attribute;
            } else {
                $label = (string) ($optionLabel['name'] ?? $optionValue);
                $filters = is_array($optionLabel['filter'] ?? null) ? $optionLabel['filter'] : [];

                foreach ($filters as $filterKey => $filterValue) {
                    $attribute .= ' data-'.$this->escape((string) $filterKey).'="'.$this->escape((string) $filterValue).'"';
                }
            }
        } else {
            $label = (string) $optionLabel;
        }

        if (str_contains($attribute, 'required')) {
            $label .= '*';
        }

        return [$label, $attribute];
    }

    /** Il confronto con `value()` è stretto per le liste e per stringa per il valore singolo. */
    private function isChecked(int|string $optionValue, mixed $value): bool
    {
        if (is_array($value)) {
            return in_array($optionValue, $value, true);
        }

        return $value !== null && (string) $value === (string) $optionValue;
    }

    private function optionId(): string
    {
        return strtolower((new Random('letters'))::generate(10, 'checkbox_'));
    }

    /**
     * Il CSS del gruppo, con i token della lib (`--input-*`, `--tx-color`):
     * un campo nello stesso tema segue i colori del sito.
     */
    private function style(): string
    {
        return <<<'HTML'
<style data-wi-check-group-style>
.wi-checkbox-container { position: relative; display: flow-root; }
.wi-check-pills { display: flex; flex-wrap: wrap; gap: .5rem; clear: both; padding: 0 calc(16px + var(--input-border-right, 1px)) 0 calc(16px + var(--input-border-left, 1px)); }
.wi-input-container.checkbox .wi-label + .wi-check-pills { padding-top: .25rem; }
.wi-check-pill { position: relative; display: inline-flex; }
.wi-check-pill-input { position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; opacity: 0; cursor: pointer; z-index: 1; }
.wi-check-pill-label { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .9rem; border: var(--input-border-bottom, 1px) solid var(--input-border-color); border-radius: 999px; background: var(--input-bg-color); color: var(--tx-color); font-size: .875rem; line-height: 1.25; cursor: pointer; transition: .1s; }
.wi-check-pill-label .wi-option-visual { flex: none; }
.wi-check-pill-input:hover + .wi-check-pill-label { border-color: var(--input-border-focus); }
.wi-check-pill-input:checked + .wi-check-pill-label { border-color: var(--input-border-focus); background: var(--input-border-focus); color: #fff; }
.wi-check-pill-input:focus-visible + .wi-check-pill-label { outline: 2px solid var(--input-border-focus); outline-offset: 2px; }
.wi-check-pill-input:disabled { cursor: not-allowed; }
.wi-check-pill-input:disabled + .wi-check-pill-label { opacity: .5; cursor: not-allowed; }
</style>
HTML;
    }
}
