<?php

namespace Wonder\OAuth;

/** PKCE solo S256 (RFC 7636). */
final class Pkce
{
    public static function validVerifier(string $verifier): bool
    {
        return preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', $verifier) === 1;
    }

    public static function validChallenge(string $challenge): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge) === 1;
    }

    public static function challenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    public static function verify(string $verifier, string $challenge): bool
    {
        return self::validVerifier($verifier) && hash_equals($challenge, self::challenge($verifier));
    }
}
