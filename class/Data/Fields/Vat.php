<?php

    namespace Wonder\Data\Fields;

    use Wonder\Data\Validators\VatValidator;

    class Vat extends Field {

        public string $type = 'text';
        private VatValidator $vatValidator;

        public function __construct($key)
        {

            parent::__construct($key);

            $this->vatValidator = new VatValidator();

            $this->validators([
                new \Wonder\Data\Validators\StringValidator(),
                $this->vatValidator
            ]);

            $this->formatters([
                new \Wonder\Data\Formatters\String\TrimFormatter(),
                new \Wonder\Data\Formatters\String\UppercaseFormatter()
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
