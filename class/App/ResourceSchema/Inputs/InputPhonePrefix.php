<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Select ricercabile dei prefissi telefonici internazionali, popolata da
 * `phonePrefix()`. Da abbinare a {@see InputPhone}.
 */
class InputPhonePrefix extends Input
{
    protected string $helper = 'inputPhonePrefix';
}
