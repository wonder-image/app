<?php
/** php tests/Http/RouteGuardTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Http\Route;
use Wonder\Http\Router;

$_ENV['APP_URL'] = $_ENV['APP_URL'] ?? 'https://sito.it';
$ROOT_APP = realpath(__DIR__.'/../../app');
$routes = Route::load([$ROOT_APP.'/config/routes/route.api.php'], ['ROOT_APP' => $ROOT_APP, 'ROOT' => $ROOT_APP]);
$match = static fn (string $path): ?array => (new Router($routes))->match('POST', $path);

// Endpoint backend che devono richiedere un utente backend valido.
$guarded = [
    '/api/backend/list/table/',
];

foreach ($guarded as $path) {
    check("{$path}: rotta protetta, area backend, permit vuoto", function () use ($match, $path) {
        $route = $match($path);

        return $route !== null
            && ($route['private'] ?? false) === true
            && ($route['backend'] ?? false) === true
            && ($route['permit'] ?? null) === [];
    });
}

foreach ($guarded as $path) {
    check("{$path}: senza sessione redirect 302 e handler non eseguito", function () use ($match, $path) {
        $route = $match($path);

        if ($route === null) {
            return false;
        }

        $autoload = var_export(__DIR__.'/../../vendor/autoload.php', true);
        $auth = var_export(__DIR__.'/../../app/function/user/auth.php', true);
        $service = var_export(__DIR__.'/../../app/service/auth.php', true);
        $private = var_export((bool) $route['private'], true);
        $backend = var_export((bool) $route['backend'], true);
        $permit = var_export($route['permit'], true);
        $script = tempnam(sys_get_temp_dir(), 'wi-route-guard-');

        if (!is_string($script)) {
            return false;
        }

        $source = <<<PHP
<?php
require {$autoload};
require {$auth};
// Il POST con sessione scaduta scrive auth_log: nessun DB nel test.
function sqlInsert(string \$table, array \$values): object { return (object) ['success' => true]; }
\$PAGE = (object) ['uriBase64' => 'cmV0dXJu'];
\$PERMITS = ['backend' => ['links' => ['login' => '/backend/login/']]];
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_COOKIE = [];
\$_SESSION = [];
\$PRIVATE = {$private};
\$BACKEND = {$backend};
\$PERMIT = {$permit};
\$handled = false;
register_shutdown_function(function () use (&\$handled): void {
    echo 'status='.http_response_code().';handled='.(\$handled ? '1' : '0').';';
});
require {$service};
\$handled = true;
PHP;

        file_put_contents($script, $source);
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output, $exitCode);
        unlink($script);

        return $exitCode === 0 && implode("\n", $output) === 'status=302;handled=0;';
    });
}

summary();
