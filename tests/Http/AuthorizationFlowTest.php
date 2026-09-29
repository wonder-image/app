<?php
/** php tests/Http/AuthorizationFlowTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Auth\UserAuthorization;
use Wonder\Http\Exceptions\ForbiddenHttpException;
use Wonder\Http\HttpErrorResponse;

$GLOBALS['authorizationTestUser'] = null;

function infoUser($value, $filter = 'id'): object
{
    return $GLOBALS['authorizationTestUser'];
}

require __DIR__.'/../../app/function/user/auth.php';

$user = static fn (array $overrides = []): object => (object) array_merge([
    'exists' => true,
    'deleted' => false,
    'active' => true,
    'area' => ['backend'],
    'authority' => ['editor'],
], $overrides);

$GLOBALS['PAGE'] = (object) ['uriBase64' => 'L2JhY2tlbmQvcmVwb3J0Lw=='];
$GLOBALS['PERMITS'] = [
    'backend' => [
        'links' => ['login' => '/backend/login/'],
        'admin' => ['links' => ['login' => '/backend/login/']],
    ],
];
$_SERVER['REQUEST_METHOD'] = 'GET';

check('pagina backend 403: usa la view errore HTTP condivisa', function () {
    $ERROR = 403;
    $ERROR_MESSAGE = '';
    $ERROR_FILE = '';
    $ERROR_LINE = 0;
    $ERROR_TRACE = '';

    ob_start();
    include __DIR__.'/../../app/view/error/http.php';
    $html = (string) ob_get_clean();
    $status = http_response_code();
    http_response_code(200);

    return $status === 403
        && str_contains($html, '<div class="error-number">403</div>')
        && str_contains($html, '<h1 class="error-title">Accesso negato</h1>');
});

check('utente non autenticato: il gate richiede il Login', function () {
    $decision = UserAuthorization::evaluate(null, 'backend', ['admin']);

    return $decision->requiresLogin()
        && !$decision->isForbidden()
        && $decision->alert() === null;
});

check('POST con sessione scaduta: il redirect conserva alert 917', function () {
    $decision = UserAuthorization::evaluate(null, 'backend', ['admin'], true);

    return $decision->requiresLogin() && $decision->alert() === 917;
});

check('authorizeUser senza sessione termina con redirect HTTP', function () {
    $autoload = var_export(__DIR__.'/../../vendor/autoload.php', true);
    $auth = var_export(__DIR__.'/../../app/function/user/auth.php', true);
    $script = tempnam(sys_get_temp_dir(), 'wi-auth-redirect-');

    if (!is_string($script)) {
        return false;
    }

    $source = <<<PHP
<?php
require {$autoload};
require {$auth};
\$PAGE = (object) ['uriBase64' => 'cmV0dXJu'];
\$PERMITS = ['backend' => ['links' => ['login' => '/backend/login/']]];
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_COOKIE = [];
\$continued = false;
register_shutdown_function(function () use (&\$continued): void {
    echo 'status='.http_response_code().';continued='.(\$continued ? '1' : '0').';';
});
authorizeUser('backend', [], null);
\$continued = true;
PHP;

    file_put_contents($script, $source);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script), $output, $exitCode);
    unlink($script);

    return $exitCode === 0 && implode("\n", $output) === 'status=302;continued=0;';
});

check('utente autenticato con permesso: accesso consentito', function () use ($user) {
    $GLOBALS['authorizationTestUser'] = $user(['authority' => ['editor', 'admin']]);

    return authorizeUser('backend', ['admin'], 10) === $GLOBALS['authorizationTestUser'];
});

check('utente autenticato senza permesso: eccezione HTTP 403 senza alert Login', function () use ($user) {
    $GLOBALS['authorizationTestUser'] = $user(['authority' => ['editor']]);

    try {
        authorizeUser('backend', ['admin'], 10);
    } catch (ForbiddenHttpException $exception) {
        return $exception->statusCode() === 403
            && $exception->publicMessage() === 'Accesso negato.';
    }

    return false;
});

check('problemi account e area restano redirect al Login con gli alert storici', function () use ($user) {
    return UserAuthorization::evaluate($user(['exists' => false]), 'backend', ['admin'])->alert() === 901
        && UserAuthorization::evaluate($user(['deleted' => true]), 'backend', ['admin'])->alert() === 912
        && UserAuthorization::evaluate($user(['active' => false]), 'backend', ['admin'])->alert() === 909
        && UserAuthorization::evaluate($user(['area' => ['frontend']]), 'backend', ['admin'])->alert() === 911;
});

check('diniego API: payload JSON e status restano 403', function () {
    $exception = new ForbiddenHttpException();
    $payload = HttpErrorResponse::jsonPayload($exception->statusCode(), $exception->publicMessage());
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $payload['status'] === 403
        && $payload['success'] === false
        && $json === '{"success":false,"status":403,"response":"Accesso negato."}';
});

check('handler protetto non eseguito dopo il diniego', function () use ($user) {
    $GLOBALS['authorizationTestUser'] = $user(['authority' => ['editor']]);
    $executed = false;

    try {
        authorizeUser('backend', ['admin'], 10);
        $executed = true;
    } catch (ForbiddenHttpException) {
    }

    return $executed === false;
});

check('RouteDispatcher traduce le eccezioni HTTP prima del catch generico', function () {
    $source = file_get_contents(__DIR__.'/../../class/Http/RouteDispatcher.php');
    $httpCatch = strpos((string) $source, 'catch (HttpException $exception)');
    $genericCatch = strpos((string) $source, 'catch (Throwable $throwable)');

    return $httpCatch !== false && $genericCatch !== false && $httpCatch < $genericCatch;
});

summary();
