<?php

namespace Wonder\Auth\Frontend;

use Wonder\Elements\Components\Alert;

final class AuthValidationAlert
{
    /**
     * @param array<string, string> $errors
     * @return list<string>
     */
    public static function messageKeys(array $errors): array
    {
        $keys = [];

        foreach ($errors as $field => $reason) {
            $keys[] = match ($field.'.'.$reason) {
                'name.required' => 'auth.validation.errors.name_required',
                'surname.required' => 'auth.validation.errors.surname_required',
                'email.invalid' => 'auth.validation.errors.email_invalid',
                'email.exists' => 'auth.validation.errors.email_exists',
                'accept_privacy_policy.required' => 'auth.validation.errors.privacy_required',
                'accept_terms_conditions.required' => 'auth.validation.errors.terms_required',
                'phone.required' => 'auth.validation.errors.phone_required',
                'phone.not_unique' => 'auth.validation.errors.phone_not_unique',
                'password.too_short' => 'auth.validation.errors.password_too_short',
                'password_confirmation.mismatch' => 'auth.validation.errors.password_mismatch',
                'token.invalid' => 'auth.validation.errors.token_invalid',
                default => 'auth.validation.review',
            };
        }

        return array_values(array_unique($keys));
    }

    /**
     * Restituisce un solo alert riepilogativo. I codici interni non vengono
     * mai mostrati e gli errori restano separati dal rendering dei campi.
     *
     * @param array<string, string> $errors
     */
    public static function make(array $errors = [], mixed $alert = null, ?string $federatedError = null, ?array $messages = null): ?Alert
    {
        $messages ??= array_map(
            static fn (string $key): string => (string) \__t($key),
            self::messageKeys($errors)
        );

        if (!empty($alert)) {
            $messages[] = is_numeric($alert)
                ? (string) \__t('notifications.'.(int) $alert.'.text')
                : (string) \__t('auth.validation.review');
        }

        if (trim((string) $federatedError) !== '') {
            $messages[] = (string) \__t(match ($federatedError) {
                'use_federated_login_google' => 'auth.federated.use_google',
                'use_federated_login_apple' => 'auth.federated.use_apple',
                default => 'auth.federated.failed',
            });
        }

        $messages = array_values(array_unique(array_filter(array_map('trim', $messages))));

        if ($messages === []) {
            return null;
        }

        return Alert::make(implode("\n", $messages), 'error')
            ->title((string) \__t('auth.validation.title'))
            ->dismissible(false);
    }
}
