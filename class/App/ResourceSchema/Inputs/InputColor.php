<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputColor as ColorElement;
use Wonder\Elements\Form\Field as ElementField;

/** Color picker nativo (`<input type="color">`). */
class InputColor extends Input
{
    protected string $helper = 'color';

    protected function element(): ElementField
    {
        return new ColorElement($this->name);
    }
}
