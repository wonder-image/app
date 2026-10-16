<?php
/** php tests/OAuth/CleanupTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\Cleanup;
use Wonder\OAuth\Store\MemoryStore;

$s = new MemoryStore();
$now = time();
$d = static fn (int $t) => date('Y-m-d H:i:s', $t);
$day = 86400;

$s->insertToken('access', ['grant_id' => 1, 'selector' => 'old', 'validator_hash' => 'h', 'expires_at' => $d($now - 8 * $day)]);
$s->insertToken('access', ['grant_id' => 1, 'selector' => 'recent', 'validator_hash' => 'h', 'expires_at' => $d($now - 2 * $day)]);
$s->insertToken('refresh', ['grant_id' => 1, 'selector' => 'live', 'validator_hash' => 'h', 'expires_at' => $d($now + $day)]);
$s->createClient(['client_id' => 'idle', 'client_name' => '', 'redirect_uris' => '[]', 'token_endpoint_auth_method' => 'none', 'registration_ip' => '', 'creation' => $d($now - 31 * $day)]);
$s->createClient(['client_id' => 'used', 'client_name' => '', 'redirect_uris' => '[]', 'token_endpoint_auth_method' => 'none', 'registration_ip' => '', 'creation' => $d($now - 31 * $day)]);
$s->createGrant(['user_id' => 1, 'client_id' => 'used', 'resource' => 'r', 'scope' => '', 'consented_at' => $d($now)]);
$expired = $s->createGrant(['user_id' => 1, 'client_id' => 'used', 'resource' => 'r2', 'scope' => '', 'consented_at' => $d($now - 91 * $day)]);

$r = Cleanup::run($s, $now);

check('token morti da più di 7 giorni eliminati, gli altri restano', fn () =>
    $s->findToken('access', 'old') === null && $s->findToken('access', 'recent') !== null && $s->findToken('refresh', 'live') !== null);
check('client DCR inattivo senza grant eliminato', fn () => $s->findClient('idle') === null && $s->findClient('used') !== null);
check('grant oltre la scadenza assoluta revocato', fn () => $s->findGrant($expired)['revoked_at'] !== null && $r['grants'] === 1);

summary();
