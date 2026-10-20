<?php
/** php tests/Backend/AccountHandlersTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

// Handler account del backend (C-PWD-I4): ogni scenario gira in un processo
// figlio perché gli handler chiudono con exit().
if (($argv[1] ?? '') !== '--scenario') {
require __DIR__.'/../harness.php';

$run = static function (string $scenario, string $token, string $extra = ''): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '.escapeshellarg($scenario).' '.escapeshellarg($token).' '.escapeshellarg($extra).' 2>&1';
    $out = (string) shell_exec($cmd);
    $pos = strrpos($out, "\n@@");
    $json = $pos === false ? null : json_decode(substr($out, $pos + 3), true);

    return is_array($json) ? $json : ['raw' => $out];
};

$csrfAlert = \Wonder\Backend\Support\ResourcePageController::CSRF_ALERT;
$events = static fn (array $r): array => array_column($r['logs'] ?? [], 'event');

// CSRF: senza token o con token errato → alert 929, pagina resa, nessun effetto.
foreach (['login' => 'login', 'recovery' => 'recupero', 'restore' => 'restore', 'set' => 'imposta password'] as $scenario => $label) {
    foreach (['none', 'wrong'] as $token) {
        check("{$label}: token {$token} → alert {$csrfAlert}, nessuna mail né scrittura", function () use ($run, $scenario, $token, $csrfAlert) {
            $r = $run($scenario, $token);

            return ($r['alert'] ?? null) == $csrfAlert && ($r['writes'] ?? null) === [] && ($r['mails'] ?? null) === []
                && !empty($r['rendered']) && ($r['exited'] ?? null) === false;
        });
    }
}

check('login: token valido → passa ad authenticateUserLogin', function () use ($run, $csrfAlert) {
    $r = $run('login', 'valid');

    return ($r['alert'] ?? null) != $csrfAlert && ($r['writes'] ?? []) === ['authenticateUserLogin'];
});

check('login: token nell\'header X-WI-CSRF → passa ad authenticateUserLogin', fn () => ($run('login', 'header')['writes'] ?? []) === ['authenticateUserLogin']);

// Recupero: risposta 601 uniforme.
$unknown = $run('recovery', 'valid', 'unknown');
// In CLI headers_list() è vuoto: si confrontano stato, rotta del redirect e exit.
$redirect = static fn (array $r): array => [$r['status'] ?? null, $r['routes'] ?? null, $r['exited'] ?? null, $r['rendered'] ?? null];
check('recupero, utente inesistente → 302 verso il login e exit, pagina non resa', fn () =>
    $redirect($unknown) === [302, ['backend.account.login'], true, false]
);
check('recupero, utente inesistente → nessuna mail', fn () => ($unknown['mails'] ?? null) === []);
check('recupero, utente inesistente → log recovery_unknown con identifier normalizzato', function () use ($unknown, $events) {
    $meta = json_decode((string) ($unknown['logs'][0]['meta'] ?? ''), true);

    return $events($unknown) === ['recovery_unknown'] && ($meta['identifier'] ?? null) === 'nessuno@example.com'
        && !array_key_exists('username', (array) $meta);
});

$known = $run('recovery', 'valid', 'known');
check('recupero, utente esistente → stessa risposta dell\'utente inesistente', fn () =>
    $redirect($known) === [302, ['backend.account.password.restore', 'backend.account.login'], true, false]
);
check('recupero, utente esistente → mail con mittente $SOCIETY->email', fn () =>
    count($known['mails'] ?? []) === 1 && ($known['mails'][0]['from'] ?? null) === 'info@negozio.test'
    && ($known['mails'][0]['to'] ?? null) === 'mario@example.com'
);
check('recupero, utente esistente → log password_recovery riuscito', fn () => $events($known) === ['password_recovery'] && ($known['logs'][0]['success'] ?? null) === 1);

$noSociety = $run('recovery', 'valid', 'known-nosociety');
check('recupero senza $SOCIETY->email → ripiego su noreply@wonderimage.it', fn () => ($noSociety['mails'][0]['from'] ?? null) === 'noreply@wonderimage.it');

$mailFailed = $run('recovery', 'valid', 'known-mailfail');
check('recupero, mail non partita → stessa risposta e log mail_failed', function () use ($mailFailed, $known, $redirect, $events) {
    $meta = json_decode((string) ($mailFailed['logs'][0]['meta'] ?? ''), true);

    return $redirect($mailFailed) === $redirect($known) && $events($mailFailed) === ['password_recovery']
        && ($mailFailed['logs'][0]['success'] ?? null) === 0 && ($meta['reason'] ?? null) === 'mail_failed';
});

// Errore nell'emissione del token: stessa risposta dell'utente inesistente (C-PWD-I4).
$issueFailed = $run('recovery', 'valid', 'known-issuefail');
check('recupero, emissione token fallita → stessa risposta dell\'utente inesistente', fn () =>
    $redirect($issueFailed) === $redirect($unknown)
);
check('recupero, emissione token fallita → nessuna mail e log token_failed', function () use ($issueFailed, $events) {
    $meta = json_decode((string) ($issueFailed['logs'][0]['meta'] ?? ''), true);

    return ($issueFailed['mails'] ?? null) === [] && $events($issueFailed) === ['password_recovery']
        && ($issueFailed['logs'][0]['success'] ?? null) === 0 && ($meta['reason'] ?? null) === 'token_failed'
        && ($meta['error'] ?? null) === 'RuntimeException';
});

$throttled = $run('recovery', 'valid', 'throttled');
check('recupero oltre il limite → alert 930, nessuna mail, log recovery_throttled', fn () =>
    ($throttled['alert'] ?? null) == \Wonder\Auth\LoginThrottle::ALERT && ($throttled['mails'] ?? null) === []
    && $events($throttled) === ['recovery_throttled'] && !empty($throttled['rendered'])
);
check('recupero: CSRF controllato prima del throttle (nessun conteggio senza token)', fn () => ($run('recovery', 'none', 'throttled')['counts'] ?? null) === 0);

// Restore e set: con token valido si arriva alla scrittura (qui la transazione senza DB).
check('restore: token valido → arriva a PasswordReset::reset', fn () => ($run('restore', 'valid')['writes'] ?? []) === ['reset']);
check('imposta password: token valido → arriva alla transazione', fn () => ($run('set', 'valid')['writes'] ?? []) === ['transaction']);

// Le viste dei quattro form emettono un solo `_csrf`.
$root = dirname(__DIR__, 2);
foreach (['login', 'password-recovery', 'password-restore', 'password-set'] as $view) {
    check("vista {$view}: un form, un Csrf::field()", function () use ($root, $view) {
        $src = (string) file_get_contents($root.'/app/view/pages/backend/account/'.$view.'.php');

        return substr_count($src, '<form') === 1 && substr_count($src, 'Csrf::field()->render()') === 1;
    });
}

summary();
}

// --- Processo figlio ---------------------------------------------------------

function accountScenario(string $scenario, string $tokenMode, string $extra): void
{
    $GLOBALS['W'] = [];
    $GLOBALS['MAILS'] = [];
    $GLOBALS['LOGS'] = [];
    $GLOBALS['COUNTS'] = 0;
    $GLOBALS['RENDERED'] = false;
    $GLOBALS['ALERT'] = null;
    $GLOBALS['EXITED'] = true;
    $GLOBALS['MODE'] = $extra;

    $_SESSION = [];
    $token = \Wonder\Http\Csrf::token();
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '203.0.113.9'];
    $csrf = match ($tokenMode) {
        'valid' => ['_csrf' => $token],
        'wrong' => ['_csrf' => str_repeat('0', 64)],
        default => [],
    };

    if ($tokenMode === 'header') {
        $_SERVER['HTTP_X_WI_CSRF'] = $token;
    }

    $GLOBALS['SOCIETY'] = $extra === 'known-nosociety' ? (object) [] : (object) ['email' => 'info@negozio.test'];

    $appDir = sys_get_temp_dir().'/wi-account-handlers-'.getmypid();

    foreach (['login', 'password-recovery', 'password-restore', 'password-set'] as $view) {
        @mkdir($appDir.'/view/pages/backend/account', 0777, true);
        file_put_contents($appDir.'/view/pages/backend/account/'.$view.'.php', '<?php $GLOBALS["RENDERED"] = true;');
    }

    register_shutdown_function(static function () use ($appDir): void {
        echo "\n@@".json_encode([
            'alert' => $GLOBALS['ALERT'] ?? null,
            'writes' => $GLOBALS['W'],
            'mails' => $GLOBALS['MAILS'],
            'logs' => $GLOBALS['LOGS'],
            'counts' => $GLOBALS['COUNTS'],
            'rendered' => $GLOBALS['RENDERED'],
            'exited' => $GLOBALS['EXITED'],
            'status' => http_response_code(),
            'routes' => $GLOBALS['ROUTES'] ?? [],
        ]);
        exec('rm -rf '.escapeshellarg($appDir));
    });

    accountStubs();

    // Token monouso valido per restore/set (letto da sqlSelect).
    $selector = str_repeat('a', 24);
    $validator = str_repeat('b', 64);
    $GLOBALS['OTT'] = ['id' => 3, 'subject_user_id' => 7, 'validator_hash' => hash('sha256', $validator), 'expires_at' => date('Y-m-d H:i:s', time() + 600)];
    $_GET = ['token' => $selector.'.'.$validator];

    $vars = ['ROOT_APP' => $appDir, 'ROOT' => $appDir, 'PAGE' => (object) ['redirect' => '']];

    try {
        switch ($scenario) {
            case 'login':
                $_POST = $csrf + ['login' => '1', 'username' => 'mario', 'password' => 'x'];
                accountInclude('login', $vars);
                break;
            case 'recovery':
                $username = str_starts_with($extra, 'known') ? 'Mario' : ' Nessuno@Example.com ';
                $_POST = $csrf + ['recovery' => '1', 'username' => $username];
                accountInclude('password-recovery', $vars);
                break;
            case 'restore':
                $_POST = $csrf + ['restore' => '1', 'password' => 'nuova-password'];
                accountInclude('password-restore', $vars);
                break;
            case 'set':
                $_POST = $csrf + ['set-password' => '1', 'password' => 'nuova-password'];
                accountInclude('password-set', $vars);
                break;
        }
    } catch (Throwable $e) {
        // Con token valido restore/set aprono la transazione: senza DB si ferma qui.
        $GLOBALS['W'][] = $scenario === 'restore' ? 'reset' : 'transaction';
    }

    $GLOBALS['EXITED'] = false;
}

function accountInclude(string $handler, array $vars): void
{
    extract($vars);
    include __DIR__.'/../../app/http/backend/account/'.$handler.'.php';
}

function accountStubs(): void
{
    \Wonder\Auth\LoginThrottle::useCounter(static function (string $where): int {
        $GLOBALS['COUNTS']++;

        return $GLOBALS['MODE'] === 'throttled' ? 1000 : 0;
    });

    eval(<<<'STUBS'
        namespace {
            function __r(string $name, array $params = []): string { $GLOBALS['ROUTES'][] = $name; return '/'.$name; }
            function sqlBind($sql, $params) { foreach ((array) $params as $p) { $sql = preg_replace('/\?/', "'".addslashes((string) $p)."'", $sql, 1); } return $sql; }
            function __t(string $key, array $params = []): string { return $key; }
            function authenticateUserLogin($u, $p, $area) { $GLOBALS['W'][] = 'authenticateUserLogin'; return false; }
            function verifyUser($key, $value, $area = null, $permit = null) {
                $known = $key === 'id' || str_starts_with((string) $GLOBALS['MODE'], 'known');
                if (!$known) { $GLOBALS['ALERT'] = 913; return (object) ['response' => false]; }
                return (object) ['response' => true, 'user' => (object) ['id' => 7, 'email' => 'mario@example.com', 'password' => '']];
            }
            function sendMail($from, $to, $subject, $content) { $GLOBALS['MAILS'][] = ['from' => $from, 'to' => $to]; return $GLOBALS['MODE'] !== 'known-mailfail'; }
            function sqlSelect($table, $cond = null, $limit = null) {
                if ($table === 'auth_one_time_tokens' && $limit === 1) { return (object) ['exists' => true, 'row' => $GLOBALS['OTT']]; }
                return (object) ['exists' => false, 'row' => []];
            }
            function sqlInsert($table, $values) {
                if ($table === 'auth_log') { $GLOBALS['LOGS'][] = $values; return (object) ['success' => true]; }
                if ($table === 'auth_one_time_tokens') {
                    if ($GLOBALS['MODE'] === 'known-issuefail') { throw new RuntimeException('db down'); }
                    return (object) ['success' => true, 'insert_id' => 1];
                }
                $GLOBALS['W'][] = "insert {$table}"; return (object) ['success' => false];
            }
            function sqlModify($table, $values, $col = null, $id = null) { $GLOBALS['W'][] = "modify {$table}"; return (object) ['success' => false]; }
            function hashPassword($v) { return 'h'; }
        }
    STUBS);
}

if (($argv[1] ?? '') === '--scenario') {
    accountScenario((string) $argv[2], (string) $argv[3], (string) ($argv[4] ?? ''));
}
