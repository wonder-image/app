<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\FormatsDateValue;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasDateBounds;
use Wonder\Elements\Form\Components\Date;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Data col picker nativo del browser (`<input type="date">`). Il value viene
 * normalizzato a `Y-m-d` al render.
 */
class InputTextDate extends Input
{
    use FormatsDateValue;
    use HasDateBounds;

    protected string $helper = 'textDate';

    protected function element(): ElementField
    {
        return new Date($this->name);
    }

    protected function elementValue(): mixed
    {
        return $this->formatDateValue(parent::elementValue(), 'Y-m-d');
    }
}
