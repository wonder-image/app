<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasSearchBar;

/** Gruppo di radio button, con barra di ricerca opzionale sulle opzioni. */
class InputRadio extends Input
{
    use HasOptions;
    use HasSearchBar;

    protected string $helper = 'radio';
}
