<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Campo nascosto: trasporta un valore nel POST senza UI. Dentro un repeater
 * la colonna `hidden` non occupa una colonna della griglia.
 */
class InputHidden extends Input
{
    protected string $helper = 'hidden';
}
