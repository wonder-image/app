<?php
/** php tests/OAuth/AuthorizationCodeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\AuthorizationCode;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\Pkce;
use Wonder\OAuth\Store\MemoryStore;

$store = new MemoryStore();
$codes = new AuthorizationCode($store);
$verifier = str_repeat('k', 50);
$req = ['client_id' => 'wio_client_a', 'redirect_uri' => 'http://localhost:4000/callback',
    'code_challenge' => Pkce::challenge($verifier), 'resource' => 'https://sito.it/api/gestionale/mcp'];
$grantError = static function (callable $fn): bool { try { $fn(); return false; } catch (OAuthException $e) { return $e->error === 'invalid_grant' && $e->status === 400; } };

check('scambio valido', function () use ($codes, $req, $verifier) {
    $code = $codes->issue(7, $req, 3);
    $r = $codes->consume($code, 'wio_client_a', 'http://localhost:4000/callback', 'https://sito.it/api/gestionale/mcp', $verifier);
    return $r === ['user_id' => 7, 'grant_id' => 3, 'client_id' => 'wio_client_a', 'resource' => 'https://sito.it/api/gestionale/mcp'];
});
check('monouso', function () use ($codes, $req, $verifier, $grantError) {
    $code = $codes->issue(7, $req, 3);
    $codes->consume($code, 'wio_client_a', $req['redirect_uri'], null, $verifier);
    return $grantError(fn () => $codes->consume($code, 'wio_client_a', $req['redirect_uri'], null, $verifier));
});
check('resource assente → vale quella del codice', fn () =>
    $codes->consume($codes->issue(7, $req, 3), 'wio_client_a', $req['redirect_uri'], null, $verifier)['resource'] === $req['resource']);
check('client diverso', fn () => $grantError(fn () => $codes->consume($codes->issue(7, $req, 3), 'wio_client_b', $req['redirect_uri'], null, $verifier)));
check('redirect diverso (confronto esatto, anche loopback)', fn () => $grantError(fn () => $codes->consume($codes->issue(7, $req, 3), 'wio_client_a', 'http://localhost:4001/callback', null, $verifier)));
check('resource diversa', fn () => $grantError(fn () => $codes->consume($codes->issue(7, $req, 3), 'wio_client_a', $req['redirect_uri'], 'https://sito.it/api/altro', $verifier)));
check('verifier sbagliato', fn () => $grantError(fn () => $codes->consume($codes->issue(7, $req, 3), 'wio_client_a', $req['redirect_uri'], null, str_repeat('z', 50))));
check('codice inesistente', fn () => $grantError(fn () => $codes->consume('nope', 'wio_client_a', $req['redirect_uri'], null, $verifier)));
check('codice scaduto', function () use ($store, $codes, $req, $verifier, $grantError) {
    $code = $codes->issue(7, $req, 3);
    $store->tables['codes'][$code]['expires'] = time() - 1;
    return $grantError(fn () => $codes->consume($code, 'wio_client_a', $req['redirect_uri'], null, $verifier));
});
// Review Focus 2: due flussi paralleli dello stesso utente.
check('un secondo codice non revoca il primo', function () use ($codes, $req, $verifier) {
    $a = $codes->issue(7, $req, 3);
    $b = $codes->issue(7, $req, 4);
    return $codes->consume($a, 'wio_client_a', $req['redirect_uri'], null, $verifier)['grant_id'] === 3
        && $codes->consume($b, 'wio_client_a', $req['redirect_uri'], null, $verifier)['grant_id'] === 4;
});

summary();
