<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/** Campo URL (`<input type="url">`). */
class InputUrl extends Input
{
    protected string $helper = 'url';
}
