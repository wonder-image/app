<?php

namespace Wonder\Elements\Charts;

use InvalidArgumentException;
use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

class Chart extends Component
{
    use Renderer;

    private const ALLOWED_TYPES = ['line', 'pie', 'bar'];
    private const ALLOWED_LEGEND_POSITIONS = ['top', 'left', 'bottom', 'right', 'chartArea'];

    public function __construct(string $type)
    {
        $this->type($type);
        $this->width('100%');
        $this->height(320);
        $this->responsive();
        $this->maintainAspectRatio(false);
    }

    public function type(string $type): self
    {
        $normalized = strtolower(trim($type));
        if (!in_array($normalized, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException(
                "Tipo grafico {$type} non valido. Valori ammessi: " . implode(', ', self::ALLOWED_TYPES)
            );
        }

        return $this->schema('type', $normalized);
    }

    public function title(string $title): self
    {
        return $this->schema('title', trim($title));
    }

    public function labels(array $labels): self
    {
        return $this->schema('labels', array_values($labels));
    }

    public function dataset(array|Dataset $dataset): self
    {
        $datasets = $this->getSchema('datasets') ?? [];
        $datasets[] = $this->normalizeDataset($dataset);

        return $this->schema('datasets', $datasets);
    }

    public function datasets(array $datasets): self
    {
        $normalized = [];

        foreach ($datasets as $dataset) {
            if (!is_array($dataset) && !$dataset instanceof Dataset) {
                throw new InvalidArgumentException('Ogni dataset deve essere un array o un oggetto Dataset.');
            }

            $normalized[] = $this->normalizeDataset($dataset);
        }

        return $this->schema('datasets', $normalized);
    }

    public function options(array $options): self
    {
        return $this->schema('options', $options);
    }

    public function mergeOptions(array $options): self
    {
        $current = $this->getSchema('options') ?? [];

        return $this->schema('options', array_replace_recursive($current, $options));
    }

    public function interaction(array $options): self
    {
        return $this->mergeOptions([
            'interaction' => $options,
        ]);
    }

    public function animation(array|bool $options): self
    {
        return $this->mergeOptions([
            'animation' => $options,
        ]);
    }

    public function plugin(string $name, array $options): self
    {
        $plugin = trim($name);
        if ($plugin === '') {
            throw new InvalidArgumentException('Il nome del plugin non può essere vuoto.');
        }

        return $this->mergeOptions([
            'plugins' => [
                $plugin => $options,
            ],
        ]);
    }

    public function scale(string $axis, array $options): self
    {
        $normalized = strtolower(trim($axis));
        if ($normalized === '') {
            throw new InvalidArgumentException("L'asse non può essere vuoto.");
        }

        return $this->mergeOptions([
            'scales' => [
                $normalized => $options,
            ],
        ]);
    }

    public function xAxis(array $options): self
    {
        return $this->scale('x', $options);
    }

    public function yAxis(array $options): self
    {
        return $this->scale('y', $options);
    }

    public function width(int|string $width): self
    {
        return $this->schema('width', $this->normalizeDimension($width, 'larghezza'));
    }

    public function height(int|string $height): self
    {
        return $this->schema('height', $this->normalizeDimension($height, 'altezza'));
    }

    public function responsive(bool $responsive = true): self
    {
        return $this->mergeOptions([
            'responsive' => $responsive,
        ]);
    }

    public function maintainAspectRatio(bool $maintainAspectRatio = true): self
    {
        return $this->mergeOptions([
            'maintainAspectRatio' => $maintainAspectRatio,
        ]);
    }

    public function legend(string $position = 'top'): self
    {
        if (!in_array($position, self::ALLOWED_LEGEND_POSITIONS, true)) {
            throw new InvalidArgumentException(
                "Posizione legenda {$position} non valida. Valori ammessi: "
                . implode(', ', self::ALLOWED_LEGEND_POSITIONS)
            );
        }

        return $this->mergeOptions([
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => $position,
                ],
            ],
        ]);
    }

    public function hideLegend(): self
    {
        return $this->mergeOptions([
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ]);
    }

    public function config(): array
    {
        return [
            'type' => $this->getSchema('type'),
            'data' => [
                'labels' => $this->getSchema('labels') ?? [],
                'datasets' => $this->getSchema('datasets') ?? [],
            ],
            'options' => $this->getSchema('options') ?? [],
        ];
    }

    protected function normalizeDataset(array|Dataset $dataset): array
    {
        if ($dataset instanceof Dataset) {
            $dataset = $dataset->toArray();
        }

        if (!array_key_exists('data', $dataset) || !is_array($dataset['data'])) {
            throw new InvalidArgumentException('Ogni dataset deve contenere una chiave data con un array di valori.');
        }

        $dataset['data'] = array_values($dataset['data']);

        return $dataset;
    }

    private function normalizeDimension(int|string $value, string $label): string
    {
        if (is_int($value)) {
            if ($value <= 0) {
                throw new InvalidArgumentException("La {$label} deve essere maggiore di zero.");
            }

            return $value . 'px';
        }

        $normalized = trim($value);
        if ($normalized === '') {
            throw new InvalidArgumentException("La {$label} non può essere vuota.");
        }

        return $normalized;
    }
}
