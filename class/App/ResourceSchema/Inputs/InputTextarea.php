<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasVersion;

/**
 * Area di testo multilinea.
 *
 * Senza `version()` rende una `Textarea` semplice; con una version rende il
 * `TextareaEditor` rich-text, che il `FormFieldElementFactory` configura col
 * preset omonimo (es. `'plus'`, `'blog'`, `'old'`).
 */
class InputTextarea extends Input
{
    use HasVersion;

    protected string $helper = 'textarea';
}
