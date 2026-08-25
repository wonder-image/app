<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Plumbing condiviso fra `HasNumberFormat` (API pubblica dei tipi numerici) e
 * gli shim `@deprecated` rimasti su `FormField`. Non espone metodi pubblici:
 * serve solo a tenere in un unico posto la scrittura di `context['number']`.
 */
trait WritesNumberConfig
{
    protected function numberConfig(string $key, mixed $value): static
    {
        $number = (array) (($this->schema['context']['number'] ?? []) ?: []);
        $number[$key] = $value;

        return $this->context('number', $number);
    }

    /**
     * Posizione del simbolo: `p` = prefix, `s` = suffix (come nell'Element).
     * Valori fuori da questi due vengono ignorati silenziosamente, così il
     * DSL resta chainable e non solleva l'eccezione di `InputNumber`.
     */
    protected function numberSymbolPlacement(string $placement): static
    {
        $placement = strtolower(trim($placement));

        if (!in_array($placement, ['p', 's'], true)) {
            return $this;
        }

        return $this->numberConfig('symbol_placement', $placement);
    }
}
