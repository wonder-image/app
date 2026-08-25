<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\FormatsDateValue;
use Wonder\Elements\Form\Components\InputDatetime;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Data e ora col picker nativo (`<input type="datetime-local">`). Il value
 * viene normalizzato a `Y-m-d\TH:i` al render.
 *
 * Non espone `dateMin()`/`dateMax()`: l'Element `InputDatetime` non li
 * applica (a differenza di {@see InputTextDate}, {@see InputDate} e
 * {@see InputDateRange}), quindi il DSL non offre setters che non avrebbero
 * effetto.
 */
class InputTextDatetime extends Input
{
    use FormatsDateValue;

    protected string $helper = 'textDatetime';

    protected function element(): ElementField
    {
        return new InputDatetime($this->name);
    }

    protected function elementValue(): mixed
    {
        return $this->formatDateValue(parent::elementValue(), 'Y-m-d\TH:i');
    }
}
