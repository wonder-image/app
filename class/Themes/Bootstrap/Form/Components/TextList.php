<?php

namespace Wonder\Themes\Bootstrap\Form\Components;

/**
 * Tema Bootstrap (backend): il combobox `TextList` non ha il comportamento
 * combobox del tema Wonder, quindi degrada a un `<select>` ricercabile
 * (`data-wi-select-search`, gestito dal JS backend di wonder-image/lib).
 * Riusa integralmente il markup del Select Bootstrap: stessa semantica
 * value/label, in più il filtro di ricerca.
 */
class TextList extends Select
{
    public function renderInput(): string
    {
        $attributes = is_array($this->schema['attributes'] ?? null) ? $this->schema['attributes'] : [];
        $attributes['data-wi-select-search'] = 'true';
        $this->schema['attributes'] = $attributes;

        return parent::renderInput();
    }
}
