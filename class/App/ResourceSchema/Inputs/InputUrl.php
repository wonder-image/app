<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputUrl as UrlElement;
use Wonder\Elements\Form\Field as ElementField;

/** Campo URL (`<input type="url">`). */
class InputUrl extends Input
{
    protected string $helper = 'url';

    protected function element(): ElementField
    {
        return new UrlElement($this->name);
    }
}
