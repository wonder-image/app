<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputTel;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Campo telefono (`<input type="tel">`). Da abbinare eventualmente a
 * {@see InputPhonePrefix} per il prefisso internazionale.
 */
class InputPhone extends Input
{
    protected string $helper = 'phone';

    protected function element(): ElementField
    {
        return new InputTel($this->name);
    }
}
