<?php

namespace Wonder\Data\Fields;

use Wonder\Data\Formatters\String\LowercaseFormatter;
use Wonder\Data\Formatters\String\TrimFormatter;
use Wonder\Data\Validators\EmailValidator;
use Wonder\Data\Validators\StringValidator;

class Email extends Field
{
    public string $type = 'email';

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->validators([
            new StringValidator(),
            new EmailValidator(),
        ]);

        $this->formatters([
            new TrimFormatter(),
            new LowercaseFormatter(),
        ]);
    }
}
