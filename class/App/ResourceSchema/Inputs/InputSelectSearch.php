<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\Elements\Form\Field as ElementField;

/**
 * Select con ricerca lato client (`data-wi-select-search`). Stesso DSL di
 * {@see InputSelect}; con `multiple()` abilita anche la selezione multipla
 * ricercabile.
 */
class InputSelectSearch extends InputSelect
{
    protected string $helper = 'selectSearch';

    protected function element(): ElementField
    {
        return $this->searchableSelectElement();
    }
}
