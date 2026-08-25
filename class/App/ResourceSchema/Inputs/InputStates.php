<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\BuildsSelectElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Select ricercabile delle province/stati di un paese.
 *
 * Le opzioni sono derivate dal paese impostato con `country()`; senza paese
 * la lista parte vuota e viene popolata lato client da {@see InputCountry}.
 */
class InputStates extends Input
{
    use BuildsSelectElement;

    protected string $helper = 'inputStates';

    public function country(string $country): static
    {
        $country = trim($country);

        return $country !== '' ? $this->context('country', $country) : $this;
    }

    /**
     * Le opzioni vengono da `states($country)`: senza paese la lista parte
     * vuota e viene popolata lato client da {@see InputCountry}. Se la
     * funzione non è caricata il campo non è renderizzabile.
     */
    protected function element(): ?ElementField
    {
        if (!function_exists('states')) {
            return null;
        }

        $country = (string) ($this->schema['context']['country'] ?? '');

        return $this->searchableSelectElement($country !== '' ? states($country) : [])
            ->attr('data-wi-input-state', 'true')
            ->attr('data-wi-list-states', $country)
            ->attr('data-wi-input-attribute', (string) ($this->schema['attribute'] ?? ''));
    }
}
