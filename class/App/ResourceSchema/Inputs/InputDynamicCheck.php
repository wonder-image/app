<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasInputType;

/**
 * Check (checkbox o radio) con le voci caricate via AJAX.
 *
 * A differenza di {@see InputSearchRemote}, l'URL vive in `context['url']`:
 * è la chiave che l'Element `DynamicCheck` legge.
 */
class InputDynamicCheck extends Input
{
    use HasInputType;

    protected string $helper = 'dynamicCheck';

    public function url(string $url): static
    {
        return $this->context('url', trim($url));
    }
}
