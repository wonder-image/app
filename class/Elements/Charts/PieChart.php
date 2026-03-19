<?php

namespace Wonder\Elements\Charts;

class PieChart extends Chart
{
    public function __construct()
    {
        parent::__construct('pie');
        $this->legend('bottom');
    }

    public static function make(): self
    {
        return new self();
    }

    public function series(array $data, ?string $label = null, array $dataset = []): self
    {
        $baseDataset = [
            'data' => array_values($data),
        ];

        if ($label !== null && trim($label) !== '') {
            $baseDataset['label'] = $label;
        }

        return $this->dataset(array_replace($baseDataset, $dataset));
    }
}
