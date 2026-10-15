<?php

namespace Wonder\OAuth;

use Wonder\OAuth\Store\OAuthStore;

/** Dynamic Client Registration (RFC 7591), solo client pubblici. */
final class ClientRegistry
{
    public function __construct(private readonly OAuthStore $store) {}

    public function register(array $body, string $ip): array
    {
        $limit = Config::registerRateLimit();
        if ($limit > 0 && $this->store->countClientsFromIp($ip, date('Y-m-d H:i:s', time() - 3600)) >= $limit) {
            throw new OAuthException('too_many_requests', 'Too many client registrations from this address.', 429, ['Retry-After' => '3600']);
        }

        $uris = $body['redirect_uris'] ?? null;
        if (!is_array($uris) || count($uris) < 1 || count($uris) > 10) {
            throw new OAuthException('invalid_redirect_uri', 'redirect_uris must contain 1 to 10 URIs.');
        }
        $uris = array_values($uris);
        foreach ($uris as $uri) {
            if (!is_string($uri) || !RedirectUri::isAllowed($uri)) {
                throw new OAuthException('invalid_redirect_uri', 'Redirect URIs must be https or loopback http, without fragment or userinfo.');
            }
        }

        $method = $body['token_endpoint_auth_method'] ?? 'none';
        if ($method !== 'none') {
            throw new OAuthException('invalid_client_metadata', 'Only public clients (token_endpoint_auth_method=none) are supported.');
        }

        $grantTypes = $body['grant_types'] ?? ['authorization_code', 'refresh_token'];
        $responseTypes = $body['response_types'] ?? ['code'];
        if (!is_array($grantTypes) || array_diff($grantTypes, ['authorization_code', 'refresh_token']) !== []
            || !is_array($responseTypes) || array_diff($responseTypes, ['code']) !== []) {
            throw new OAuthException('invalid_client_metadata', 'Unsupported grant_types or response_types.');
        }

        $name = $this->cleanName($body['client_name'] ?? null) ?: RedirectUri::host($uris[0]);
        $clientId = 'wio_client_'.bin2hex(random_bytes(16));
        $now = time();

        $this->store->createClient([
            'client_id' => $clientId,
            'client_name' => $name,
            'redirect_uris' => json_encode($uris, JSON_UNESCAPED_SLASHES),
            'token_endpoint_auth_method' => 'none',
            'registration_ip' => $ip,
            'creation' => date('Y-m-d H:i:s', $now),
        ]);

        return [
            'client_id' => $clientId,
            'client_id_issued_at' => $now,
            'client_name' => $name,
            'redirect_uris' => $uris,
            'grant_types' => array_values($grantTypes),
            'response_types' => array_values($responseTypes),
            'token_endpoint_auth_method' => 'none',
        ];
    }

    public function find(string $clientId): ?array
    {
        if ($clientId === '' || strlen($clientId) > 64) {
            return null;
        }
        $row = $this->store->findClient($clientId);
        if ($row === null || !empty($row['revoked_at'])) {
            return null;
        }
        $uris = json_decode((string) $row['redirect_uris'], true);
        $row['redirect_uris'] = is_array($uris) ? $uris : [];
        return $row;
    }

    public function touch(string $clientId): void
    {
        $this->store->touchClient($clientId, date('Y-m-d H:i:s'));
    }

    private function cleanName(mixed $name): string
    {
        if (!is_string($name)) {
            return '';
        }
        $name = strip_tags($name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        return mb_substr(trim($name), 0, 100);
    }
}
