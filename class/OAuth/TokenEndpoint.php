<?php

namespace Wonder\OAuth;

use Wonder\OAuth\Store\OAuthStore;

/** /oauth/token e /oauth/revoke: parametri form-urlencoded, errori RFC 6749. */
final class TokenEndpoint
{
    private readonly ClientRegistry $clients;
    private readonly TokenIssuer $issuer;
    private readonly AuthorizationCode $codes;

    public function __construct(private readonly OAuthStore $store)
    {
        $this->clients = new ClientRegistry($store);
        $this->issuer = new TokenIssuer($store);
        $this->codes = new AuthorizationCode($store);
    }

    public function token(array $post): array
    {
        $p = static fn (string $k): string => is_string($post[$k] ?? null) ? trim($post[$k]) : '';
        $clientId = $p('client_id');
        $grantType = $p('grant_type');

        if (!in_array($grantType, ['authorization_code', 'refresh_token'], true)) {
            throw new OAuthException('unsupported_grant_type', 'Supported: authorization_code, refresh_token.');
        }
        if ($this->clients->find($clientId) === null) {
            throw new OAuthException('invalid_client', 'Client not found.', 401);
        }

        $resource = $p('resource') !== '' ? $p('resource') : null;

        if ($grantType === 'refresh_token') {
            $result = $this->issuer->refresh($p('refresh_token'), $clientId, $resource, $p('scope'));
        } else {
            $code = $this->codes->consume($p('code'), $clientId, $p('redirect_uri'), $resource, $p('code_verifier'));
            if ($this->issuer->usableGrant($code['grant_id']) === null) {
                throw new OAuthException('invalid_grant', 'Grant revoked or user not active.');
            }
            $result = $this->issuer->issue($code['grant_id']);
        }

        $this->clients->touch($clientId);
        return $result;
    }

    /** RFC 7009: risponde sempre 200, anche per token sconosciuti. */
    public function revoke(array $post): void
    {
        $token = is_string($post['token'] ?? null) ? trim($post['token']) : '';
        $clientId = is_string($post['client_id'] ?? null) ? trim($post['client_id']) : '';
        if ($token !== '' && $clientId !== '') {
            $this->issuer->revokeByToken($token, $clientId);
        }
    }
}
