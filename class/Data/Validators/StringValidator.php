<?php

    namespace Wonder\Data\Validators;

    use Wonder\Data\Support\ValidationResult;

    class StringValidator implements Validator {

        public function validate( $value, array $input = [] ): ValidationResult 
        {

            if (!empty($value) && !is_string($value)) {

                return ValidationResult::error(
                    "Stringa non valida.",
                    $value
                );

            }

            return ValidationResult::success($value);

        }

    }
