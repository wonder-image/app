<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasInputType;
use Wonder\Elements\Form\Components\DynamicCheck;
use Wonder\Elements\Form\Field as ElementField;

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

    protected function element(): ElementField
    {
        $context = (array) ($this->schema['context'] ?? []);

        return (new DynamicCheck($this->name))
            ->url((string) ($context['url'] ?? ''))
            ->inputType((string) ($context['input_type'] ?? 'checkbox'))
            ->value($this->schema['value'] ?? null);
    }
}
