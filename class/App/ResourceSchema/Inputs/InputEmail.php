<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/**
 * Campo email. È l'unico tipo per cui `autocomplete(true)` si traduce in
 * `autocomplete="email"` invece che `"on"`.
 */
class InputEmail extends Input
{
    protected string $helper = 'email';
}
