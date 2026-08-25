<?php

namespace Wonder\App\ResourceSchema\Inputs;

use Wonder\App\ResourceSchema\Input;
use Wonder\Elements\Form\Components\InputTime as TimeElement;
use Wonder\Elements\Form\Field as ElementField;

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

    protected function element(): ElementField
    {
        return new TimeElement($this->name);
    }

    protected function decorate(ElementField $element): void
    {
        $timeStep = $this->schema['time_step'] ?? null;

        if (is_numeric($timeStep) && (int) $timeStep > 0) {
            $element->step((int) $timeStep);
        }
    }
}
