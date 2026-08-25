<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasSearchBar;

/**
 * Checkbox singolo oppure gruppo di checkbox.
 *
 * Senza `options()` rende un singolo checkbox booleano (spuntato quando il
 * value è `true`/`1`/`'true'`/`'on'`); con `options()` rende un gruppo, il
 * cui value può arrivare anche come JSON e viene decodificato al render.
 */
class InputCheckbox extends Input
{
    use HasOptions;
    use HasSearchBar;

    protected string $helper = 'checkbox';
}
