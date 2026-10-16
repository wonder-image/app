<?php

namespace Wonder\OAuth;

/** Validazione di /oauth/authorize e richieste in sessione. */
final class AuthorizationRequest
{
    public static function validate(array $q, ClientRegistry $clients): array
    {
        // state e redirect_uri restano identici (RFC 6749): niente trim.
        $str = static fn (string $k): string => is_string($q[$k] ?? null)
            ? (in_array($k, ['state', 'redirect_uri'], true) ? $q[$k] : trim($q[$k]))
            : '';

        // 1. Prima del client e del redirect valido: pagina d'errore, mai redirect.
        $client = $clients->find($str('client_id'));
        $redirect = $str('redirect_uri');
        if ($client === null || $redirect === '' || !RedirectUri::isAllowed($redirect) || !RedirectUri::matchesAny($client['redirect_uris'], $redirect)) {
            throw new OAuthException('invalid_request', 'Unknown client or unregistered redirect_uri.');
        }

        $state = $str('state') !== '' ? $str('state') : null;
        $fail = static fn (string $error, string $desc) => new OAuthException($error, $desc, 302, [], $redirect, $state);

        // 2. Da qui gli errori tornano al client.
        if ($str('response_type') !== 'code') {
            throw $fail('unsupported_response_type', 'Only response_type=code is supported.');
        }
        if ($str('code_challenge_method') !== 'S256' || !Pkce::validChallenge($str('code_challenge'))) {
            throw $fail('invalid_request', 'PKCE with S256 is required.');
        }
        $resource = ResourceRegistry::findByUrl($str('resource'));
        if ($resource === null) {
            throw $fail('invalid_target', 'Unknown or missing resource.');
        }

        return [
            'client_id' => $client['client_id'],
            'client_name' => $client['client_name'],
            'redirect_uri' => $redirect,
            'code_challenge' => $str('code_challenge'),
            'resource' => $resource['url'],
            'resource_name' => $resource['name'],
            'state' => $state,
        ];
    }

    public static function store(array $request, array &$session, ?int $now = null): string
    {
        $now ??= time();
        $requests = self::live($session, $now);
        $id = bin2hex(random_bytes(16));
        $requests[$id] = ['request' => $request, 'created' => $now];

        uasort($requests, static fn ($a, $b) => $a['created'] <=> $b['created']);
        while (count($requests) > Config::MAX_REQUESTS) {
            array_shift($requests);
        }

        $session['oauth_requests'] = $requests;
        return $id;
    }

    public static function find(array $session, string $id, ?int $now = null): ?array
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            return null;
        }
        return self::live($session, $now ?? time())[$id]['request'] ?? null;
    }

    public static function forget(array &$session, string $id): void
    {
        unset($session['oauth_requests'][$id]);
    }

    /** Logout per "Cambia account" senza perdere la richiesta: logoutUser() svuota l'intera sessione. */
    public static function switchAccount(array &$session, string $id, callable $logout): void
    {
        $request = self::find($session, $id);
        $logout();
        if ($request !== null) {
            $session['oauth_requests'][$id] = ['request' => $request, 'created' => time()];
        }
    }

    public static function redirectUrl(string $redirectUri, array $params): string
    {
        $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
        $sep = str_contains($redirectUri, '?') ? '&' : '?';
        return $redirectUri.$sep.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private static function live(array $session, int $now): array
    {
        $requests = is_array($session['oauth_requests'] ?? null) ? $session['oauth_requests'] : [];
        return array_filter($requests, static fn ($r) => is_array($r) && ($r['created'] ?? 0) + Config::REQUEST_TTL >= $now);
    }
}
