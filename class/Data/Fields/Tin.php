<?php

namespace Wonder\Data\Fields;

use Wonder\Data\Formatters\String\TrimFormatter;
use Wonder\Data\Formatters\String\UppercaseFormatter;
use Wonder\Data\Validators\StringValidator;
use Wonder\Data\Validators\TinValidator;

class Tin extends Field
{
    public string $type = 'tin';
    private TinValidator $tinValidator;

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->tinValidator = new TinValidator();

        $this->validators([
            new StringValidator(),
            $this->tinValidator,
        ]);

        $this->formatters([
            new TrimFormatter(),
            new UppercaseFormatter(),
        ]);
    }

    public function countryField(string $field): self
    {
        $this->tinValidator->countryField($field);

        return $this;
    }

    public function countryIso(string $iso2): self
    {
        $this->tinValidator->countryIso($iso2);

        return $this;
    }

    public function type(string $type = 'private'): self
    {
        $this->tinValidator->type($type);

        return $this;
    }
}
