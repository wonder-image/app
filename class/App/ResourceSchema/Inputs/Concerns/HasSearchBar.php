<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Barra di ricerca testuale sopra la lista di opzioni (radio/checkbox/
 * checkTree). Finisce in `schema['search_bar']`.
 */
trait HasSearchBar
{
    public function searchBar(bool $searchBar = true): static
    {
        $this->schema['search_bar'] = $searchBar;

        return $this;
    }
}
