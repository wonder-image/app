<?php

    namespace Wonder\Data\Validators;

    use Wonder\Data\Support\ValidationResult;

    class DomainValidator implements Validator {

        public function validate( $domain, array $input = [] ): ValidationResult 
        {

            if (!checkdnsrr($domain, "MX")) {

                return ValidationResult::error(
                    "Dominio non valido.",
                    $domain
                );

            }

            return ValidationResult::success($domain);

        }

    }
