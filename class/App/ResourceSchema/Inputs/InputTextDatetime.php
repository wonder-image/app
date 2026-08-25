<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Data e ora col picker nativo (`<input type="datetime-local">`). Il value
 * viene normalizzato a `Y-m-d\TH:i` al render.
 *
 * Non espone `dateMin()`/`dateMax()`: l'Element `InputDatetime` non li
 * applica (a differenza di {@see InputTextDate}, {@see InputDate} e
 * {@see InputDateRange}), quindi il DSL non offre setters che non avrebbero
 * effetto.
 */
class InputTextDatetime extends Input
{
    protected string $helper = 'textDatetime';
}
