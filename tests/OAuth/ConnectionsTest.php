<?php
/** php tests/OAuth/ConnectionsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\LegacyGlobals;
use Wonder\App\Resources\OAuth\{ConnectionsResource, GrantResource};
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

function use_user(?object $user): void { LegacyGlobals::set('USER', $user); }

check('querySchema: utente normale filtrato sui propri grant', function () use ($store) {
    use_user($store->users[7]);
    return GrantResource::querySchema()['condition'] === "deleted = 'false' AND revoked_at IS NULL AND user_id = 7";
});
check('querySchema: admin vede tutti i grant attivi', function () use ($store) {
    use_user($store->users[8]);
    return GrantResource::querySchema()['condition'] === "deleted = 'false' AND revoked_at IS NULL";
});
check('querySchema: senza utente nessuna riga', function () {
    use_user(null);
    return GrantResource::querySchema()['condition'] === '1 = 0';
});
check('querySchema: più recenti prima', fn () => GrantResource::querySchema()['order'] === ['column' => 'consented_at', 'direction' => 'DESC']);
check('columnsFor: colonna utente solo per chi vede tutti', fn () =>
    !in_array('user_id', GrantResource::columnsFor($store->users[7]), true)
    && GrantResource::columnsFor($store->users[8])[0] === 'user_id'
    && !in_array('user_id', GrantResource::columnsFor(null), true));
check('redirectHost: dominio del primo redirect, vuoto se assente o rotto', fn () =>
    GrantResource::redirectHost('["https://claude.ai/cb"]') === 'claude.ai'
    && GrantResource::redirectHost(null) === '' && GrantResource::redirectHost('{rotto') === '');
check('canRevoke: proprio sì, altrui no; admin sì', fn () =>
    ConnectionsResource::canRevoke($store->users[7], $store->findGrant($g7))
    && !ConnectionsResource::canRevoke($store->users[7], $store->findGrant($g8))
    && ConnectionsResource::canRevoke($store->users[8], $store->findGrant($g7)));
check('seeAll: admin sì, editor no, null no', fn () =>
    ConnectionsResource::seeAll($store->users[8]) && !ConnectionsResource::seeAll($store->users[7]) && !ConnectionsResource::seeAll(null));

// APP_URL http fuori da locale: servers() degrada a null e il nome risorsa resta l'URL
check('issuer non valido: servers() null, nessuna eccezione', function () {
    \Wonder\OAuth\ResourceRegistry::register('/api/gestionale/mcp', 'Gestionale');
    $_ENV['APP_URL'] = 'http://sito.it';
    $prevLog = ini_set('error_log', '/dev/null');
    try {
        $ok = !\Wonder\App\Environment::isLocal()
            ? (ConnectionsResource::servers() === null && GrantResource::resourceName('https://sito.it/api/gestionale/mcp') === 'https://sito.it/api/gestionale/mcp')
            : true;
    } finally {
        $_ENV['APP_URL'] = 'https://sito.it';
        ini_set('error_log', (string) $prevLog);
        \Wonder\OAuth\ResourceRegistry::clear();
    }
    return $ok;
});
check('con issuer valido il nome risorsa viene risolto', function () {
    \Wonder\OAuth\ResourceRegistry::register('/api/gestionale/mcp', 'Gestionale');
    $name = GrantResource::resourceName('https://sito.it/api/gestionale/mcp');
    \Wonder\OAuth\ResourceRegistry::clear();
    return $name === 'Gestionale';
});

// Revoca: l'id arriva dal client, proprietà e stato si ricontrollano lato server
check('revoke: un utente non revoca il grant altrui', fn () => !ConnectionsResource::revoke($g8, $store->users[7]) && empty($store->findGrant($g8)['revoked_at']));
check('revoke: senza utente o con id inesistente no', fn () => !ConnectionsResource::revoke($g7, null) && !ConnectionsResource::revoke(999, $store->users[8]) && !ConnectionsResource::revoke(0, $store->users[8]));
check('revoke: il proprietario revoca il suo', fn () => ConnectionsResource::revoke($g7, $store->users[7]) && !empty($store->findGrant($g7)['revoked_at']));
check('revoke: già revocato no', fn () => !ConnectionsResource::revoke($g7, $store->users[8]));
check('revoke: admin revoca quello altrui', fn () => ConnectionsResource::revoke($g8, $store->users[8]) && !empty($store->findGrant($g8)['revoked_at']));

summary();
