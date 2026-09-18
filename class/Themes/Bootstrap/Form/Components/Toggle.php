<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

use Wonder\Themes\Bootstrap\Form\Field;

/**
 * Renderer Bootstrap dell'interruttore: `form-check form-switch` con
 * etichetta e descrizione. Il campo nascosto prima della casella manda il
 * valore "spento" quando l'interruttore è staccato.
 */
class Toggle extends Field
{
    public function render($class): string
    {
        $this->schema = (array) ($class->schema ?? []);

        return $this->renderField($this->renderInput(), false);
    }

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

        $attributes = (array) ($this->schema['attributes'] ?? []);
        unset($attributes['required']);
        $attributeString = $this->renderAttributes($attributes);

        $label = $this->escape($this->resolvedLabel());
        $description = trim((string) ($this->schema['description'] ?? ''));
        $descriptionHtml = $description === ''
            ? ''
            : '<span class="d-block text-body-secondary small">'.$this->escape($description).'</span>';

        return <<<HTML
<input type="hidden" name="{$name}" value="{$offValue}">
<div class="form-check form-switch">
    <input class="form-check-input" type="checkbox" role="switch" id="{$id}" name="{$name}" value="{$onValue}"{$checked} {$attributeString}>
    <label class="form-check-label" for="{$id}">{$label}{$descriptionHtml}</label>
</div>
HTML;
    }

    /** L'etichetta sta dentro l'interruttore: niente `<label>` sopra il campo. */
    protected function renderLabel(): string
    {
        return '';
    }
}
