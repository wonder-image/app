<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;

/** Orario (`<input type="time">`) con granularità configurabile. */
class InputTime extends Input
{
    protected string $helper = 'timeInput';

    /**
     * Granularità in secondi dello step temporale (es. `900` = 15 minuti).
     * Applicata al render solo se maggiore di zero.
     */
    public function timeStep(?int $timeStep): static
    {
        $this->schema['time_step'] = $timeStep;

        return $this;
    }
}
