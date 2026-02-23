<?php

    namespace Wonder\Data\Fields;

    use Wonder\Data\Validators\TinValidator;

    class Tin extends Field {

        public string $type = 'text';
        private TinValidator $tinValidator;

        public function __construct($key)
        {

            parent::__construct($key);

            $this->tinValidator = new TinValidator();

            $this->validators([
                new \Wonder\Data\Validators\StringValidator(),
                $this->tinValidator
            ]);

            $this->formatters([
                new \Wonder\Data\Formatters\String\TrimFormatter(),
                new \Wonder\Data\Formatters\String\UppercaseFormatter()
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
