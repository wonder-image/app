<?php

namespace Wonder\App\ResourceSchema\Inputs\Concerns;

use Wonder\Elements\Form\Field as ElementField;

/**
 * Intervallo di date ammesse (`schema['date_min']` / `schema['date_max']`).
 *
 * Il trait porta con sé anche l'applicazione all'Element: chi lo usa —
 * `InputTextDate`, `InputDate`, `InputDateRange` — ottiene i due setters e il
 * `decorate()` che li propaga a `min()` / `max()` dopo l'idratazione.
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

    protected function decorate(ElementField $element): void
    {
        $dateMin = $this->schema['date_min'] ?? null;
        $dateMax = $this->schema['date_max'] ?? null;

        if (is_string($dateMin) && trim($dateMin) !== '') {
            $element->min(trim($dateMin));
        }

        if (is_string($dateMax) && trim($dateMax) !== '') {
            $element->max(trim($dateMax));
        }
    }
}
