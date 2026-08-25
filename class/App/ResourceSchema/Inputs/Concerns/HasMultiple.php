<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Selezione multipla: oltre a `schema['multiple']` aggiunge l'attributo HTML
 * `multiple`, perché alcuni renderer leggono l'uno e altri l'altro.
 */
trait HasMultiple
{
    public function multiple(bool $multiple = true): static
    {
        $this->schema['multiple'] = $multiple;

        return $multiple ? $this->attribute('multiple') : $this;
    }
}
