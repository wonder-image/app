<?php
/** php tests/OAuth/AuthorizationRequestTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\AuthorizationRequest;
use Wonder\OAuth\ClientRegistry;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\Pkce;
use Wonder\OAuth\Store\MemoryStore;

$_ENV['APP_URL'] = 'https://sito.it';
OAuth::reset();
OAuth::resource('/api/gestionale/mcp', 'Gestionale');
$clients = new ClientRegistry(new MemoryStore());
$client = $clients->register(['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback', 'http://localhost/callback']], '1.1.1.1');

$q = static fn (array $over = []) => $over + [
    'response_type' => 'code', 'client_id' => $client['client_id'], 'redirect_uri' => 'https://claude.ai/api/mcp/auth_callback',
    'code_challenge' => Pkce::challenge(str_repeat('v', 43)), 'code_challenge_method' => 'S256',
    'resource' => 'https://sito.it/api/gestionale/mcp', 'state' => 'xyz',
];
$err = static function (callable $fn): ?OAuthException { try { $fn(); return null; } catch (OAuthException $e) { return $e; } };

check('richiesta valida normalizzata', function () use ($q, $clients) {
    $r = AuthorizationRequest::validate($q(), $clients);
    return $r['client_name'] === 'Claude' && $r['resource'] === 'https://sito.it/api/gestionale/mcp' && $r['resource_name'] === 'Gestionale' && $r['state'] === 'xyz';
});
check('loopback con porta diversa accettato', fn () =>
    AuthorizationRequest::validate($q(['redirect_uri' => 'http://localhost:51234/callback']), $clients)['redirect_uri'] === 'http://localhost:51234/callback');
check('client sconosciuto → pagina, niente redirect', function () use ($q, $clients, $err) {
    $e = $err(fn () => AuthorizationRequest::validate($q(['client_id' => 'wio_client_x']), $clients));
    return $e !== null && $e->redirectUri === null;
});
check('redirect non registrato → pagina, niente redirect', function () use ($q, $clients, $err) {
    $e = $err(fn () => AuthorizationRequest::validate($q(['redirect_uri' => 'https://evil.example/cb']), $clients));
    return $e !== null && $e->redirectUri === null;
});
check('response_type errato → redirect unsupported_response_type', function () use ($q, $clients, $err) {
    $e = $err(fn () => AuthorizationRequest::validate($q(['response_type' => 'token']), $clients));
    return $e->error === 'unsupported_response_type' && $e->redirectUri === 'https://claude.ai/api/mcp/auth_callback' && $e->state === 'xyz';
});
check('PKCE plain → invalid_request', fn () => $err(fn () => AuthorizationRequest::validate($q(['code_challenge_method' => 'plain']), $clients))->error === 'invalid_request');
check('PKCE mancante → invalid_request', fn () => $err(fn () => AuthorizationRequest::validate($q(['code_challenge' => '']), $clients))->error === 'invalid_request');
check('resource sconosciuta → invalid_target', fn () => $err(fn () => AuthorizationRequest::validate($q(['resource' => 'https://sito.it/api/altro']), $clients))->error === 'invalid_target');
check('resource mancante → invalid_target', fn () => $err(fn () => AuthorizationRequest::validate($q(['resource' => '']), $clients))->error === 'invalid_target');

check('redirectUrl aggiunge iss e conserva la query', fn () =>
    AuthorizationRequest::redirectUrl('https://a.it/cb?x=1', ['code' => 'c', 'state' => 's', 'iss' => 'https://sito.it'])
    === 'https://a.it/cb?x=1&code=c&state=s&iss=https%3A%2F%2Fsito.it');

check('sessione: TTL di 10 minuti', function () {
    $s = [];
    $id = AuthorizationRequest::store(['client_id' => 'a'], $s, 1000);
    return preg_match('/^[a-f0-9]{32}$/', $id) === 1
        && AuthorizationRequest::find($s, $id, 1000 + 599) !== null
        && AuthorizationRequest::find($s, $id, 1000 + 601) === null;
});
check('sessione: al massimo 10 richieste, la più vecchia esce', function () {
    $s = [];
    $first = AuthorizationRequest::store(['n' => 0], $s, 1000);
    for ($i = 1; $i <= 10; $i++) { AuthorizationRequest::store(['n' => $i], $s, 1000 + $i); }
    return count($s['oauth_requests']) === 10 && AuthorizationRequest::find($s, $first, 1011) === null;
});
check('forget', function () {
    $s = [];
    $id = AuthorizationRequest::store(['a' => 1], $s);
    AuthorizationRequest::forget($s, $id);
    return AuthorizationRequest::find($s, $id) === null;
});

// Review Focus 1: "Cambia account" svuota la sessione ma la richiesta sopravvive.
check('switchAccount conserva la richiesta', function () {
    $s = ['user_id' => 7];
    $id = AuthorizationRequest::store(['client_id' => 'a'], $s);
    AuthorizationRequest::switchAccount($s, $id, function () use (&$s) { $s = []; });
    return !isset($s['user_id']) && AuthorizationRequest::find($s, $id)['client_id'] === 'a';
});

summary();
