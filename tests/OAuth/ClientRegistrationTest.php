<?php
/** php tests/OAuth/ClientRegistrationTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\OAuth\ClientRegistry;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\Store\MemoryStore;

ini_set('error_log', '/dev/null');
$store = new MemoryStore();
$reg = new ClientRegistry($store);
$fails = static function (callable $fn, string $error, int $status = 400): bool {
    try { $fn(); return false; } catch (OAuthException $e) { return $e->error === $error && $e->status === $status; }
};

check('claude.ai si registra', function () use ($reg) {
    $r = $reg->register(['client_name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback'],
        'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => 'none'], '1.1.1.1');
    return preg_match('/^wio_client_[a-f0-9]{32}$/', $r['client_id']) === 1
        && is_int($r['client_id_issued_at'])
        && $r['token_endpoint_auth_method'] === 'none'
        && !isset($r['client_secret'])
        && $r['redirect_uris'] === ['https://claude.ai/api/mcp/auth_callback'];
});

check('auth method assente → none', fn () =>
    $reg->register(['redirect_uris' => ['http://localhost:3000/callback']], '1.1.1.2')['token_endpoint_auth_method'] === 'none');

check('client_name assente → host del primo redirect', fn () =>
    $reg->register(['redirect_uris' => ['https://claude.ai/cb']], '1.1.1.3')['client_name'] === 'claude.ai');

check('client_name ripulito e troncato', function () use ($reg) {
    $r = $reg->register(['client_name' => "<b>Evil</b>\x07".str_repeat('x', 200), 'redirect_uris' => ['https://a.it/cb']], '1.1.1.4');
    return !str_contains($r['client_name'], '<') && !str_contains($r['client_name'], "\x07") && mb_strlen($r['client_name']) <= 100;
});

check('redirect_uris mancanti', fn () => $fails(fn () => $reg->register([], '2.2.2.1'), 'invalid_redirect_uri'));
check('più di 10 redirect', fn () => $fails(fn () => $reg->register(['redirect_uris' => array_fill(0, 11, 'https://a.it/cb')], '2.2.2.2'), 'invalid_redirect_uri'));
check('redirect http non loopback', fn () => $fails(fn () => $reg->register(['redirect_uris' => ['http://evil.example/cb']], '2.2.2.3'), 'invalid_redirect_uri'));
check('client_secret_basic rifiutato', fn () => $fails(fn () => $reg->register(['redirect_uris' => ['https://a.it/cb'], 'token_endpoint_auth_method' => 'client_secret_basic'], '2.2.2.4'), 'invalid_client_metadata'));
check('grant_types non ammessi', fn () => $fails(fn () => $reg->register(['redirect_uris' => ['https://a.it/cb'], 'grant_types' => ['password']], '2.2.2.5'), 'invalid_client_metadata'));
check('response_types non ammessi', fn () => $fails(fn () => $reg->register(['redirect_uris' => ['https://a.it/cb'], 'response_types' => ['token']], '2.2.2.6'), 'invalid_client_metadata'));

check('rate limit per IP → 429', function () use ($reg, $fails) {
    $_ENV['OAUTH_REGISTER_RATE_LIMIT'] = '2';
    $reg->register(['redirect_uris' => ['https://a.it/cb']], '3.3.3.3');
    $reg->register(['redirect_uris' => ['https://a.it/cb']], '3.3.3.3');
    $blocked = $fails(fn () => $reg->register(['redirect_uris' => ['https://a.it/cb']], '3.3.3.3'), 'too_many_requests', 429);
    $otherIp = isset($reg->register(['redirect_uris' => ['https://a.it/cb']], '3.3.3.4')['client_id']);
    unset($_ENV['OAUTH_REGISTER_RATE_LIMIT']);
    return $blocked && $otherIp;
});

check('rate limit di default (20): la 21ª registrazione dallo stesso IP → 429', function () use ($reg, $fails) {
    unset($_ENV['OAUTH_REGISTER_RATE_LIMIT']);
    $ids = [];
    for ($i = 0; $i < 20; $i++) { $ids[] = $reg->register(['redirect_uris' => ['https://a.it/cb']], '4.4.4.1')['client_id']; }
    $blocked = $fails(fn () => $reg->register(['redirect_uris' => ['https://a.it/cb']], '4.4.4.1'), 'too_many_requests', 429);
    return count(array_unique($ids)) === 20 && $blocked;
});

check('rate limit 0 = nessun limite: 25 registrazioni (oltre il default 20) tutte accettate', function () use ($reg) {
    $_ENV['OAUTH_REGISTER_RATE_LIMIT'] = '0';
    $ids = [];
    try {
        for ($i = 0; $i < 25; $i++) { $ids[] = $reg->register(['redirect_uris' => ['https://a.it/cb']], '4.4.4.4')['client_id']; }
    } catch (OAuthException) {
        // un 429 qui significa che il limite 0 non disattiva il controllo
    } finally {
        unset($_ENV['OAUTH_REGISTER_RATE_LIMIT']);
    }
    return count(array_unique($ids)) === 25;
});

check('find decodifica i redirect; revocato → null', function () use ($reg, $store) {
    $id = $reg->register(['redirect_uris' => ['https://a.it/cb']], '5.5.5.5')['client_id'];
    $found = $reg->find($id);
    $store->tables['clients'][$id]['revoked_at'] = date('Y-m-d H:i:s');
    return $found['redirect_uris'] === ['https://a.it/cb'] && $reg->find($id) === null && $reg->find('wio_client_nope') === null;
});

summary();
