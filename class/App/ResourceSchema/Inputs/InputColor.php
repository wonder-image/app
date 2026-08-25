<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/** Color picker nativo (`<input type="color">`). */
class InputColor extends Input
{
    protected string $helper = 'color';
}
