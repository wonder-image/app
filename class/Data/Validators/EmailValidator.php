<?php

    namespace Wonder\Data\Validators;

    use Wonder\Data\Support\ValidationResult;

    class EmailValidator implements Validator {

        public function validate( $email, array $input = [] ): ValidationResult 
        {

            $initialValue = $email;

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

                return ValidationResult::error(
                    "Formato email non valido.",
                    $initialValue
                );

            }

            $domain = substr(strrchr($email, "@"), 1);

            $domainValidation = (new DomainValidator())->validate($domain, $input);

            if (!$domainValidation->isValid()) {
                
                return ValidationResult::error(
                    "Dominio email non valido.",
                    $initialValue
                );

            }

            return ValidationResult::success($initialValue);

        }

    }
