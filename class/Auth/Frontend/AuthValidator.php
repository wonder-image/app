<?php

namespace Wonder\Auth\Frontend;

final class AuthValidator
{
    public static function signupRequest(array $input, array $documents = ['privacy_policy', 'terms_conditions']): array
    {
        $errors = [];

        foreach (['name', 'surname'] as $field) {
            if (trim((string) ($input[$field] ?? '')) === '') {
                $errors[$field] = 'required';
            }
        }

        if (!filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'invalid';
        }

        foreach ($documents as $document) {
            if (!filter_var($input['accept_'.$document] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $errors['accept_'.$document] = 'required';
            }
        }

        return $errors;
    }

    public static function completion(array $input, bool $passwordRequired = true, bool $phoneRequired = true): array
    {
        $errors = [];
        $prefix = preg_replace('/[^0-9+]/', '', (string) ($input['phone_prefix'] ?? '')) ?? '';
        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '';

        if (($phoneRequired || $phone !== '' || $prefix !== '') && ($prefix === '' || $phone === '' || strlen($phone) < 6)) {
            $errors['phone'] = 'required';
        }

        $password = (string) ($input['password'] ?? '');
        if ($passwordRequired && strlen($password) < 8) {
            $errors['password'] = 'too_short';
        } elseif ($password !== '' && strlen($password) < 8) {
            $errors['password'] = 'too_short';
        } elseif ($password !== '' && !hash_equals($password, (string) ($input['password_confirmation'] ?? ''))) {
            $errors['password_confirmation'] = 'mismatch';
        }

        return $errors;
    }

    public static function canonicalPhone(array $input): string
    {
        $prefix = preg_replace('/\D+/', '', (string) ($input['phone_prefix'] ?? '')) ?? '';
        $phone = preg_replace('/\D+/', '', (string) ($input['phone'] ?? '')) ?? '';

        return $prefix !== '' && $phone !== '' ? '+'.$prefix.$phone : '';
    }
}
