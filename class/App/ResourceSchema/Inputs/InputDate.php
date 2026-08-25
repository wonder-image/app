<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasDateBounds;

/**
 * Data col datepicker della lib (non il controllo nativo): il value viaggia
 * nel formato `d/m/Y`.
 */
class InputDate extends Input
{
    use HasDateBounds;

    protected string $helper = 'dateInput';
}
