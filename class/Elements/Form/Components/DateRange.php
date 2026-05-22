<?php

namespace Wonder\Elements\Form\Components;

use Wonder\Elements\Form\Field;

class DateRange extends Field
{
    public string $type = 'text';

    public function min(string $date): self
    {
        return $this->attr('data-wi-min-date', $date);
    }

    public function max(string $date): self
    {
        return $this->attr('data-wi-max-date', $date);
    }

    protected function renderInput(): string
    {
        return '';
    }
}
