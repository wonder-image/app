<?php

namespace Wonder\Data\Fields;

use Wonder\Data\Formatters\String\TrimFormatter;
use Wonder\Data\Validators\StringValidator;

class Password extends Field
{
    public string $type = 'password';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->validators([
            new StringValidator(),
        ]);

        $this->formatters([
            new TrimFormatter(),
        ]);
    }
}
