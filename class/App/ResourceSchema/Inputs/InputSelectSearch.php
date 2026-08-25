<?php

namespace Wonder\App\ResourceSchema\Inputs;

/**
 * Select con ricerca lato client (`data-wi-select-search`). Stesso DSL di
 * {@see InputSelect}; con `multiple()` abilita anche la selezione multipla
 * ricercabile.
 */
class InputSelectSearch extends InputSelect
{
    protected string $helper = 'selectSearch';
}
