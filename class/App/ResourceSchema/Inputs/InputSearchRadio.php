<?php

namespace Wonder\App\ResourceSchema\Inputs;

/**
 * Variante a selezione singola di {@see InputSearchText}
 * (`data-wi-search-radio`): la scelta di una voce invia direttamente il
 * relativo value.
 */
class InputSearchRadio extends InputSearchRemote
{
    protected string $helper = 'searchRadio';
}
