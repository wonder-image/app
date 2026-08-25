<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasNumberFormat;

/**
 * Campo numerico con formatting configurabile (separatori, simbolo, decimali).
 *
 * Base delle varianti {@see InputPrice} e {@see InputPercentige}, che ne
 * ereditano l'intero DSL cambiando solo l'Element reso.
 */
class InputNumber extends Input
{
    use HasNumberFormat;

    protected string $helper = 'number';
}
