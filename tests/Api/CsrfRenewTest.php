<?php
/** php tests/Api/CsrfRenewTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

// Figlio: esegue l'handler reale `app/http/api/csrf.php` (chiude con exit).
if (($argv[1] ?? '') === '--scenario') {
    [$session, $origin, $method] = [$argv[2], $argv[3], $argv[4]];
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['HTTP_HOST'] = 'example.test';
    if ($origin === 'same') {
        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
    } elseif ($origin === 'cross') {
        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.test';
    }
    if ($session === 'yes') {
        session_save_path(sys_get_temp_dir());
        session_start();
        $_SESSION['wonder_auth_csrf'] = str_repeat('a', 64);
    }
    register_shutdown_function(static function () {
        echo "\n@@".http_response_code();
    });
    require __DIR__.'/../../app/http/api/csrf.php';
}

require __DIR__.'/../harness.php';

$run = static function (string $session, string $origin = 'same', string $method = 'POST'): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '
        .escapeshellarg($session).' '.escapeshellarg($origin).' '.escapeshellarg($method).' 2>&1';
    $out = (string) shell_exec($cmd);
    $pos = strrpos($out, "\n@@");

    return $pos === false ? [-1, $out] : [(int) substr($out, $pos + 3), substr($out, 0, $pos)];
};

check('GET: 405', fn () => $run('yes', 'same', 'GET')[0] === 405);
check('cross-origin: 403', fn () => $run('yes', 'cross')[0] === 403);
check('senza Origin né Sec-Fetch-Site: 403', fn () => $run('yes', 'none')[0] === 403);
check('senza sessione: 403', fn () => $run('no')[0] === 403);
check('same-origin con sessione: 200 e token della sessione', function () use ($run) {
    [$code, $body] = $run('yes');
    $json = json_decode($body, true);

    return $code === 200 && ($json['token'] ?? null) === str_repeat('a', 64);
});

summary();
