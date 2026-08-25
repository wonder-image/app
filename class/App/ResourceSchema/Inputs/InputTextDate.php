<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasDateBounds;

/**
 * Data col picker nativo del browser (`<input type="date">`). Il value viene
 * normalizzato a `Y-m-d` al render.
 */
class InputTextDate extends Input
{
    use HasDateBounds;

    protected string $helper = 'textDate';
}
