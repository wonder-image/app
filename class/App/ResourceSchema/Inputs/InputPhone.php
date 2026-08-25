<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Campo telefono (`<input type="tel">`). Da abbinare eventualmente a
 * {@see InputPhonePrefix} per il prefisso internazionale.
 */
class InputPhone extends Input
{
    protected string $helper = 'phone';
}
