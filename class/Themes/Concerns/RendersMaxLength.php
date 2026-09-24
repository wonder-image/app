<?php

namespace Wonder\Themes\Concerns;

/**
 * Attributo `maxlength` degli input di testo, uguale nei due temi.
 *
 * Il limite arriva dallo schema dell'Element (`InputText::maxLength()`, che
 * scrive `max-length`). Se gli attributi liberi portano già un `maxlength`
 * (per esempio `->attribute('maxlength="10"')`) vince quello e qui non si
 * emette niente, così l'attributo non esce due volte.
 */
trait RendersMaxLength
{
    protected function renderMaxLength(): string
    {
        $length = $this->schema['max-length'] ?? null;

        if (!is_numeric($length) || (int) $length <= 0) {
            return '';
        }

        foreach (array_keys((array) ($this->schema['attributes'] ?? [])) as $key) {
            if (is_string($key) && strtolower($key) === 'maxlength') {
                return '';
            }
        }

        return ' maxlength="'.(int) $length.'"';
    }
}
