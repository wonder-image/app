<?php

namespace Wonder\Themes\Wonder\Form\Components;

use Wonder\Themes\Wonder\Form\Field;

/**
 * Renderer Wonder dell'interruttore, con il markup della lib: container
 * `wi-input-container switch`, campo nascosto per il valore "spento" e
 * descrizione sotto l'etichetta.
 */
class Toggle extends Field
{
    public function renderInput(): string
    {
        $id = $this->escape((string) ($this->schema['id'] ?? ''));
        $name = $this->escape((string) ($this->schema['name'] ?? ''));
        $values = is_array($this->schema['toggle_values'] ?? null)
            ? $this->schema['toggle_values']
            : ['true', 'false'];

        [$on, $off] = array_pad($values, 2, '');

        $onValue = $this->escape((string) $on);
        $offValue = $this->escape((string) $off);
        $checked = ((string) ($this->schema['value'] ?? '')) === (string) $on ? ' checked' : '';

        $attributes = $this->renderAttributes((array) ($this->schema['attributes'] ?? []));
        $label = $this->escape($this->resolvedLabel());
        $description = trim((string) ($this->schema['description'] ?? ''));
        $descriptionHtml = $description === ''
            ? ''
            : '<span class="wi-switch-description">'.$this->escape($description).'</span>';

        return <<<HTML
<input type="hidden" name="{$name}" value="{$offValue}">
<div class="{$this->containerClass('switch')}">
    <div class="wi-switch-container">
        <input type="checkbox" id="{$id}" class="wi-switch" name="{$name}" value="{$onValue}"{$checked} {$attributes}>
        <label for="{$id}" class="wi-switch-label unselectable">{$label}{$descriptionHtml}</label>
    </div>
</div>
HTML;
    }
}
