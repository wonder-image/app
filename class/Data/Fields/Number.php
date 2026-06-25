<?php

namespace Wonder\Data\Fields;

use Wonder\Data\Formatters\String\TrimFormatter;

class Number extends Field
{
    public string $type = 'number';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->formatters([
            new TrimFormatter(),
        ]);

        $this->decimals(2);
    }

    public function decimals(int $decimals = 2): self
    {
        return $this->schema('decimals', $decimals);
    }

    public function sqlSchema(): array
    {
        return [
            'type' => 'DECIMAL',
            'length' => '10,2',
        ];
    }

    public function defaultInputFormat(): array
    {
        return [
            'decimals' => (int) ($this->getSchema('decimals') ?? 2),
        ];
    }
}
