<?php

namespace Wonder\OAuth;

use Wonder\OAuth\Store\OAuthStore;

/** Codice di autorizzazione monouso (OneTimeToken purpose oauth_code, TTL 5 minuti). */
final class AuthorizationCode
{
    public function __construct(private readonly OAuthStore $store) {}

    public function issue(int $userId, array $request, int $grantId): string
    {
        return $this->store->issueCode($userId, [
            'client_id' => $request['client_id'],
            'redirect_uri' => $request['redirect_uri'],
            'code_challenge' => $request['code_challenge'],
            'resource' => $request['resource'],
            'grant_id' => $grantId,
        ], Config::CODE_TTL);
    }

    public function consume(string $code, string $clientId, string $redirectUri, ?string $resource, string $verifier): array
    {
        $record = $code !== '' ? $this->store->consumeCode($code) : null;
        $m = $record['metadata'] ?? [];

        $resourceOk = $resource === null || $resource === '' || rtrim($resource, '/') === ($m['resource'] ?? null);

        if ($record === null
            || !hash_equals((string) ($m['client_id'] ?? ''), $clientId)
            || !hash_equals((string) ($m['redirect_uri'] ?? ''), $redirectUri)
            || !$resourceOk
            || !Pkce::verify($verifier, (string) ($m['code_challenge'] ?? ''))) {
            throw new OAuthException('invalid_grant', 'Invalid, expired or already used authorization code.');
        }

        return [
            'user_id' => (int) $record['user_id'],
            'grant_id' => (int) $m['grant_id'],
            'client_id' => (string) $m['client_id'],
            'resource' => (string) $m['resource'],
        ];
    }
}
