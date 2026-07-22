<?php

namespace Wonder\Elements\Components;

class MetricCard extends AbstractValueCard
{
    public function __construct(
        string $title = '',
        string|int|float|bool|null $value = null
    ) {
        parent::__construct($title, $value);

        $this->unit();
        $this->valueLevel(2);
        $this->higherIsBetter();
    }

    public function unit(string $unit = ''): static
    {
        return $this->schema('unit', $unit);
    }

    public function displayValue(string|int|float|bool|null $value): static
    {
        return $this->schema('display_value', $value);
    }

    public function clearDisplayValue(): static
    {
        unset($this->schema['display_value']);

        return $this;
    }

    public function compareTo(string|int|float|null $previousValue): static
    {
        if ($previousValue === null) {
            return $this->clearComparison();
        }

        return $this->schema('previous_value', $previousValue);
    }

    public function previousValue(string|int|float|null $previousValue): static
    {
        return $this->compareTo($previousValue);
    }

    public function clearComparison(): static
    {
        unset($this->schema['previous_value']);

        return $this;
    }

    public function higherIsBetter(bool $higherIsBetter = true): static
    {
        return $this->schema('higher_is_better', $higherIsBetter);
    }

    public function lowerIsBetter(bool $lowerIsBetter = true): static
    {
        return $this->higherIsBetter(!$lowerIsBetter);
    }

    public function deltaPrecision(?int $precision): static
    {
        return $this->schema(
            'delta_precision',
            $precision === null ? null : max(0, min(6, $precision))
        );
    }
}
