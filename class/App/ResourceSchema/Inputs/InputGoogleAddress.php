<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Indirizzo con autocomplete Google Places più i sei hidden field del
 * breakdown (via, civico, città, provincia, CAP, paese).
 */
class InputGoogleAddress extends Input
{
    protected string $helper = 'googleAddress';

    /**
     * Restrizioni Google Places, es. `['country' => 'it']`.
     * Un array vuoto lascia la ricerca senza vincoli.
     */
    public function restriction(array $restriction): static
    {
        return $restriction !== [] ? $this->context('restriction', $restriction) : $this;
    }

    /**
     * Prefisso dei sei hidden field. Senza alias esplicito si usa il `name`
     * del campo: passa un alias quando servono più indirizzi nello stesso form.
     */
    public function alias(string $alias): static
    {
        $alias = trim($alias);

        return $alias !== '' ? $this->context('alias', $alias) : $this;
    }
}
