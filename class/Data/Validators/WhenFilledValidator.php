<?php

namespace Wonder\Data\Validators;

use Wonder\Data\Support\ValidationResult;

/** Validate optional formatted data without treating a blank value as invalid. */
final class WhenFilledValidator implements Validator
{
    public function __construct(private Validator $validator) {}

    public function validate($value, array $input = []): ValidationResult
    {
        return $value === null || (is_string($value) && trim($value) === '')
            ? ValidationResult::success($value)
            : $this->validator->validate($value, $input);
    }
}
