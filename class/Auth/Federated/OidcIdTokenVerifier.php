<?php

namespace Wonder\Auth\Federated;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use RuntimeException;

/** Server-side OIDC ID-token verification with a small bounded JWKS cache. */
final class OidcIdTokenVerifier
{
    /** @param list<string> $issuers */
    public function __construct(
        private readonly string $audience,
        private readonly array $issuers,
        private readonly string $jwksUrl,
        private readonly ?string $expectedNonce = null,
        private readonly int $cacheTtlSeconds = 3600,
    ) {}

    public function claims(string $idToken): array
    {
        if (trim($idToken) === '' || trim($this->audience) === '') {
            throw new RuntimeException('oidc_token_or_audience_missing');
        }

        $decoded = (array) JWT::decode($idToken, JWK::parseKeySet($this->jwks()));
        $issuer = (string) ($decoded['iss'] ?? '');
        $audience = $decoded['aud'] ?? '';
        $audiences = is_array($audience) ? array_map('strval', $audience) : [(string) $audience];

        if (!in_array($issuer, $this->issuers, true)) {
            throw new RuntimeException('oidc_issuer_invalid');
        }

        if (!in_array($this->audience, $audiences, true)) {
            throw new RuntimeException('oidc_audience_invalid');
        }

        if ($this->expectedNonce !== null
            && !hash_equals($this->expectedNonce, (string) ($decoded['nonce'] ?? ''))) {
            throw new RuntimeException('oidc_nonce_invalid');
        }

        return $decoded;
    }

    private function jwks(): array
    {
        $cacheFile = rtrim(sys_get_temp_dir(), '/').'/wonder-oidc-'.hash('sha256', $this->jwksUrl).'.json';

        if (is_file($cacheFile) && filemtime($cacheFile) >= time() - max(60, $this->cacheTtlSeconds)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached) && isset($cached['keys'])) {
                return $cached;
            }
        }

        $context = stream_context_create(['http' => [
            'timeout' => 5,
            'follow_location' => 0,
            'user_agent' => 'WonderAuth/1.0',
        ]]);
        $body = @file_get_contents($this->jwksUrl, false, $context);
        $jwks = is_string($body) ? json_decode($body, true) : null;

        if (!is_array($jwks) || !isset($jwks['keys']) || !is_array($jwks['keys'])) {
            throw new RuntimeException('oidc_jwks_unavailable');
        }

        @file_put_contents($cacheFile, json_encode($jwks, JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $jwks;
    }
}
