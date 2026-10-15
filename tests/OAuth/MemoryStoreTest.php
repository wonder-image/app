<?php
/** php tests/OAuth/MemoryStoreTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\Store\MemoryStore;

$s = new MemoryStore();
$now = date('Y-m-d H:i:s');

check('client: crea, trova, cancella', function () use ($s, $now) {
    $s->createClient(['client_id' => 'wio_client_a', 'client_name' => 'A', 'redirect_uris' => '["https://a.it/cb"]',
        'token_endpoint_auth_method' => 'none', 'registration_ip' => '1.2.3.4', 'creation' => $now]);
    $found = $s->findClient('wio_client_a');
    $s->deleteClient('wio_client_a');
    return $found['client_name'] === 'A' && $s->findClient('wio_client_a') === null;
});

check('countClientsFromIp conta solo da $since', function () use ($s) {
    $s->createClient(['client_id' => 'c1', 'client_name' => '', 'redirect_uris' => '[]', 'token_endpoint_auth_method' => 'none',
        'registration_ip' => '9.9.9.9', 'creation' => date('Y-m-d H:i:s', time() - 7200)]);
    $s->createClient(['client_id' => 'c2', 'client_name' => '', 'redirect_uris' => '[]', 'token_endpoint_auth_method' => 'none',
        'registration_ip' => '9.9.9.9', 'creation' => date('Y-m-d H:i:s')]);
    return $s->countClientsFromIp('9.9.9.9', date('Y-m-d H:i:s', time() - 3600)) === 1;
});

check('grant attivo e revoca a cascata', function () use ($s, $now) {
    $g = $s->createGrant(['user_id' => 7, 'client_id' => 'c2', 'resource' => 'https://s.it/api/x/mcp', 'scope' => '', 'consented_at' => $now]);
    $s->insertToken('access', ['grant_id' => $g, 'selector' => str_repeat('a', 16), 'validator_hash' => 'h', 'expires_at' => $now]);
    $s->insertToken('refresh', ['grant_id' => $g, 'selector' => str_repeat('b', 16), 'validator_hash' => 'h', 'expires_at' => $now]);
    $active = $s->findActiveGrant(7, 'c2', 'https://s.it/api/x/mcp');
    $s->revokeGrant($g, $now);
    return $active['id'] === $g
        && $s->findActiveGrant(7, 'c2', 'https://s.it/api/x/mcp') === null
        && $s->findToken('access', str_repeat('a', 16))['revoked_at'] !== null
        && $s->findToken('refresh', str_repeat('b', 16))['revoked_at'] !== null;
});

check('consumeRefresh è atomico', function () use ($s, $now) {
    $s->insertToken('refresh', ['grant_id' => 99, 'selector' => str_repeat('c', 16), 'validator_hash' => 'h', 'expires_at' => $now]);
    $id = $s->findToken('refresh', str_repeat('c', 16))['id'];
    return $s->consumeRefresh($id, $now) === true && $s->consumeRefresh($id, $now) === false;
});

check('codice monouso', function () use ($s) {
    $code = $s->issueCode(7, ['client_id' => 'c2'], 300);
    $first = $s->consumeCode($code);
    return $first['user_id'] === 7 && $first['metadata']['client_id'] === 'c2' && $s->consumeCode($code) === null;
});

check('codice scaduto', function () use ($s) {
    $code = $s->issueCode(7, [], -1);
    return $s->consumeCode($code) === null;
});

summary();
