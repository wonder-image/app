<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\BuildsSelectElement;
use Wonder\Elements\Form\Field as ElementField;
use Wonder\Support\Prettify\Phone;

/**
 * Select ricercabile dei prefissi telefonici internazionali, popolata da
 * `phonePrefix()`. Da abbinare a {@see InputPhone}.
 *
 * Senza valore parte dal prefisso del paese di casa
 * (`Phone::$defaultCountryCode`, +39), come il paese negli indirizzi: non dal
 * primo dell'elenco.
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

    protected function elementValue(): mixed
    {
        $value = parent::elementValue();

        return $value === null || (is_string($value) && trim($value) === '') ? Phone::$defaultCountryCode : $value;
    }
}
