<?php

namespace Wonder\OAuth;

/** JSON di discovery (RFC 9728, RFC 8414) e header WWW-Authenticate. */
final class Metadata
{
    public static function protectedResource(array $resource): array
    {
        return [
            'resource' => $resource['url'],
            'authorization_servers' => [Config::issuer()],
            'bearer_methods_supported' => ['header'],
            'resource_name' => $resource['name'],
        ];
    }

    public static function authorizationServer(): array
    {
        $iss = Config::issuer();

        return [
            'issuer' => $iss,
            'authorization_endpoint' => $iss.'/oauth/authorize',
            'token_endpoint' => $iss.'/oauth/token',
            'registration_endpoint' => $iss.'/oauth/register',
            'revocation_endpoint' => $iss.'/oauth/revoke',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'revocation_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => [],
        ];
    }

    public static function wwwAuthenticate(string $path, ?string $error = null, ?string $description = null): string
    {
        $header = 'Bearer resource_metadata="'.OAuth::resourceMetadataUrl($path).'"';
        if ($error !== null) {
            $header .= ', error="'.$error.'"';
        }
        if ($description !== null && $description !== '') {
            $header .= ', error_description="'.str_replace('"', '', $description).'"';
        }
        return $header;
    }
}
