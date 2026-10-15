<?php

namespace Wonder\OAuth;

/** Autenticazione dei bearer `wio_at_` per le risorse dei moduli. Le autorizzazioni restano alla risorsa. */
final class ResourceGuard
{
    public static function authenticate(string $resourcePath, ?array $server = null): OAuthCaller
    {
        $server ??= $_SERVER;
        $path = '/'.trim($resourcePath, '/');
        $bearer = self::bearer($server);

        if ($bearer === '') {
            throw new OAuthException('invalid_request', 'Missing bearer token.', 401, ['WWW-Authenticate' => Metadata::wwwAuthenticate($path)]);
        }

        $invalid = static fn (string $why) => new OAuthException('invalid_token', $why, 401, ['WWW-Authenticate' => Metadata::wwwAuthenticate($path, 'invalid_token', $why)]);

        $resource = ResourceRegistry::findByPath($path);
        $store = OAuth::store();
        $issuer = new TokenIssuer($store);
        $row = $issuer->lookup('access', $bearer, 'wio_at_');

        if ($resource === null || $row === null || !empty($row['revoked_at']) || strtotime((string) $row['expires_at']) < time()) {
            throw $invalid('The access token is invalid or expired.');
        }

        $usable = $issuer->usableGrant((int) $row['grant_id']);
        if ($usable === null || !hash_equals($resource['url'], (string) $usable['grant']['resource'])) {
            throw $invalid('The access token is not valid for this resource.');
        }

        $grant = $usable['grant'];
        $now = time();
        if (empty($grant['last_used_at']) || strtotime((string) $grant['last_used_at']) < $now - 60) {
            $stamp = date('Y-m-d H:i:s', $now);
            $store->updateGrant((int) $grant['id'], ['last_used_at' => $stamp]);
            $store->touchClient((string) $grant['client_id'], $stamp);
        }

        return new OAuthCaller($usable['user'], (int) $grant['user_id'], (int) $grant['id'], (string) $grant['client_id'], (string) $grant['resource']);
    }

    /** Bearer da Authorization, con fallback su REDIRECT_HTTP_AUTHORIZATION e getallheaders() (Apache CGI). */
    public static function bearer(array $server): string
    {
        $header = (string) ($server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');

        if ($header === '' && $server === $_SERVER && function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $header = (string) $value;
                    break;
                }
            }
        }

        return preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $m) === 1 ? $m[1] : '';
    }
}
