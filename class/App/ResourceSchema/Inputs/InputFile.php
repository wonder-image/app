<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasUpload;

/**
 * Upload "classic": `form-control` più la lista di file ammessi, numero
 * massimo e peso massimo.
 *
 * La categoria di file (`image`, `pdf`, `video`, `font`, `media`, ...) si
 * imposta con `accept()`; il renderer la traduce in attributo `accept="..."`
 * e in label informativa.
 */
class InputFile extends Input
{
    use HasUpload;

    protected string $helper = 'inputFile';
}
