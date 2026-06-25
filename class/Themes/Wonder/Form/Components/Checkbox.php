<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer Wonder del singolo checkbox boolean.
 *
 * Usa il pattern markup di `wonder-image/lib`:
 *  - container `wi-input-container checkbox`
 *  - `<input type="hidden" name="…">` per inviare un valore anche
 *    quando il checkbox è deselezionato
 *  - `wi-checkbox-list > wi-checkbox-container` con icona `wi-checkbox-icon`
 *    e label `wi-checkbox-label`
 *
 * Differenze rispetto al backend Bootstrap (`form-check-input`): il
 * frontend Wonder usa lo stile custom della lib, quindi nessuna classe
 * `form-control`/`input-group`.
 */
class Checkbox extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        # `resolvedLabel()` aggiunge già il suffisso `*` se l'attributo
        # `required` è settato — non ripeterlo qui (`Accetto i termini**`).
        $label = $this->escape($this->resolvedLabel());
        # `data-wi-check="true"` arriva già dagli attributes di default
        # di `Field::__construct` — non hard-codarlo nel markup o si
        # duplica nell'output.
        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $checked = !empty($this->schema['attributes']['checked']) ? ' checked' : '';

        return <<<HTML
<input type="hidden" name="{$name}">
<div class="{$this->containerClass('checkbox')}">
    <div class="wi-checkbox-list">
        <div class="wi-checkbox-container">
            <input type="checkbox" id="{$id}" class="wi-checkbox" name="{$name}" value="true"{$checked} {$attributes}>
            <div class="wi-checkbox-icon"><i class="bi bi-check-lg"></i></div>
            <label for="{$id}" class="wi-checkbox-label unselectable">{$label}</label>
        </div>
    </div>
    {$this->renderError()}
</div>
HTML;
    }
}
