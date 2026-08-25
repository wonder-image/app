<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

/**
 * Intervallo di date ammesse (`schema['date_min']` / `schema['date_max']`).
 *
 * Applicato al render solo dagli Element che lo supportano — `Date`,
 * `DatePicker`, `DateRange` — via `FormFieldElementFactory::hydrate()`.
 */
trait HasDateBounds
{
    public function dateMin(?string $dateMin): static
    {
        $this->schema['date_min'] = $dateMin;

        return $this;
    }

    public function dateMax(?string $dateMax): static
    {
        $this->schema['date_max'] = $dateMax;

        return $this;
    }
}
