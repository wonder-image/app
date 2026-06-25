<?php

namespace Wonder\Data\Fields;

use Wonder\Data\Formatters\String\TrimFormatter;
use Wonder\Data\Validators\StringValidator;

class Date extends Field
{
    public string $type = 'date';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->validators([
            new StringValidator(),
        ]);

        $this->formatters([
            new TrimFormatter(),
        ]);

        $this->schema('date', true);
    }

    public function sqlSchema(): array
    {
        return [
            'type' => 'DATETIME',
        ];
    }

    public function defaultInputFormat(): array
    {
        return [
            'date' => true,
        ];
    }
}
