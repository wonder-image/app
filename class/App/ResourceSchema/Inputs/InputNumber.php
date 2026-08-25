<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasNumberFormat;
use Wonder\Elements\Form\Components\InputNumber as NumberElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Campo numerico con formatting configurabile (separatori, simbolo, decimali).
 *
 * Base delle varianti {@see InputPrice} e {@see InputPercentige}, che ne
 * ereditano l'intero DSL cambiando solo l'Element reso.
 */
class InputNumber extends Input
{
    use HasNumberFormat;

    protected string $helper = 'number';

    protected function element(): ElementField
    {
        return $this->applyNumberConfig(new NumberElement($this->name));
    }
}
