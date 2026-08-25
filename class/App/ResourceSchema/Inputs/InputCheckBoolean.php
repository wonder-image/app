<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Toggle Sì/No a tre stati (nessuna scelta / vero / falso).
 *
 * I tre value effettivamente postati sono configurabili con `values()`: la
 * tripla `[valueNull, valueTrue, valueFalse]` viene sempre normalizzata a
 * lunghezza 3.
 */
class InputCheckBoolean extends Input
{
    protected string $helper = 'checkBoolean';

    /**
     * @param array<int, string> $values Tripla `[valueNull, valueTrue, valueFalse]`.
     */
    public function values(array $values): static
    {
        return $this->context('boolean_values', array_pad($values, 3, ''));
    }

    public function trueLabel(string $label): static
    {
        $label = trim($label);

        return $label !== '' ? $this->context('true_label', $label) : $this;
    }

    public function falseLabel(string $label): static
    {
        $label = trim($label);

        return $label !== '' ? $this->context('false_label', $label) : $this;
    }
}
