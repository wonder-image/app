<?php

namespace Wonder\Auth\Federated;

final class FederatedClaimMapper
{
    public static function fromGoogleClaims(array $claims): FederatedIdentityPayload
    {
        $providerUserId = trim((string) ($claims['sub'] ?? ''));
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $emailVerified = self::toBool($claims['email_verified'] ?? false);

        $name = trim((string) ($claims['given_name'] ?? ''));
        $surname = trim((string) ($claims['family_name'] ?? ''));

        if ($name === '' && isset($claims['name'])) {
            $name = trim((string) $claims['name']);
        }

        return new FederatedIdentityPayload(
            FederatedProvider::GOOGLE,
            $providerUserId,
            $email,
            $emailVerified,
            $name,
            $surname,
            $claims
        );
    }

    public static function fromAppleClaims(array $claims): FederatedIdentityPayload
    {
        $providerUserId = trim((string) ($claims['sub'] ?? ''));
        $email = strtolower(trim((string) ($claims['email'] ?? '')));
        $emailVerified = self::toBool($claims['email_verified'] ?? false);

        $name = trim((string) ($claims['given_name'] ?? ''));
        $surname = trim((string) ($claims['family_name'] ?? ''));

        return new FederatedIdentityPayload(
            FederatedProvider::APPLE,
            $providerUserId,
            $email,
            $emailVerified,
            $name,
            $surname,
            $claims
        );
    }

    private static function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }
}
