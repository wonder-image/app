<?php

namespace Wonder\Elements\Charts;

class LineChart extends Chart
{
    public function __construct()
    {
        parent::__construct('line');
    }

    public static function make(): self
    {
        return new self();
    }

    public function series(array $data, ?string $label = null, array|Dataset $dataset = []): self
    {
        $series = Dataset::make()
            ->data($data)
            ->fill(false)
            ->tension(0.35);

        if ($label !== null && trim($label) !== '') {
            $series->label($label);
        }

        if ($dataset !== []) {
            $series->merge($dataset);
        }

        return $this->dataset($series);
    }
}
