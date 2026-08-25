<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Lista di opzioni `[value => label]` dell'input.
 *
 * Le opzioni finiscono in `schema['options']`; al render
 * `FormFieldElementFactory::normalizeOptions()` le normalizza (gestendo anche
 * la forma estesa `['name' => ..., 'filter' => [...], 'child' => [...]]`
 * usata da `checkTree()`).
 */
trait HasOptions
{
    public function options(array $options): static
    {
        $this->schema['options'] = $options;

        return $this;
    }
}
