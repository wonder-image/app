<?php

namespace Wonder\Auth\Federated;

final class FederatedProvider
{
    public const GOOGLE = 'google';
    public const APPLE = 'apple';

    public static function normalize(string $provider): string
    {
        return strtolower(trim($provider));
    }

    public static function isSupported(string $provider): bool
    {
        $provider = self::normalize($provider);

        return in_array($provider, [self::GOOGLE, self::APPLE], true);
    }
}
