<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\BuildsSelectElement;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Select ricercabile dei prefissi telefonici internazionali, popolata da
 * `phonePrefix()`. Da abbinare a {@see InputPhone}.
 */
class InputPhonePrefix extends Input
{
    use BuildsSelectElement;

    protected string $helper = 'inputPhonePrefix';

    protected function element(): ?ElementField
    {
        if (!function_exists('phonePrefix')) {
            return null;
        }

        return $this->searchableSelectElement(phonePrefix());
    }
}
