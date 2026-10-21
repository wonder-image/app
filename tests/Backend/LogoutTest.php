<?php
/** php tests/Backend/LogoutTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

// Figlio: esegue l'handler reale con View e logoutUser finti (l'handler chiude con exit).
if (($argv[1] ?? '') === '--scenario') {
    eval(<<<'STUBS'
        namespace Wonder\View {
            final class View {
                public static function make(string $file, array $data = []): self { $v = new self; $v->file = $file; $v->data = $data; return $v; }
                public string $file = '';
                public array $data = [];
                public function render(): void {
                    $GLOBALS['R']['rendered'] = basename($this->file);
                    $GLOBALS['R']['alert'] = $this->data['ALERT'] ?? null;
                    extract($this->data);
                    require $this->file;
                }
                public static function layout(string $name): void { ob_start(); }
                public static function end(): void { $GLOBALS['R']['html'] = (string) ob_get_clean(); }
            }
        }
        namespace {
            function logoutUser(string $area): void { $GLOBALS['R']['logout'][] = $area; }
            function __r(string $name, array $params = []): string { return '/'.$name; }
            function __t(string $key, array $params = []): string { return $key; }
            function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
        }
    STUBS);
    [$method, $token] = [$argv[2], $argv[3]];
    $GLOBALS['R'] = ['logout' => [], 'rendered' => null, 'alert' => null, 'html' => ''];
    $_SESSION = ['wonder_auth_csrf' => 'tok-valido'];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_POST = match ($token) {
        'valid' => ['_csrf' => 'tok-valido'],
        'wrong' => ['_csrf' => 'tok-errato'],
        default => [],
    };
    $_GET = $token === 'query' ? ['_csrf' => 'tok-valido'] : [];
    $ROOT_APP = __DIR__.'/../../app';
    register_shutdown_function(static function () {
        echo "\n@@".json_encode($GLOBALS['R']);
    });
    require $ROOT_APP.'/http/backend/account/logout.php';
    exit;
}

require __DIR__.'/../harness.php';

$run = static function (string $method, string $token): array {
    $out = (string) shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '.escapeshellarg($method).' '.escapeshellarg($token).' 2>&1');
    $pos = strrpos($out, "\n@@");
    $json = $pos === false ? null : json_decode(substr($out, $pos + 3), true);

    return is_array($json) ? $json : ['raw' => $out];
};

$csrfAlert = \Wonder\Backend\Support\ResourcePageController::CSRF_ALERT;

check('GET: nessun logout, pagina di conferma con form POST e _csrf', function () use ($run) {
    $r = $run('GET', 'none');
    $html = (string) ($r['html'] ?? '');

    return ($r['logout'] ?? null) === [] && ($r['rendered'] ?? null) === 'logout.php'
        && preg_match('/<form method="post" action="\/backend\.account\.logout">/', $html) === 1
        && str_contains($html, 'name="_csrf"') && str_contains($html, 'href="/backend.home"');
});
check('GET con _csrf nella query: nessun logout', fn () => ($run('GET', 'query')['logout'] ?? null) === []);
check('POST senza token: nessun logout, alert CSRF', fn () => ($r = $run('POST', 'none'))['logout'] === [] && $r['alert'] == $csrfAlert);
check('POST con token errato: nessun logout, alert CSRF', fn () => ($r = $run('POST', 'wrong'))['logout'] === [] && $r['alert'] == $csrfAlert);
check('POST con _csrf solo nella query: nessun logout', fn () => ($run('POST', 'query')['logout'] ?? null) === []);
check('POST con token valido: logout backend, nessuna pagina', fn () => ($r = $run('POST', 'valid'))['logout'] === ['backend'] && $r['rendered'] === null);

// Rotte: GET e POST sullo stesso handler, senza guard (come login).
// Le Resource registrate dalle rotte backend chiedono __t per i titoli.
if (!function_exists('__t')) {
    function __t(string $key, array $params = []): string { return $key; }
}
$_ENV['APP_URL'] = $_ENV['APP_URL'] ?? 'https://sito.it';
$appDir = realpath(__DIR__.'/../../app');
$routes = \Wonder\Http\Route::load([$appDir.'/config/routes/route.backend.php'], ['ROOT_APP' => $appDir, 'ROOT' => $appDir]);
foreach (['GET', 'POST'] as $verb) {
    check("rotta {$verb} /backend/account/logout/ → handler logout", function () use ($routes, $verb) {
        $route = (new \Wonder\Http\Router($routes))->match($verb, '/backend/account/logout/');

        return $route !== null && str_ends_with((string) ($route['handler'] ?? ''), '/http/backend/account/logout.php');
    });
}

// Menu «Esci»: l'elemento con 'method' => 'post' diventa un form con CSRF.
check('sidebarOffcanvas: voce POST resa come form con _csrf, le altre restano link', function () {
    $code = '
        require '.var_export(__DIR__.'/../../vendor/autoload.php', true).';
        function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, "UTF-8"); }
        function code($n, $t, $p) { return $p."1"; }
        session_start();
        require '.var_export(__DIR__.'/../../app/function/backend/plugin/header.php', true).';
        echo sidebarOffcanvas("s", "Impostazioni", [
            ["title" => "Profilo", "link" => "/backend/profilo/"],
            ["title" => "Esci", "link" => "/backend/account/logout/", "method" => "post"],
        ]);';
    $html = (string) shell_exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code).' 2>&1');

    return preg_match("~<form method='post' action='/backend/account/logout/'[^>]*><input type=\"hidden\" name=\"_csrf\"~", $html) === 1
        && str_contains($html, "<a href='/backend/profilo/'")
        && !str_contains($html, "<a href='/backend/account/logout/'");
});

// Testo del sorgente: il layout core passa «Esci» come voce POST.
check('header backend: «Esci» con method post', fn () => preg_match(
    "~'link' => \\\$PATH->backend\\.'/account/logout/',\\s*'method' => 'post',~",
    (string) file_get_contents(__DIR__.'/../../app/view/components/backend/layout/header.php')
) === 1);

summary();
