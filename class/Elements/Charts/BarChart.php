<?php

namespace Wonder\Elements\Charts;

class BarChart extends Chart
{
    public function __construct()
    {
        parent::__construct('bar');
    }

    public static function make(): self
    {
        return new self();
    }

    public function series(array $data, ?string $label = null, array|Dataset $dataset = []): self
    {
        $series = Dataset::make()
            ->data($data);

        if ($label !== null && trim($label) !== '') {
            $series->label($label);
        }

        if ($dataset !== []) {
            $series->merge($dataset);
        }

        return $this->dataset($series);
    }

    public function horizontal(bool $horizontal = true): self
    {
        return $this->mergeOptions([
            'indexAxis' => $horizontal ? 'y' : 'x',
        ]);
    }

    public function stacked(bool $stacked = true): self
    {
        return $this->mergeOptions([
            'scales' => [
                'x' => [
                    'stacked' => $stacked,
                ],
                'y' => [
                    'stacked' => $stacked,
                ],
            ],
        ]);
    }
}
