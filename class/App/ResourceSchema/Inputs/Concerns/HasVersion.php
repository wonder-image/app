<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Variante di resa dell'input (`schema['version']`).
 *
 * Per `textarea()` seleziona il preset dell'editor rich-text
 * (`TextareaEditor::version()`); per i select `old()` forza il markup legacy.
 */
trait HasVersion
{
    public function version(?string $version): static
    {
        $this->schema['version'] = $version;

        return $this;
    }

    public function old(): static
    {
        return $this->version('old');
    }
}
