<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Toggle Si/No con 3 stati: null (nessuna scelta), true, false. Renderizzato
 * come due bottoni `btn-check` accoppiati a un hidden con il valore "null"
 * di default, così il form trasmette sempre un valore (l'utente può
 * deselezionare entrambi i bottoni).
 */
class CheckBoolean extends Field
{
    public string $type = 'checkbox';

    /**
     * Tripla $valueNull/$valueTrue/$valueFalse — valori effettivamente
     * postati dal form (es. `['', 'true', 'false']` di default ma può
     * essere `['', 'yes', 'no']` per dataset alternativi).
     */
    public function values(string $null, string $true, string $false): self
    {
        return $this->schema('boolean_values', [$null, $true, $false]);
    }

    public function trueLabel(string $label): self
    {
        return $this->schema('true_label', $label);
    }

    public function falseLabel(string $label): self
    {
        return $this->schema('false_label', $label);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
