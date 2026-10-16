<?php
/** php tests/OAuth/TokenIssuerTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\ClientRegistry;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\Pkce;
use Wonder\OAuth\Store\MemoryStore;
use Wonder\OAuth\TokenEndpoint;
use Wonder\OAuth\TokenIssuer;
use Wonder\OAuth\AuthorizationCode;

$_ENV['APP_URL'] = 'https://sito.it';
OAuth::reset();
OAuth::resource('/api/gestionale/mcp', 'Gestionale');

$store = new MemoryStore();
$store->users[7] = (object) ['id' => 7, 'active' => true, 'deleted' => false, 'authority' => ['admin'], 'area' => ['backend'], 'name' => 'Anna'];
$client = (new ClientRegistry($store))->register(['redirect_uris' => ['http://localhost/callback']], '1.1.1.1')['client_id'];
$res = 'https://sito.it/api/gestionale/mcp';
$issuer = new TokenIssuer($store);
$endpoint = new TokenEndpoint($store);
$err = static function (callable $fn): ?OAuthException { try { $fn(); return null; } catch (OAuthException $e) { return $e; } };

$pair = function () use ($store, $client, $res, $endpoint) {
    $verifier = bin2hex(random_bytes(30));
    $grant = (new TokenIssuer($store))->grantFor(7, $client, $res);
    $code = (new AuthorizationCode($store))->issue(7, ['client_id' => $client, 'redirect_uri' => 'http://localhost:5000/callback', 'code_challenge' => Pkce::challenge($verifier), 'resource' => $res], $grant);
    return $endpoint->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => 'http://localhost:5000/callback', 'client_id' => $client, 'code_verifier' => $verifier, 'resource' => $res]);
};

check('authorization_code → coppia di token', function () use ($pair) {
    $t = $pair();
    return preg_match('/^wio_at_[a-f0-9]{16}\.[a-f0-9]{64}$/', $t['access_token']) === 1
        && preg_match('/^wio_rt_[a-f0-9]{16}\.[a-f0-9]{64}$/', $t['refresh_token']) === 1
        && $t['token_type'] === 'Bearer' && $t['expires_in'] === 3600;
});

check('expires_in segue OAUTH_ACCESS_TOKEN_TTL', function () use ($pair) {
    $_ENV['OAUTH_ACCESS_TOKEN_TTL'] = '600';
    $t = $pair();
    unset($_ENV['OAUTH_ACCESS_TOKEN_TTL']);
    return $t['expires_in'] === 600;
});

check('nel DB solo selector e hash', function () use ($pair, $store) {
    $t = $pair();
    [$sel, $val] = TokenIssuer::parse($t['access_token'], 'wio_at_');
    $row = $store->findToken('access', $sel);
    return $row['validator_hash'] === hash('sha256', $val) && !in_array($val, $row, true);
});

check('grantFor riusa il grant attivo', fn () => $issuer->grantFor(7, $client, $res) === $issuer->grantFor(7, $client, $res));

check('refresh ruota: nuova coppia, stesso grant', function () use ($pair, $endpoint, $client, $store) {
    $t = $pair();
    $n = $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]);
    [$oldSel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    [$newSel] = TokenIssuer::parse($n['refresh_token'], 'wio_rt_');
    return $n['refresh_token'] !== $t['refresh_token']
        && $store->findToken('refresh', $oldSel)['consumed_at'] !== null
        && $store->findToken('refresh', $oldSel)['grant_id'] === $store->findToken('refresh', $newSel)['grant_id'];
});

check('riuso del refresh → grant revocato e invalid_grant', function () use ($pair, $endpoint, $client, $store, $err) {
    $t = $pair();
    $n = $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]);
    $e = $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]));
    [$sel] = TokenIssuer::parse($n['access_token'], 'wio_at_');
    $grant = $store->findGrant($store->findToken('access', $sel)['grant_id']);
    return $e?->error === 'invalid_grant' && $e->status === 400 && $grant['revoked_at'] !== null && $store->findToken('access', $sel)['revoked_at'] !== null;
});

check('refresh con resource diversa → invalid_target', function () use ($pair, $endpoint, $client, $err) {
    $t = $pair();
    return $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client, 'resource' => 'https://sito.it/api/altro']))?->error === 'invalid_target';
});

check('refresh scaduto → invalid_grant', function () use ($pair, $endpoint, $client, $store, $err) {
    $t = $pair();
    [$sel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    $store->tables['refresh'][$sel]['expires_at'] = date('Y-m-d H:i:s', time() - 1);
    return $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]))?->error === 'invalid_grant';
});

check('grant oltre la scadenza assoluta → invalid_grant', function () use ($pair, $endpoint, $client, $store, $err) {
    $t = $pair();
    [$sel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    $gid = $store->findToken('refresh', $sel)['grant_id'];
    $orig = $store->tables['grants'][$gid]['consented_at'];
    try {
        $store->tables['grants'][$gid]['consented_at'] = date('Y-m-d H:i:s', time() - 7776001);
        $e = $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]));
    } finally {
        $store->tables['grants'][$gid]['consented_at'] = $orig;
    }
    return $e?->error === 'invalid_grant';
});

check('utente disattivato → invalid_grant', function () use ($pair, $endpoint, $client, $store, $err) {
    $t = $pair();
    $store->users[7]->active = false;
    try {
        $e = $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]));
    } finally {
        $store->users[7]->active = true;
    }
    return $e?->error === 'invalid_grant';
});

check('client cancellato → 401 invalid_client', function () use ($pair, $endpoint, $client, $store, $err) {
    $t = $pair();
    $saved = $store->tables['clients'][$client];
    try {
        $store->deleteClient($client);
        $e = $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]));
    } finally {
        $store->tables['clients'][$client] = $saved;
    }
    return $e?->error === 'invalid_client' && $e->status === 401;
});

check('refresh di un altro client → invalid_grant', function () use ($pair, $endpoint, $store, $err) {
    $t = $pair();
    $other = (new ClientRegistry($store))->register(['redirect_uris' => ['https://x.it/cb']], '1.1.1.9')['client_id'];
    return $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $other]))?->error === 'invalid_grant';
});

check('grant_type sconosciuto → unsupported_grant_type', fn () =>
    $err(fn () => $endpoint->token(['grant_type' => 'password', 'client_id' => $client]))?->error === 'unsupported_grant_type');

check('revoke revoca il grant; token sconosciuto non lancia', function () use ($pair, $endpoint, $client, $store) {
    $t = $pair();
    $endpoint->revoke(['token' => $t['access_token'], 'client_id' => $client]);
    $endpoint->revoke(['token' => 'wio_at_nope', 'client_id' => $client]);
    [$sel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    return $store->findToken('refresh', $sel)['revoked_at'] !== null;
});

check('revoke con client diverso non revoca', function () use ($pair, $endpoint, $store) {
    $t = $pair();
    $endpoint->revoke(['token' => $t['refresh_token'], 'client_id' => 'wio_client_altro']);
    [$sel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    return $store->findToken('refresh', $sel)['revoked_at'] === null;
});

check('grant revocato → refresh invalid_grant (usableGrant)', function () use ($pair, $endpoint, $client, $store, $err) {
    $t = $pair();
    [$sel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    $gid = $store->findToken('refresh', $sel)['grant_id'];
    $store->tables['grants'][$gid]['revoked_at'] = date('Y-m-d H:i:s');
    return $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]))?->error === 'invalid_grant'
        && (new TokenIssuer($store))->usableGrant((int) $gid) === null;
});

check('grantFor non resuscita un grant oltre il max age', function () use ($pair, $endpoint, $client, $store, $res, $err) {
    $t = $pair();
    [$sel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    $gid = $store->findToken('refresh', $sel)['grant_id'];
    $store->tables['grants'][$gid]['consented_at'] = date('Y-m-d H:i:s', time() - 7776001);
    $new = (new TokenIssuer($store))->grantFor(7, $client, $res);
    $e = $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]));
    return $new !== $gid && $e?->error === 'invalid_grant' && $store->findGrant($gid)['revoked_at'] !== null;
});

check('refresh consumato presentato da un altro client non revoca il grant', function () use ($pair, $endpoint, $client, $store, $err) {
    $t = $pair();
    $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $client]);
    $other = (new ClientRegistry($store))->register(['redirect_uris' => ['https://y.it/cb']], '1.1.1.8')['client_id'];
    $e = $err(fn () => $endpoint->token(['grant_type' => 'refresh_token', 'refresh_token' => $t['refresh_token'], 'client_id' => $other]));
    [$sel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    return $e?->error === 'invalid_grant' && $store->findGrant($store->findToken('refresh', $sel)['grant_id'])['revoked_at'] === null;
});

check('client con revoked_at → refresh e usableGrant falliscono', function () use ($pair, $endpoint, $client, $store, $err) {
    $t = $pair();
    [$sel] = TokenIssuer::parse($t['refresh_token'], 'wio_rt_');
    $gid = (int) $store->findToken('refresh', $sel)['grant_id'];
    try {
        $store->tables['clients'][$client]['revoked_at'] = date('Y-m-d H:i:s');
        $e = $err(fn () => (new TokenIssuer($store))->refresh($t['refresh_token'], $client, null));
        $u = (new TokenIssuer($store))->usableGrant($gid);
    } finally {
        $store->tables['clients'][$client]['revoked_at'] = null;
    }
    return $e !== null && $u === null;
});

summary();
