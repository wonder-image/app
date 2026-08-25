<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Select ricercabile delle province/stati di un paese.
 *
 * Le opzioni sono derivate dal paese impostato con `country()`; senza paese
 * la lista parte vuota e viene popolata lato client da {@see InputCountry}.
 */
class InputStates extends Input
{
    protected string $helper = 'inputStates';

    public function country(string $country): static
    {
        $country = trim($country);

        return $country !== '' ? $this->context('country', $country) : $this;
    }
}
