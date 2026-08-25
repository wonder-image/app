<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasMultiple;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasOptions;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasVersion;

/**
 * Tendina a scelta singola (o multipla con `multiple()`).
 *
 * `old()` — scorciatoia per `version('old')` — forza il markup legacy del
 * select sul tema Wonder.
 */
class InputSelect extends Input
{
    use HasOptions;
    use HasMultiple;
    use HasVersion;

    protected string $helper = 'select';
}
