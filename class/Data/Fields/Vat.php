<?php

namespace Wonder\Data\Fields;

use Wonder\Data\Formatters\String\TrimFormatter;
use Wonder\Data\Formatters\String\UppercaseFormatter;
use Wonder\Data\Validators\StringValidator;
use Wonder\Data\Validators\VatValidator;

class Vat extends Field
{
    public string $type = 'vat';
    private VatValidator $vatValidator;

    public function __construct(string $key)
    {
        parent::__construct($key);

        $this->vatValidator = new VatValidator();

        $this->validators([
            new StringValidator(),
            $this->vatValidator,
        ]);

        $this->formatters([
            new TrimFormatter(),
            new UppercaseFormatter(),
        ]);
    }

    public function countryField(string $field): self
    {
        $this->vatValidator->countryField($field);

        return $this;
    }

    public function countryIso(string $iso2): self
    {
        $this->vatValidator->countryIso($iso2);

        return $this;
    }
}
