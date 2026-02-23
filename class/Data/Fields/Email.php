<?php

    namespace Wonder\Data\Fields;

    class Email extends Field {

        public string $type = 'email';

        public function __construct($key)
        {

            parent::__construct($key);

            $this->validators([
                new \Wonder\Data\Validators\StringValidator(),
                new \Wonder\Data\Validators\EmailValidator()
            ]);

            $this->formatters([
                new \Wonder\Data\Formatters\String\TrimFormatter(),
                new \Wonder\Data\Formatters\String\LowercaseFormatter()
            ]);

        }

    }