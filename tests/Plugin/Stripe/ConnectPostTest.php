<?php
/** php tests/Plugin/Stripe/ConnectPostTest.php */
declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';

// Figlio: esegue l'handler reale `app/http/api/service/stripe/connect.php` (chiude con exit), con View finta.
if (($argv[1] ?? '') === '--scenario') {
    eval(<<<'STUBS'
        namespace Wonder\View {
            final class View {
                public string $file = '';
                public array $data = [];
                public static function make(string $file, array $data = []): self { $v = new self; $v->file = $file; $v->data = $data; return $v; }
                public function render(): void { $GLOBALS['R']['layout'] = null; extract($this->data); require $this->file; }
                public static function layout(string $name): void { $GLOBALS['R']['layout'] = $name; }
                public static function end(): void {}
            }
        }
        namespace {
            function __t(string $key, array $params = []): string { return $key; }
            function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
        }
    STUBS);
    [$method, $where] = [$argv[2], $argv[3]];
    $GLOBALS['R'] = ['layout' => false];
    $ROOT_APP = realpath(__DIR__.'/../../../app');
    $PATH = (object) ['backend' => '/backend'];
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
        echo "\n@@".http_response_code().'|'.json_encode($GLOBALS['R']['layout']);
    });
    require __DIR__.'/../../../app/http/api/service/stripe/connect.php';
}

require __DIR__.'/../../harness.php';

$run = static function (string $method, string $where): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '.escapeshellarg($method).' '.escapeshellarg($where).' 2>&1';
    $out = (string) shell_exec($cmd);
    $pos = strrpos($out, "\n@@");

    [$status, $layout] = $pos === false ? [-1, 'false'] : explode('|', substr($out, $pos + 3), 2) + [1 => 'false'];

    return ['status' => (int) $status, 'layout' => json_decode($layout, true), 'body' => $pos === false ? $out : substr($out, 0, $pos)];
};

// Senza rotte caricate il webhook manca: vederne il messaggio vuol dire controlli superati.
$passed = static fn (array $r): bool => str_contains($r['body'], 'webhook_missing');

check('GET (anche con _csrf nella query): nessuna azione, solo conferma in POST', function () use ($run, $passed) {
    $r = $run('GET', 'query');

    // In CLI senza codice impostato http_response_code() vale false (0).
    return in_array($r['status'], [0, 200], true) && !$passed($r) && str_contains($r['body'], 'method="post"')
        && str_contains($r['body'], 'name="_csrf"') && !str_contains($r['body'], '_csrf=');
});
check('GET: pagina con layout backend.auth, action senza query, link alle credenziali', function () use ($run) {
    $r = $run('GET', 'none');

    return $r['layout'] === 'backend.auth' && str_contains($r['body'], 'action="/api/service/stripe/connect/"')
        && str_contains($r['body'], 'name="account" value="test"') && str_contains($r['body'], 'href="/backend/app/config/credentials/"');
});
check('HEAD: nessun corpo, nessuna azione', fn () => in_array(($r = $run('HEAD', 'none'))['status'], [0, 200], true)
    && trim($r['body']) === '' && $r['layout'] === false);
check('POST senza token: messaggio nel layout backend.auth', fn () => ($r = $run('POST', 'none'))['layout'] === 'backend.auth'
    && str_contains($r['body'], 'stripe.connect.invalid'));
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
    check("rotta {$verb} /api/service/stripe/connect/ → handler connect, area backend, solo admin", function () use ($routes, $verb) {
        $route = (new \Wonder\Http\Router($routes))->match($verb, '/api/service/stripe/connect/');

        return $route !== null && ($route['area'] ?? null) === 'backend' && ($route['private'] ?? false) === true
            && ($route['permit'] ?? null) === ['admin']
            && str_ends_with((string) ($route['handler'] ?? ''), '/http/api/service/stripe/connect.php');
    });
}
// matchByPath decide gli header CORS/JSON di RouteDispatcher: deve vedere l'area backend.
check('matchByPath /api/service/stripe/connect/: area backend (niente CORS api)', fn () =>
    ((new \Wonder\Http\Router($routes))->matchByPath('/api/service/stripe/connect/?account=test')['area'] ?? null) === 'backend');
check('nome rotta service.stripe.connect invariato', fn () =>
    in_array('service.stripe.connect', array_column($routes, 'name'), true));
check('POST con nome service.stripe.connect.submit, nessun nome duplicato', function () use ($routes) {
    $route = (new \Wonder\Http\Router($routes))->match('POST', '/api/service/stripe/connect/');
    $names = array_filter(array_column($routes, 'name'), static fn ($n) => str_starts_with((string) $n, 'service.stripe.'));

    return ($route['name'] ?? null) === 'service.stripe.connect.submit'
        && $names === array_filter($names, static fn ($n) => substr_count((string) $n, 'stripe.') === 1);
});
// Il router non mappa HEAD e PUT: il dispatcher risponde 404 prima dell'handler.
check('router: HEAD e PUT senza rotta (404 dal dispatcher)', fn () =>
    (new \Wonder\Http\Router($routes))->match('HEAD', '/api/service/stripe/connect/') === null
    && (new \Wonder\Http\Router($routes))->match('PUT', '/api/service/stripe/connect/') === null);

// Testo del sorgente: il bottone della Resource non deve più portare il token nell'URL.
check('SecurityResource: nessun _csrf= negli URL', fn () =>
    !str_contains((string) file_get_contents(__DIR__.'/../../../class/App/Resources/Config/SecurityResource.php'), '_csrf='));

summary();
