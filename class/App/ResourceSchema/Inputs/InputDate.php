<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\FormatsDateValue;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasDateBounds;
use Wonder\Elements\Form\Components\DatePicker;
use Wonder\Elements\Form\Field as ElementField;

/**
 * Data col datepicker della lib (non il controllo nativo): il value viaggia
 * nel formato `d/m/Y`.
 */
class InputDate extends Input
{
    use FormatsDateValue;
    use HasDateBounds;

    protected string $helper = 'dateInput';

    protected function element(): ElementField
    {
        return new DatePicker($this->name);
    }

    protected function elementValue(): mixed
    {
        return $this->formatDateValue(parent::elementValue(), 'd/m/Y');
    }
}
