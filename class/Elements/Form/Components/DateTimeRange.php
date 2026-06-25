<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

/**
 * Range data+ora con due input gestiti dal plugin jQuery
 * `datetimepicker` (formato `dd/mm/yyyy HH:mm`). Versione "datetime"
 * di `DateRange`.
 */
class DateTimeRange extends Field
{
    public string $type = 'text';

    public function min(string $datetime): self
    {
        return $this->attr('data-wi-min-date', $datetime);
    }

    public function max(string $datetime): self
    {
        return $this->attr('data-wi-max-date', $datetime);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
