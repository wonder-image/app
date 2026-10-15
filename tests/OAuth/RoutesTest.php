<?php
/** php tests/OAuth/RoutesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Http\Route;
use Wonder\Http\Router;
use Wonder\App\Environment;
use Wonder\OAuth\OAuth;

$_ENV['APP_URL'] = 'https://sito.it';
$ROOT_APP = realpath(__DIR__ . '/../../app');

// Route::load() fa già Route::reset(); $ROOT_APP arriva al file rotte tramite il context.
$load = static fn (): array => Route::load([$ROOT_APP.'/config/routes/route.oauth.php'], ['ROOT_APP' => $ROOT_APP]);
$match = static fn (array $routes, string $method, string $path): ?array => (new Router($routes))->match($method, $path);

check('senza risorse nessuna rotta OAuth', function () use ($load, $match) {
    OAuth::reset();
    $routes = $load();
    return $routes === []
        && $match($routes, 'GET', '/.well-known/oauth-authorization-server') === null
        && $match($routes, 'POST', '/oauth/token') === null;
});

check('con una risorsa: discovery ed endpoint con e senza slash', function () use ($load, $match) {
    OAuth::reset();
    OAuth::resource('/api/gestionale/mcp', 'Gestionale');
    $routes = $load();
    $ok = true;
    foreach ([
        ['GET', '/.well-known/oauth-authorization-server'],
        ['GET', '/.well-known/oauth-protected-resource'],
        ['GET', '/.well-known/oauth-protected-resource/api/gestionale/mcp'],
        ['POST', '/oauth/token'],
        ['POST', '/oauth/register'],
        ['POST', '/oauth/revoke'],
        ['GET', '/oauth/authorize'],
        ['GET', '/oauth/login'],
        ['POST', '/oauth/login'],
        ['GET', '/oauth/consent'],
        ['POST', '/oauth/consent'],
    ] as [$method, $path]) {
        $ok = $ok && $match($routes, $method, $path) !== null && $match($routes, $method, $path.'/') !== null;
    }
    return $ok;
});

check('PRM per risorsa porta il path nei meta (top level)', function () use ($load, $match) {
    OAuth::reset();
    OAuth::resource('/api/gestionale/mcp', 'Gestionale');
    $routes = $load();
    $route = $match($routes, 'GET', '/.well-known/oauth-protected-resource/api/gestionale/mcp/');
    return ($route['oauth_resource'] ?? null) === '/api/gestionale/mcp';
});

check('endpoint JSON in area api, pagine in area backend', function () use ($load, $match) {
    OAuth::reset();
    OAuth::resource('/api/gestionale/mcp', 'Gestionale');
    $routes = $load();
    return ($match($routes, 'POST', '/oauth/token')['area'] ?? null) === 'api'
        && ($match($routes, 'GET', '/oauth/authorize')['area'] ?? null) === 'backend';
});

check('rotte non traducibili e senza 301 sullo slash finale', function () use ($load, $match) {
    OAuth::reset();
    OAuth::resource('/api/gestionale/mcp', 'Gestionale');
    $routes = $load();
    $r = $match($routes, 'POST', '/oauth/token/');
    return $r !== null && $r['translatable'] === false && empty($r['redirect_to'])
        && empty($match($routes, 'POST', '/oauth/token')['redirect_to']);
});

check('regressione: APP_URL http in produzione non rompe il caricamento delle rotte', function () use ($load) {
    $oldUrl = $_ENV['APP_URL'] ?? null;
    $oldEnv = $_ENV['APP_ENV'] ?? null;
    try {
        $_ENV['APP_URL'] = 'http://sito.it';
        $_ENV['APP_ENV'] = 'production';
        Environment::reset();
        OAuth::reset();
        OAuth::resource('/api/gestionale/mcp', 'Gestionale');
        return count($load()) > 0;
    } finally {
        if ($oldUrl === null) { unset($_ENV['APP_URL']); } else { $_ENV['APP_URL'] = $oldUrl; }
        if ($oldEnv === null) { unset($_ENV['APP_ENV']); } else { $_ENV['APP_ENV'] = $oldEnv; }
        Environment::reset();
    }
});

OAuth::reset();
summary();
