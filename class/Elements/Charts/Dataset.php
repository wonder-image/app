<?php

namespace Wonder\Elements\Charts;

use InvalidArgumentException;
use Wonder\Concerns\HasSchema;

class Dataset
{
    use HasSchema;

    public static function make(): self
    {
        return new self();
    }

    public static function from(array $options): self
    {
        return self::make()->options($options);
    }

    public function data(array $data): self
    {
        return $this->schema('data', array_values($data));
    }

    public function label(?string $label): self
    {
        $normalized = trim((string) $label);

        if ($normalized === '') {
            unset($this->schema['label']);

            return $this;
        }

        return $this->schema('label', $normalized);
    }

    public function options(array $options): self
    {
        foreach ($options as $key => $value) {
            if (!is_string($key) || trim($key) === '') {
                throw new InvalidArgumentException('Ogni opzione del dataset deve avere una chiave stringa valida.');
            }

            $this->schema[trim($key)] = $value;
        }

        return $this;
    }

    public function option(string $key, mixed $value): self
    {
        $normalized = trim($key);
        if ($normalized === '') {
            throw new InvalidArgumentException('La chiave dell\'opzione dataset non può essere vuota.');
        }

        return $this->schema($normalized, $value);
    }

    public function merge(array|self $options): self
    {
        $payload = $options instanceof self ? $options->toArray() : $options;

        return $this->options($payload);
    }

    public function fill(bool|string|array $fill = true): self
    {
        return $this->option('fill', $fill);
    }

    public function tension(int|float $tension): self
    {
        return $this->option('tension', $tension);
    }

    public function hidden(bool $hidden = true): self
    {
        return $this->option('hidden', $hidden);
    }

    public function order(int $order): self
    {
        return $this->option('order', $order);
    }

    public function stack(string $stack): self
    {
        return $this->option('stack', trim($stack));
    }

    public function xAxisId(string $axisId): self
    {
        return $this->option('xAxisID', trim($axisId));
    }

    public function yAxisId(string $axisId): self
    {
        return $this->option('yAxisID', trim($axisId));
    }

    public function pointRadius(int|float $radius): self
    {
        return $this->option('pointRadius', $radius);
    }

    public function pointHoverRadius(int|float $radius): self
    {
        return $this->option('pointHoverRadius', $radius);
    }

    public function borderWidth(int|float $width): self
    {
        return $this->option('borderWidth', $width);
    }

    public function borderColor(mixed $color): self
    {
        return $this->option('borderColor', $color);
    }

    public function backgroundColor(mixed $color): self
    {
        return $this->option('backgroundColor', $color);
    }

    public function pointBackgroundColor(mixed $color): self
    {
        return $this->option('pointBackgroundColor', $color);
    }

    public function pointBorderColor(mixed $color): self
    {
        return $this->option('pointBorderColor', $color);
    }

    public function toArray(): array
    {
        return $this->schema;
    }

    public function __call(string $name, array $arguments): self
    {
        if (count($arguments) !== 1) {
            throw new InvalidArgumentException(
                "Il metodo dinamico {$name} richiede esattamente un argomento."
            );
        }

        return $this->option($name, $arguments[0]);
    }
}
