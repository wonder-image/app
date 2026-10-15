<?php
/** php tests/OAuth/ResourceGuardTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\ClientRegistry;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\ResourceGuard;
use Wonder\OAuth\Store\MemoryStore;
use Wonder\OAuth\TokenIssuer;

$_ENV['APP_URL'] = 'https://sito.it';
OAuth::reset();
OAuth::resource('/api/gestionale/mcp', 'Gestionale');
OAuth::resource('/api/altro/mcp', 'Altro');
$store = new MemoryStore();
OAuth::useStore($store);
$store->users[7] = (object) ['id' => 7, 'active' => true, 'deleted' => false, 'authority' => ['admin'], 'area' => ['backend'], 'name' => 'Anna'];
$client = (new ClientRegistry($store))->register(['redirect_uris' => ['https://claude.ai/cb']], '1.1.1.1')['client_id'];
$issuer = new TokenIssuer($store);
$mint = static fn (string $res = 'https://sito.it/api/gestionale/mcp') => $issuer->issue($issuer->grantFor(7, $client, $res))['access_token'];
$auth = static fn (string $bearer) => ['HTTP_AUTHORIZATION' => 'Bearer '.$bearer];
$prm = 'resource_metadata="https://sito.it/.well-known/oauth-protected-resource/api/gestionale/mcp"';
$reject = static function (array $server) use ($prm): ?string {
    try { ResourceGuard::authenticate('/api/gestionale/mcp', $server); return null; }
    catch (OAuthException $e) { return $e->status === 401 && str_contains($e->headers['WWW-Authenticate'] ?? '', $prm) ? ($e->headers['WWW-Authenticate']) : 'BAD'; }
};

check('token valido → OAuthCaller', function () use ($mint, $auth, $client) {
    $c = ResourceGuard::authenticate('/api/gestionale/mcp/', $auth($mint()));
    return $c->userId === 7 && $c->clientId === $client && $c->resource === 'https://sito.it/api/gestionale/mcp' && $c->user->name === 'Anna' && $c->grantId > 0;
});
check('fallback REDIRECT_HTTP_AUTHORIZATION', fn () =>
    ResourceGuard::authenticate('/api/gestionale/mcp', ['REDIRECT_HTTP_AUTHORIZATION' => 'Bearer '.$mint()])->userId === 7);

// Review Focus 5
check('senza token: corpo senza codice error', function () {
    try { ResourceGuard::authenticate('/api/gestionale/mcp', []); return false; }
    catch (OAuthException $e) { return $e->status === 401 && !array_key_exists('error', $e->toArray()); }
});

check('senza token: 401 con solo resource_metadata', function () use ($reject) {
    $h = $reject([]);
    return $h !== null && $h !== 'BAD' && !str_contains($h, 'error=');
});
check('token scaduto: invalid_token', function () use ($mint, $auth, $store, $reject) {
    $t = $mint();
    [$sel] = TokenIssuer::parse($t, 'wio_at_');
    $store->tables['access'][$sel]['expires_at'] = date('Y-m-d H:i:s', time() - 1);
    return str_contains((string) $reject($auth($t)), 'error="invalid_token"');
});
check('token revocato', function () use ($mint, $auth, $store, $reject) {
    $t = $mint();
    [$sel] = TokenIssuer::parse($t, 'wio_at_');
    $store->revokeGrant($store->findToken('access', $sel)['grant_id'], date('Y-m-d H:i:s'));
    return str_contains((string) $reject($auth($t)), 'invalid_token');
});
check('audience diversa → rifiuto', fn () => str_contains((string) $reject($auth($mint('https://sito.it/api/altro/mcp'))), 'invalid_token'));
check('JWT → rifiuto', fn () => str_contains((string) $reject($auth('eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOjF9.c2ln')), 'invalid_token'));
check('validator manomesso → rifiuto', function () use ($mint, $auth, $reject) {
    $t = $mint();
    return str_contains((string) $reject($auth(substr($t, 0, -1).(str_ends_with($t, 'a') ? 'b' : 'a'))), 'invalid_token');
});
check('refresh token usato come access → rifiuto', function () use ($issuer, $client, $auth, $reject) {
    $rt = $issuer->issue($issuer->grantFor(7, $client, 'https://sito.it/api/gestionale/mcp'))['refresh_token'];
    return str_contains((string) $reject($auth($rt)), 'invalid_token');
});
check('utente disattivato → rifiuto', function () use ($mint, $auth, $store, $reject) {
    $t = $mint();
    $store->users[7]->active = false;
    $h = $reject($auth($t));
    $store->users[7]->active = true;
    return str_contains((string) $h, 'invalid_token');
});
check('last_used_at aggiornato al massimo una volta al minuto', function () use ($mint, $auth, $store) {
    $t = $mint();
    [$sel] = TokenIssuer::parse($t, 'wio_at_');
    $gid = $store->findToken('access', $sel)['grant_id'];
    $store->tables['grants'][$gid]['last_used_at'] = date('Y-m-d H:i:s', time() - 30);
    ResourceGuard::authenticate('/api/gestionale/mcp', $auth($t));
    $unchanged = $store->tables['grants'][$gid]['last_used_at'] === date('Y-m-d H:i:s', time() - 30);
    $store->tables['grants'][$gid]['last_used_at'] = date('Y-m-d H:i:s', time() - 120);
    ResourceGuard::authenticate('/api/gestionale/mcp', $auth($t));
    return $unchanged && strtotime($store->tables['grants'][$gid]['last_used_at']) >= time() - 2;
});

summary();
