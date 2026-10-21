<?php
/** php tests/Plugin/Stripe/ConnectPostTest.php */
declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';

// Figlio: esegue l'handler reale `app/http/api/service/stripe/connect.php` (chiude con exit).
if (($argv[1] ?? '') === '--scenario') {
    [$method, $where] = [$argv[2], $argv[3]];
    function __t(string $key, array $params = []): string { return $key; }
    function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
    $_SESSION = ['wonder_auth_csrf' => 'tok-valido'];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = '/api/service/stripe/connect/?account=test';
    $_GET = ['account' => 'test'];
    $_POST = $method === 'POST' ? ['account' => 'test'] : [];
    $token = $where === 'wrong' ? 'tok-errato' : 'tok-valido';
    match ($where) {
        'body', 'wrong' => $_POST['_csrf'] = $token,
        'query' => $_GET['_csrf'] = $token,
        'header' => $_SERVER['HTTP_X_WI_CSRF'] = $token,
        default => null,
    };
    $_REQUEST = $_GET + $_POST;
    register_shutdown_function(static function () {
        echo "\n@@".http_response_code();
    });
    require __DIR__.'/../../../app/http/api/service/stripe/connect.php';
}

require __DIR__.'/../../harness.php';

$run = static function (string $method, string $where): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '.escapeshellarg($method).' '.escapeshellarg($where).' 2>&1';
    $out = (string) shell_exec($cmd);
    $pos = strrpos($out, "\n@@");

    return ['status' => $pos === false ? -1 : (int) substr($out, $pos + 3), 'body' => $pos === false ? $out : substr($out, 0, $pos)];
};

// Senza rotte caricate il webhook manca: vederne il messaggio vuol dire controlli superati.
$passed = static fn (array $r): bool => str_contains($r['body'], 'webhook_missing');

check('GET (anche con _csrf nella query): nessuna azione, solo conferma in POST', function () use ($run, $passed) {
    $r = $run('GET', 'query');

    // In CLI senza codice impostato http_response_code() vale false (0).
    return in_array($r['status'], [0, 200], true) && !$passed($r) && str_contains($r['body'], 'method="post"')
        && str_contains($r['body'], 'name="_csrf"') && !str_contains($r['body'], '_csrf=');
});
check('POST senza token: 403', fn () => ($r = $run('POST', 'none'))['status'] === 403 && !$passed($r));
check('POST con token errato: 403', fn () => ($r = $run('POST', 'wrong'))['status'] === 403 && !$passed($r));
check('POST con _csrf solo nella query: 403', fn () => ($r = $run('POST', 'query'))['status'] === 403 && !$passed($r));
check('POST con _csrf nel body: controlli superati', fn () => $passed($run('POST', 'body')));
check('POST con X-WI-CSRF: controlli superati', fn () => $passed($run('POST', 'header')));
check('PUT: 405', fn () => $run('PUT', 'body')['status'] === 405);

$_ENV['APP_URL'] = $_ENV['APP_URL'] ?? 'https://sito.it';
$appDir = realpath(__DIR__.'/../../../app');
$routes = \Wonder\Http\Route::load([$appDir.'/config/routes/route.api.php'], ['ROOT_APP' => $appDir, 'ROOT' => $appDir]);
foreach (['GET', 'POST'] as $verb) {
    check("rotta {$verb} /api/service/stripe/connect/ → handler connect, area backend", function () use ($routes, $verb) {
        $route = (new \Wonder\Http\Router($routes))->match($verb, '/api/service/stripe/connect/');

        return $route !== null && ($route['backend'] ?? false) === true
            && str_ends_with((string) ($route['handler'] ?? ''), '/http/api/service/stripe/connect.php');
    });
}

// Testo del sorgente: il bottone della Resource non deve più portare il token nell'URL.
check('SecurityResource: nessun _csrf= negli URL', fn () =>
    !str_contains((string) file_get_contents(__DIR__.'/../../../class/App/Resources/Config/SecurityResource.php'), '_csrf='));

summary();
