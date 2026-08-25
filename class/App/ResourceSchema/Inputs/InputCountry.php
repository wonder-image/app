<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\BuildsSelectElement;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Select ricercabile dei paesi.
 *
 * Senza `options()` esplicite usa l'elenco completo restituito da
 * `countries()`. Con `stateField()` si aggancia al campo province/stati
 * indicato: la lib ne ricarica le opzioni al cambio di paese.
 */
class InputCountry extends Input
{
    use BuildsSelectElement;
    use HasOptions;

    protected string $helper = 'inputCountry';

    /** Nome del campo province/stati da tenere sincronizzato con questo. */
    public function stateField(string $field): static
    {
        $field = trim($field);

        return $field !== '' ? $this->context('state_field', $field) : $this;
    }

    /**
     * Senza `options()` esplicite usa l'elenco completo di `countries()`.
     * Se la funzione non è caricata il campo non è renderizzabile.
     */
    protected function element(): ?ElementField
    {
        if (!function_exists('countries')) {
            return null;
        }

        $options = (array) ($this->schema['options'] ?? []);
        $select = $this->searchableSelectElement($options !== [] ? $options : countries());
        $stateField = $this->schema['context']['state_field'] ?? null;

        if (is_string($stateField) && trim($stateField) !== '') {
            $select->attr('data-wi-input-country', 'true');
            $select->attr('data-wi-input-state', trim($stateField));
        }

        return $select;
    }
}
