<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\Concerns\HasDateBounds;

/**
 * Intervallo di date (da / a) in un solo controllo.
 *
 * Il value è la coppia `[from, to]` in `d/m/Y`; se non viene passato, al
 * render viene ricostruito dai POST `<name>_from` / `<name>_to`.
 */
class InputDateRange extends Input
{
    use HasDateBounds;

    protected string $helper = 'dateRange';
}
