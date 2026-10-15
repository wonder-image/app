<?php
/** php tests/OAuth/ConnectionsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Resources\OAuth\ConnectionsResource;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\Store\MemoryStore;

$_ENV['APP_URL'] = 'https://sito.it';
$store = new MemoryStore();
OAuth::useStore($store);
$now = date('Y-m-d H:i:s');
$store->createClient(['client_id' => 'c1', 'client_name' => 'Claude', 'redirect_uris' => '["https://claude.ai/cb"]', 'token_endpoint_auth_method' => 'none', 'registration_ip' => '1.1.1.1', 'creation' => $now]);
$g7 = $store->createGrant(['user_id' => 7, 'client_id' => 'c1', 'resource' => 'https://sito.it/api/gestionale/mcp', 'scope' => '', 'consented_at' => $now]);
$g8 = $store->createGrant(['user_id' => 8, 'client_id' => 'c1', 'resource' => 'https://sito.it/api/gestionale/mcp', 'scope' => '', 'consented_at' => $now]);
$store->users[7] = (object) ['id' => 7, 'name' => 'Anna', 'surname' => '', 'email' => 'a@x.it', 'authority' => ['editor'], 'active' => true, 'deleted' => false];
$store->users[8] = (object) ['id' => 8, 'name' => 'Bruno', 'surname' => '', 'email' => 'b@x.it', 'authority' => ['admin'], 'active' => true, 'deleted' => false];

check('utente normale vede solo i propri grant', fn () => array_column(ConnectionsResource::rows($store->users[7]), 'grant_id') === [$g7]);
check('admin vede tutti, con nome utente', function () use ($store) {
    $rows = ConnectionsResource::rows($store->users[8]);
    return count($rows) === 2 && in_array('Anna', array_column($rows, 'user_name'), true);
});
check('riga: client e dominio del redirect', fn () =>
    ConnectionsResource::rows($store->users[7])[0]['client_name'] === 'Claude' && ConnectionsResource::rows($store->users[7])[0]['redirect_host'] === 'claude.ai');
check('canRevoke: proprio sì, altrui no; admin sì', fn () =>
    ConnectionsResource::canRevoke($store->users[7], $store->findGrant($g7))
    && !ConnectionsResource::canRevoke($store->users[7], $store->findGrant($g8))
    && ConnectionsResource::canRevoke($store->users[8], $store->findGrant($g7)));
check('seeAll: admin sì, editor no, null no', fn () =>
    ConnectionsResource::seeAll($store->users[8]) && !ConnectionsResource::seeAll($store->users[7]) && !ConnectionsResource::seeAll(null));
check('rows(null) è vuoto', fn () => ConnectionsResource::rows(null) === []);

// APP_URL http fuori da locale: servers() degrada a null invece di lanciare, e rows() non esplode
check('issuer non valido: servers() null, nessuna eccezione', function () use ($store) {
    \Wonder\OAuth\ResourceRegistry::register('/api/gestionale/mcp', 'Gestionale');
    $_ENV['APP_URL'] = 'http://sito.it';
    $prevLog = ini_set('error_log', '/dev/null');
    try {
        $ok = !\Wonder\App\Environment::isLocal() ? (ConnectionsResource::servers() === null && count(ConnectionsResource::rows($store->users[8])) === 2) : true;
    } finally {
        $_ENV['APP_URL'] = 'https://sito.it';
        ini_set('error_log', (string) $prevLog);
        \Wonder\OAuth\ResourceRegistry::clear();
    }
    return $ok;
});
check('con issuer valido il nome risorsa viene risolto', function () use ($store) {
    \Wonder\OAuth\ResourceRegistry::register('/api/gestionale/mcp', 'Gestionale');
    $name = ConnectionsResource::rows($store->users[7])[0]['resource'];
    \Wonder\OAuth\ResourceRegistry::clear();
    return $name === 'Gestionale';
});

summary();
