<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\CheckBoolean as CheckBooleanElement;
use Wonder\Elements\Form\Field as ElementField;

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

    protected function element(): ElementField
    {
        $context = (array) ($this->schema['context'] ?? []);
        $values = is_array($context['boolean_values'] ?? null) ? $context['boolean_values'] : ['', 'true', 'false'];
        $values = array_pad($values, 3, '');

        $element = (new CheckBooleanElement($this->name))
            ->values((string) $values[0], (string) $values[1], (string) $values[2])
            ->value($this->schema['value'] ?? null);

        if (isset($context['true_label']) && is_string($context['true_label'])) {
            $element->trueLabel($context['true_label']);
        }

        if (isset($context['false_label']) && is_string($context['false_label'])) {
            $element->falseLabel($context['false_label']);
        }

        return $element;
    }
}
