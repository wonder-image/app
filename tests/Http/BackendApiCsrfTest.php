<?php
/** php tests/Http/BackendApiCsrfTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Http\Csrf;
use Wonder\Http\CsrfGuard;
use Wonder\Http\Exceptions\ForbiddenHttpException;
use Wonder\Http\HttpErrorResponse;
use Wonder\Http\Route;
use Wonder\Http\Router;

$_ENV['APP_URL'] = $_ENV['APP_URL'] ?? 'https://sito.it';
$ROOT_APP = realpath(__DIR__.'/../../app');
// Con almeno una risorsa OAuth si registra anche `/api/backend/oauth/revoke/`.
\Wonder\OAuth\ResourceRegistry::register('/mcp', 'Test');
$routes = Route::load([$ROOT_APP.'/config/routes/route.api.php'], ['ROOT_APP' => $ROOT_APP, 'ROOT' => $ROOT_APP]);

// Rotte del gruppo backend autenticate solo con Bearer (`Handler::run`).
$bearerOnly = ['/api/backend/alert/'];

$backendPosts = array_values(array_filter(
    $routes,
    static fn (array $route): bool => str_starts_with((string) $route['path'], '/api/backend/')
        && ($route['method'] ?? '') !== 'GET'
        && !in_array($route['path'], $bearerOnly, true)
));

/** Simula il tratto del dispatcher dopo `bootApplication`: guard, poi handler. */
$dispatch = static function (array $route, array $post = [], array $server = []): array {
    $_POST = $post;
    $_SERVER = array_merge(['REQUEST_METHOD' => (string) $route['method']], $server);
    $written = false;

    try {
        CsrfGuard::enforce($route);
        $written = true; // qui il dispatcher includerebbe l'handler

        return ['status' => 200, 'written' => $written];
    } catch (ForbiddenHttpException $e) {

        return ['status' => $e->statusCode(), 'written' => $written, 'body' => HttpErrorResponse::jsonPayload($e->statusCode(), $e->publicMessage())];
    }
};

$_SESSION = [];
$token = Csrf::token();

$expected = [
    '/api/backend/list/table/', '/api/backend/change/boolean/', '/api/backend/active/',
    '/api/backend/visible/', '/api/backend/delete/', '/api/backend/authority/',
    '/api/backend/move/', '/api/backend/file/delete/', '/api/backend/file/move/',
    '/api/backend/delete-icon/', '/api/backend/oauth/revoke/', '/api/backend/editorjs/file/', '/api/backend/editorjs/image/',
];

check('tutte le rotte attese del gruppo backend sono registrate', function () use ($backendPosts, $expected) {
    $paths = array_column($backendPosts, 'path');

    return array_diff($expected, $paths) === [];
});

check('ogni POST di sessione del gruppo backend dichiara csrf', function () use ($backendPosts) {
    foreach ($backendPosts as $route) {
        if (($route['csrf'] ?? null) !== true) {
            return false;
        }
    }

    return $backendPosts !== [];
});

check('/api/backend/alert/ è solo Bearer e resta fuori dal CSRF', function () use ($routes, $ROOT_APP) {
    $route = (new Router($routes))->match('POST', '/api/backend/alert/');
    $handler = (string) file_get_contents($ROOT_APP.'/http/api/backend/alert.php');

    return $route !== null && ($route['csrf'] ?? null) === false && str_contains($handler, 'Handler::run(');
});

foreach ($backendPosts as $route) {
    $path = $route['path'];

    check("{$path}: senza token 403 JSON e nessuna scrittura", function () use ($dispatch, $route) {
        $result = $dispatch($route);

        return $result['status'] === 403
            && $result['written'] === false
            && ($result['body']['success'] ?? null) === false
            && ($result['body']['status'] ?? null) === 403;
    });

    check("{$path}: token sbagliato 403", function () use ($dispatch, $route) {
        $result = $dispatch($route, ['_csrf' => 'sbagliato'], ['HTTP_X_WI_CSRF' => 'sbagliato']);

        return $result['status'] === 403 && $result['written'] === false;
    });

    check("{$path}: header X-WI-CSRF valido passa", function () use ($dispatch, $route, $token) {
        return $dispatch($route, [], ['HTTP_X_WI_CSRF' => $token])['written'] === true;
    });

    check("{$path}: campo _csrf valido passa", function () use ($dispatch, $route, $token) {
        return $dispatch($route, ['_csrf' => $token])['written'] === true;
    });
}

check('senza token in sessione nessuna richiesta passa', function () use ($dispatch, $backendPosts) {
    $saved = $_SESSION;
    $_SESSION = [];
    $result = $dispatch($backendPosts[0], ['_csrf' => ''], ['HTTP_X_WI_CSRF' => '']);
    $_SESSION = $saved;

    return $result['status'] === 403;
});

check('GET/HEAD/OPTIONS non richiedono token', function () {
    $route = ['csrf' => true];

    return !CsrfGuard::requires($route, 'GET')
        && !CsrfGuard::requires($route, 'HEAD')
        && !CsrfGuard::requires($route, 'OPTIONS')
        && CsrfGuard::requires($route, 'POST')
        && CsrfGuard::requires($route, 'delete');
});

check('rotte Bearer, task, token di pagina e app/update non dichiarano csrf', function () use ($routes) {
    foreach ($routes as $route) {
        $path = (string) $route['path'];
        $outside = str_starts_with($path, '/api/resource/')
            || str_starts_with($path, '/api/task/')
            || str_starts_with($path, '/api/frontend/')
            || in_array($path, ['/api/alert/', '/api/token/page/', '/api/app/update/', '/api/states/'], true);

        if ($outside && ($route['csrf'] ?? false) !== false) {
            return false;
        }
    }

    return true;
});

check('una route senza csrf non viene verificata', function () use ($dispatch) {
    return $dispatch(['method' => 'POST', 'csrf' => false])['written'] === true
        && $dispatch(['method' => 'POST'])['written'] === true;
});

check('RouteDefinition::csrf() sovrascrive il gruppo', function () {
    Route::reset();
    Route::prefix('/x')->csrf()->group(function () {
        Route::post('/a/', __FILE__);
        Route::post('/b/', __FILE__)->csrf(false);
    });
    $all = Route::all();

    return ($all[0]['csrf'] ?? null) === true && ($all[1]['csrf'] ?? null) === false;
});

check('il dispatcher applica il guard dopo il boot e prima dell\'handler', function () {
    $source = (string) file_get_contents(__DIR__.'/../../class/Http/RouteDispatcher.php');
    $boot = strpos($source, '$this->bootApplication($route);');
    $guard = strpos($source, 'CsrfGuard::enforce($route);');
    $include = strpos($source, "include (string) \$route['handler'];");

    return $boot !== false && $guard !== false && $include !== false && $boot < $guard && $guard < $include;
});

check('CORS ammette l\'header X-WI-CSRF', function () {
    $source = (string) file_get_contents(__DIR__.'/../../class/Http/RouteDispatcher.php');

    return preg_match("/Access-Control-Allow-Headers: [^']*X-WI-CSRF/", $source) === 1;
});

summary();
