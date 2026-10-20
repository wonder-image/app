<?php
/** php tests/Auth/AuthControllerTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

// AuthController frontend: enumerazione (C-PWD-I5) e remember-me (C-PWD-I7).
// Ogni scenario gira in un processo figlio perché il controller chiude con exit().
if (($argv[1] ?? '') !== '--scenario') {
require __DIR__.'/../harness.php';

$run = static function (string $scenario, array $opts = []): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '.escapeshellarg($scenario).' '.escapeshellarg(json_encode($opts)).' 2>&1';
    $out = (string) shell_exec($cmd);
    $pos = strrpos($out, "\n@@");
    $json = $pos === false ? null : json_decode(substr($out, $pos + 3), true);

    return is_array($json) ? $json : ['raw' => $out];
};
$events = static fn (array $r): array => array_column($r['logs'] ?? [], 'event');
$subjects = static fn (array $r): array => array_column($r['mails'] ?? [], 'subject');

// --- I5: login ------------------------------------------------------------
$federated = $run('login', ['user' => 'federated']);
$unknown = $run('login', ['user' => 'none']);
$wrong = $run('login', ['user' => 'local']);

check('login, account solo Google → alert 925 come le credenziali errate', fn () => ($federated['render']['alert'] ?? null) === 925);
check('login, account solo Google → nessun hint «accedi con Google» in pagina', fn () =>
    array_key_exists('federated_error', (array) ($federated['render'] ?? [])) && $federated['render']['federated_error'] === null && ($federated['render'] ?? null) == ($wrong['render'] ?? [])
);
check('login, account solo Google → mail «accedi con Google» all\'indirizzo', fn () =>
    $subjects($federated) === ['auth.email.federated_subject'] && ($federated['mails'][0]['to'] ?? null) === 'mario@example.com'
    && str_contains((string) ($federated['mails'][0]['body'] ?? ''), 'Google')
);
check('login, account solo Google → tentativo conteggiato (login_failed)', fn () => $events($federated) === ['login_failed']);
check('login, utente inesistente → 925, stessa pagina, nessuna mail', fn () =>
    ($unknown['render'] ?? null) == ($wrong['render'] ?? []) && ($unknown['render']['alert'] ?? null) === 925 && ($unknown['mails'] ?? null) === []
);
check('login, password errata → 925 e nessuna mail', fn () => ($wrong['render']['alert'] ?? null) === 925 && ($wrong['mails'] ?? null) === []);
check('login, registrazione senza password (924) → 925, nessuna mail', function () use ($run, $wrong) {
    $r = $run('login', ['user' => 'partial']);

    return ($r['render'] ?? null) == ($wrong['render'] ?? []) && ($r['mails'] ?? null) === [];
});
check('login, account solo Google oltre il limite → 930 e nessuna mail', function () use ($run) {
    $r = $run('login', ['user' => 'federated', 'throttled' => true]);

    return ($r['render']['alert'] ?? null) === \Wonder\Auth\LoginThrottle::ALERT && ($r['mails'] ?? null) === [];
});

// --- I5: registrazione ----------------------------------------------------
$fresh = $run('signup', ['signup' => 'new']);
$existing = $run('signup', ['signup' => 'existing', 'user' => 'local']);
$redirect = static fn (array $r): array => [$r['redirect'] ?? null, $r['exited'] ?? null, $r['render'] ?? null];

check('registrazione, email nuova → redirect a email.sent', fn () => $redirect($fresh) === ['/account.auth.email.sent', true, null]);
check('registrazione, email esistente → stessa risposta della email nuova', fn () => $redirect($existing) === $redirect($fresh));
check('registrazione, email esistente → mail «hai già un account» con il link al recupero', fn () =>
    $subjects($existing) === ['auth.email.existing_subject'] && ($existing['mails'][0]['to'] ?? null) === 'mario@example.com'
    && str_contains((string) ($existing['mails'][0]['body'] ?? ''), 'account.auth.password.recovery')
);
check('registrazione, email esistente → log signup_existing', fn () => $events($existing) === ['signup_existing'] && ($existing['logs'][0]['success'] ?? null) === 1);
check('registrazione, email nuova → nessuna mail «hai già un account»', fn () => !in_array('auth.email.existing_subject', $subjects($fresh), true));
check('registrazione, email esistente oltre il limite → stessa risposta, nessuna mail', function () use ($run, $redirect, $fresh, $events) {
    $r = $run('signup', ['signup' => 'existing', 'user' => 'local', 'throttled' => true]);

    return $redirect($r) === $redirect($fresh) && ($r['mails'] ?? null) === [] && $events($r) === ['signup_existing_throttled'];
});
check('registrazione, email di un account fuori area → stessa risposta, nessuna mail', function () use ($run, $redirect, $fresh) {
    $r = $run('signup', ['signup' => 'existing', 'user' => 'none']);

    return $redirect($r) === $redirect($fresh) && ($r['mails'] ?? null) === [];
});
check('registrazione: signup_existing conta nel bucket recovery', fn () =>
    in_array('signup_existing', (new ReflectionClassConstant(\Wonder\Auth\LoginThrottle::class, 'BUCKETS'))->getValue()['recovery']['events'] ?? [], true)
);

// --- I7: remember-me opt-in ------------------------------------------------
$login = static fn (array $post, string $policy = 'optional'): array => $run('login', ['user' => 'local', 'policy' => $policy, 'post' => ['password' => 'Giusta-1'] + $post]);

$plain = $login([]);
check('login riuscito senza checkbox → sessione aperta, nessun remember-me', fn () =>
    ($plain['session_user'] ?? null) === 7 && ($plain['remember'] ?? null) === [] && ($plain['redirect'] ?? null) === '/account/'
);
check('login riuscito con il solo hidden vuoto → nessun remember-me', function () use ($login) {
    $r = $login(['remember' => '']);

    return ($r['session_user'] ?? null) === 7 && ($r['remember'] ?? null) === [];
});
check('login riuscito con checkbox spuntata → un token remember-me per l\'area', function () use ($login) {
    $r = $login(['remember' => 'true']);

    return count($r['remember'] ?? []) === 1 && ($r['remember'][0]['area'] ?? null) === 'frontend' && ($r['remember'][0]['user_id'] ?? null) === 7;
});
check('rememberMe() always → remember-me anche senza checkbox', fn () => count($login([], 'always')['remember'] ?? []) === 1);
check('rememberMe() never → niente remember-me anche con checkbox', function () use ($login) {
    $r = $login(['remember' => 'true'], 'never');

    return ($r['session_user'] ?? null) === 7 && ($r['remember'] ?? null) === [];
});
check('login: checkbox «remember» resa solo con optional', function () use ($run) {
    $fields = static fn (string $policy): array => (array) ($run('login', ['user' => 'none', 'policy' => $policy])['render']['fields'] ?? []);

    return $fields('optional') === ['email', 'password', 'remember'] && $fields('always') === ['email', 'password'] && $fields('never') === ['email', 'password'];
});
check('AuthProfile::rememberMe() di default è optional', fn () => (new \Wonder\Auth\Frontend\AuthProfile())->rememberMe() === 'optional');
check('federato: la scelta passa dal profilo e dal token di completamento', function () {
    $src = (string) file_get_contents(__DIR__.'/../../class/Auth/Frontend/AuthController.php');

    return substr_count($src, 'new LegacySessionLoginAdapter()') === 0
        && str_contains($src, "new LegacySessionLoginAdapter(\$this->profile->remember(\$_POST))")
        && str_contains($src, "'remember' => \$this->profile->remember(\$_POST)")
        && str_contains($src, "new LegacySessionLoginAdapter((bool) (\$record->metadata['remember'] ?? false))");
});
check('RememberMe::requested(): solo valori di checkbox spuntata', fn () =>
    \Wonder\Auth\RememberMe::requested(['remember' => 'true']) && \Wonder\Auth\RememberMe::requested(['remember' => 'on'])
    && !\Wonder\Auth\RememberMe::requested(['remember' => '']) && !\Wonder\Auth\RememberMe::requested([])
    && !\Wonder\Auth\RememberMe::requested(['remember' => ['true']])
);

summary();
}

// --- Processo figlio ---------------------------------------------------------

function authScenario(string $scenario, array $opts): void
{
    $GLOBALS['OPTS'] = $opts;
    $GLOBALS['MAILS'] = [];
    $GLOBALS['LOGS'] = [];
    $GLOBALS['REMEMBER'] = [];
    $GLOBALS['RENDER'] = null;
    $GLOBALS['REDIRECT'] = null;
    $GLOBALS['EXITED'] = true;
    $GLOBALS['ALERT'] = null;
    $GLOBALS['SOCIETY'] = (object) ['email' => 'info@negozio.test'];
    $GLOBALS['SEO'] = (object) [];
    $GLOBALS['PAGE'] = (object) [];

    // In locale reCAPTCHA salta: qui interessa il flusso dopo il captcha.
    $_ENV['APP_ENV'] = 'local';
    \Wonder\App\Environment::reset();

    $_SESSION = [];
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '203.0.113.9'];
    $_COOKIE = [];
    $_POST = ['csrf_token' => \Wonder\Http\Csrf::token()];

    $view = sys_get_temp_dir().'/wi-auth-controller-'.getmypid().'.php';
    file_put_contents($view, '<?php $GLOBALS["RENDER"] = ["surface" => $surface, "alert" => $alert === null ? null : (int) $alert, "errors" => $errors, "federated_error" => $federated_error, "fields" => array_keys($fields)];');
    $GLOBALS['VIEW'] = $view;

    register_shutdown_function(static function () use ($view): void {
        echo "\n@@".json_encode([
            'render' => $GLOBALS['RENDER'],
            'redirect' => $GLOBALS['REDIRECT'],
            'exited' => $GLOBALS['EXITED'],
            'mails' => $GLOBALS['MAILS'],
            'logs' => $GLOBALS['LOGS'],
            'remember' => $GLOBALS['REMEMBER'],
            'session_user' => $_SESSION['user_id'] ?? null,
        ]);
        @unlink($view);
    });

    authStubs();

    \Wonder\Auth\LoginThrottle::useCounter(static fn (string $where): int => !empty($GLOBALS['OPTS']['throttled']) ? 100000 : 0);

    $profile = new class extends \Wonder\Auth\Frontend\AuthProfile {
        public function viewPath(string $page): string { return $GLOBALS['VIEW']; }
        public function googleEnabled(): bool { return false; }
        public function rememberMe(): string { return (string) ($GLOBALS['OPTS']['policy'] ?? parent::rememberMe()); }
    };
    $controller = new \Wonder\Auth\Frontend\AuthController($profile);

    switch ($scenario) {
        case 'login':
            $_POST += (array) ($opts['post'] ?? []) + ['email' => 'mario@example.com', 'password' => 'Sbagliata-1'];
            $controller->handle('login');
            break;
        case 'signup':
            $_POST += ['name' => 'Mario', 'surname' => 'Rossi', 'email' => 'mario@example.com', 'accept_privacy_policy' => 'true'];
            $controller->handle('signup.request');
            break;
    }

    $GLOBALS['EXITED'] = false;
}

function authStubs(): void
{
    eval(<<<'STUBS'
        namespace Wonder\Auth\Frontend {
            // Il redirect del controller passa da qui: si registra la destinazione.
            function header(string $value, bool $replace = true, int $code = 0): void {
                if (str_starts_with($value, 'Location: ')) { $GLOBALS['REDIRECT'] = substr($value, 10); }
            }
        }
        namespace {
            function __r(string $name, array $params = []): string { return '/'.$name; }
            function __t(string $key, array $params = []): string { return $params === [] ? $key : $key.' '.json_encode($params); }
            function infoUser($value, $filter = 'id'): object {
                $mode = $GLOBALS['OPTS']['user'] ?? 'none';
                if ($mode === 'none') { return (object) ['exists' => false]; }
                return (object) [
                    'exists' => true, 'id' => 7, 'email' => 'mario@example.com', 'phone' => '',
                    'password' => in_array($mode, ['federated', 'partial'], true) ? '' : password_hash('Giusta-1', PASSWORD_DEFAULT),
                    'active' => true, 'deleted' => false, 'area' => ['frontend'], 'authority' => ['client'],
                ];
            }
            function isUserEmailVerified($userId): bool { return true; }
            function permissions($authority) { return (object) ['area' => 'frontend']; }
            function userPermissionVerificationRules($permission) { return []; }
            function userPermissionEmailVerificationConfig($permission, $rules, $flow) { return ['required' => false]; }
            function sendMail($from, $to, $subject, $content) { $GLOBALS['MAILS'][] = ['from' => $from, 'to' => $to, 'subject' => $subject, 'body' => $content]; return true; }
            function user($values, $id = null) {
                if (($GLOBALS['OPTS']['signup'] ?? '') === 'existing') { $GLOBALS['ALERT'] = 906; return (object) ['user' => (object) ['exists' => false]]; }
                return (object) ['user' => (object) ['exists' => true, 'id' => 8]];
            }
            function sqlBind($sql, $params) { foreach ((array) $params as $p) { $sql = preg_replace('/\?/', "'".addslashes((string) $p)."'", $sql, 1); } return $sql; }
            function sqlSelect($table, $cond = null, $limit = null, $order = null, $dir = null, $attributes = '*') {
                if ($table === 'auth_federated' && ($GLOBALS['OPTS']['user'] ?? '') === 'federated') {
                    return (object) ['exists' => true, 'row' => [['id' => 1, 'user_id' => 7, 'provider' => 'google', 'subject' => 'g-7', 'email' => 'mario@example.com']]];
                }
                return (object) ['exists' => false, 'row' => []];
            }
            function sqlInsert($table, $values) {
                if ($table === 'auth_log') { $GLOBALS['LOGS'][] = $values; }
                if ($table === 'auth_remember') { $GLOBALS['REMEMBER'][] = $values; }
                return (object) ['success' => true, 'insert_id' => 1];
            }
            function sqlModify($table, $values, $col = null, $id = null) { return (object) ['success' => true]; }
        }
    STUBS);

    require_once __DIR__.'/../../app/config/array/characters.php';
    require_once __DIR__.'/../../app/function/string/common.php';
    require_once __DIR__.'/../../app/function/string/sanitize.php';
    require_once __DIR__.'/../../app/function/string/password.php';
    require_once __DIR__.'/../../app/function/user/auth.php';
}

if (($argv[1] ?? '') === '--scenario') {
    authScenario((string) $argv[2], (array) json_decode((string) ($argv[3] ?? '{}'), true));
}
