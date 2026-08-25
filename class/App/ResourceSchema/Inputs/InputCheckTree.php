<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasInputType;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasSearchBar;

/**
 * Lista ad albero (jsTree) di checkbox o radio.
 *
 * Le opzioni ammettono la forma estesa con figli:
 * `['id' => ['name' => 'Label', 'child' => [...]]]`.
 */
class InputCheckTree extends Input
{
    use HasOptions;
    use HasSearchBar;
    use HasInputType;

    protected string $helper = 'checkTree';
}
