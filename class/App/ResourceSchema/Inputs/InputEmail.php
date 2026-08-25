<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputEmail as EmailElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Campo email. È l'unico tipo per cui `autocomplete(true)` si traduce in
 * `autocomplete="email"` invece che `"on"`.
 */
class InputEmail extends Input
{
    protected string $helper = 'email';

    protected function element(): ElementField
    {
        return new EmailElement($this->name);
    }

    protected function autocompleteOn(): string
    {
        return 'email';
    }
}
