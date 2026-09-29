<?php

namespace Wonder\Data\Fields;

use InvalidArgumentException;
use Wonder\Data\Formatters\String\TrimFormatter;

class Number extends Field
{
    private const DEFAULT_PRECISION = 10;
    private const MAX_PRECISION = 65;
    private const MAX_DECIMALS = 30;

    public string $type = 'number';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->formatters([
            new TrimFormatter(),
        ]);

        $this->precision(self::DEFAULT_PRECISION)
            ->decimals(2);
    }

    public function decimals(int $decimals = 2): self
    {
        if ($decimals < 0 || $decimals > self::MAX_DECIMALS) {
            throw new InvalidArgumentException('I decimali devono essere compresi tra 0 e 30.');
        }

        return $this->schema('decimals', $decimals);
    }

    public function decimal(int $decimals = 2): self
    {
        return $this->decimals($decimals);
    }

    public function integer(): self
    {
        return $this->decimals(0);
    }

    public function precision(int $precision = self::DEFAULT_PRECISION): self
    {
        if ($precision < 1 || $precision > self::MAX_PRECISION) {
            throw new InvalidArgumentException('La precisione deve essere compresa tra 1 e 65.');
        }

        return $this->schema('precision', $precision);
    }

    public function sqlSchema(): array
    {
        $precision = (int) ($this->getSchema('precision') ?? self::DEFAULT_PRECISION);
        $decimals = (int) ($this->getSchema('decimals') ?? 2);

        if ($decimals > $precision) {
            throw new InvalidArgumentException('I decimali non possono superare la precisione totale.');
        }

        return [
            'type' => 'DECIMAL',
            'length' => $decimals === 0
                ? (string) $precision
                : $precision.','.$decimals,
        ];
    }

    public function defaultInputFormat(): array
    {
        return [
            'decimals' => (int) ($this->getSchema('decimals') ?? 2),
        ];
    }
}
