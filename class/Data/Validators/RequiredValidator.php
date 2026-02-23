<?php

    namespace Wonder\Data\Validators;

    use Wonder\Data\Support\ValidationResult;

    class RequiredValidator implements Validator {

        public function validate( $value, array $input = [] ): ValidationResult 
        {

            if ($value === null) {
                return ValidationResult::error(
                    "Valore obbligatorio.",
                    $value
                );
            }

            if (is_string($value) && trim($value) === '') {
                return ValidationResult::error(
                    "Valore obbligatorio.",
                    $value
                );
            }

            if (is_array($value) && count($value) === 0) {

                return ValidationResult::error(
                    "Valore obbligatorio.",
                    $value
                );

            }

            return ValidationResult::success($value);

        }

    }
