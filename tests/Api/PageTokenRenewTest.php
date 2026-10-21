<?php
/** php tests/Api/PageTokenRenewTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

// Figlio: esegue l'handler reale `app/http/api/token/page.php` (chiude con exit).
if (($argv[1] ?? '') === '--scenario') {
    [$session, $csrf, $origin] = [$argv[2], $argv[3], $argv[4]];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_HOST'] = 'example.test';
    if ($origin === 'same') {
        $_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
    }
    if ($session === 'yes') {
        session_save_path(sys_get_temp_dir());
        session_start();
        $_SESSION['wonder_auth_csrf'] = 'tok-valido';
    }
    if ($csrf !== 'none') {
        $_SERVER['HTTP_X_WI_CSRF'] = $csrf === 'valid' ? 'tok-valido' : 'tok-errato';
    }
    register_shutdown_function(static function () {
        echo "\n@@".http_response_code();
    });
    require __DIR__.'/../../app/http/api/token/page.php';
}

require __DIR__.'/../harness.php';

$status = static function (string $session, string $csrf, string $origin = 'same'): int {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '
        .escapeshellarg($session).' '.escapeshellarg($csrf).' '.escapeshellarg($origin).' 2>&1';
    $out = (string) shell_exec($cmd);
    $pos = strrpos($out, "\n@@");

    return $pos === false ? -1 : (int) substr($out, $pos + 3);
};

check('rinnovo senza X-WI-CSRF: 403', fn () => $status('yes', 'none') === 403);
check('rinnovo con X-WI-CSRF errato: 403', fn () => $status('yes', 'wrong') === 403);
check('rinnovo senza sessione: 403', fn () => $status('no', 'valid') === 403);
check('rinnovo cross-origin con CSRF valido: 403', fn () => $status('yes', 'valid', 'cross') === 403);
// Senza DB il token non si emette (503): conta che il controllo sia superato.
check('rinnovo same-origin con sessione e CSRF valido: supera i controlli', fn () => !in_array($status('yes', 'valid'), [403, 405, -1], true));

summary();
