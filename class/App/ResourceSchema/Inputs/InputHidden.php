<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\Hidden;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Campo nascosto: trasporta un valore nel POST senza UI. Dentro un repeater
 * la colonna `hidden` non occupa una colonna della griglia.
 */
class InputHidden extends Input
{
    protected string $helper = 'hidden';

    protected function element(): ElementField
    {
        return new Hidden($this->name);
    }
}
