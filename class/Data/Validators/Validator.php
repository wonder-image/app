<?php

    namespace Wonder\Data\Validators;

    use Wonder\Data\Support\ValidationResult;

    interface Validator
    {

        public function validate($value, array $input = []): ValidationResult;

    }
