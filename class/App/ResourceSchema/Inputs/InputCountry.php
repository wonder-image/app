<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;

/**
 * Select ricercabile dei paesi.
 *
 * Senza `options()` esplicite usa l'elenco completo restituito da
 * `countries()`. Con `stateField()` si aggancia al campo province/stati
 * indicato: la lib ne ricarica le opzioni al cambio di paese.
 */
class InputCountry extends Input
{
    use HasOptions;

    protected string $helper = 'inputCountry';

    /** Nome del campo province/stati da tenere sincronizzato con questo. */
    public function stateField(string $field): static
    {
        $field = trim($field);

        return $field !== '' ? $this->context('state_field', $field) : $this;
    }
}
