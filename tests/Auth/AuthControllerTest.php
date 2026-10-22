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
check('registrazione, email esistente con il recupero saturo → la mail parte (bucket signup)', function () use ($run, $subjects) {
    $r = $run('signup', ['signup' => 'existing', 'user' => 'local', 'saturate' => 'recovery_request']);

    return $subjects($r) === ['auth.email.existing_subject'];
});

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
// Flusso Google reale fino al controller: id token firmato, JWKS in cache, gateway finto.
$google = static fn (array $post, array $extra = []): array => $run('federated', ['google' => true, 'post' => $post] + $extra);
$gPlain = $google([]);
check('Google senza checkbox → sessione aperta, nessun remember-me', fn () =>
    ($gPlain['session_user'] ?? null) === 7 && ($gPlain['remember'] ?? null) === [] && ($gPlain['redirect'] ?? null) === '/account/'
);
check('Google con checkbox spuntata → un token remember-me per l\'area', function () use ($google) {
    $r = $google(['remember' => 'true']);

    return count($r['remember'] ?? []) === 1 && ($r['remember'][0]['user_id'] ?? null) === 7 && ($r['remember'][0]['area'] ?? null) === 'frontend';
});
check('Google con rememberMe() never → niente remember-me anche con checkbox', fn () =>
    ($google(['remember' => 'true'], ['policy' => 'never'])['remember'] ?? null) === []
);
$gPending = $google(['remember' => 'true'], ['completion' => true]);
$gMeta = (array) json_decode((string) ($gPending['tokens'][0]['metadata_json'] ?? ''), true);
check('Google con completamento → scelta salvata nel token, nessun remember-me prima del completamento', fn () =>
    ($gMeta['remember'] ?? null) === true && ($gPending['remember'] ?? null) === []
    && str_starts_with((string) ($gPending['redirect'] ?? ''), '/account.auth.signup.completion?token=')
);
$gPendingPlain = $google([], ['completion' => true]);
$gMetaPlain = (array) json_decode((string) ($gPendingPlain['tokens'][0]['metadata_json'] ?? ''), true);
check('Google con completamento senza checkbox → remember: false nel token, niente remember-me al completamento', function () use ($run, $gMetaPlain, $gPendingPlain) {
    $r = $run('completion', ['google' => true, 'metadata' => $gMetaPlain]);

    return array_key_exists('remember', $gMetaPlain) && $gMetaPlain['remember'] === false && ($gPendingPlain['remember'] ?? null) === []
        && ($r['session_user'] ?? null) === 8 && ($r['remember'] ?? null) === [];
});
check('completamento Google → remember-me dal token emesso al login', function () use ($run, $gMeta) {
    $r = $run('completion', ['google' => true, 'metadata' => $gMeta]);

    return ($r['session_user'] ?? null) === 8 && count($r['remember'] ?? []) === 1 && ($r['remember'][0]['user_id'] ?? null) === 8;
});
check('completamento Google senza scelta nel token → niente remember-me, anche con checkbox nel POST', function () use ($run) {
    $r = $run('completion', ['google' => true, 'metadata' => ['federated_provider' => 'google', 'signup_event' => false], 'post' => ['remember' => 'true']]);

    return ($r['session_user'] ?? null) === 8 && ($r['remember'] ?? null) === [];
});

// --- I3: reCAPTCHA fail-closed sull'auth -----------------------------------
check('login in produzione con Google irraggiungibile → 617, nessun accesso', function () use ($run) {
    $action = (new \Wonder\Auth\Frontend\AuthProfile())->captchaAction('login');
    $r = $run('login', ['user' => 'local', 'env' => 'production', 'recaptcha' => true, 'post' => [
        'password' => 'Giusta-1', 'g-recaptcha-token' => 'token-di-prova', 'g-recaptcha-action' => $action,
    ]]);

    return ($r['render']['alert'] ?? null) === 617 && ($r['session_user'] ?? null) === null && ($r['remember'] ?? null) === []
        && in_array('engine_error', $r['captcha'] ?? [], true);
});
check('RememberMe::requested(): solo valori di checkbox spuntata', fn () =>
    \Wonder\Auth\RememberMe::requested(['remember' => 'true']) && \Wonder\Auth\RememberMe::requested(['remember' => 'on'])
    && !\Wonder\Auth\RememberMe::requested(['remember' => '']) && !\Wonder\Auth\RememberMe::requested([])
    && !\Wonder\Auth\RememberMe::requested(['remember' => ['true']])
);

// --- Token scaduto: avviso 929, campi ripopolati, nessun testo tecnico -----
$noTech = static fn (mixed $v): bool => preg_match('/csrf|token/i', strip_tags((string) json_encode($v, JSON_UNESCAPED_UNICODE))) !== 1;
$bad = $run('login', ['user' => 'local', 'bad_csrf' => true, 'post' => ['password' => 'Giusta-1', 'continue' => '/carrello/']]);
check('login con token scaduto → stessa pagina con alert 929, nessun accesso, nessun tentativo contato', fn () =>
    ($bad['render']['surface'] ?? null) === 'login' && ($bad['render']['alert'] ?? null) === 929 && ($bad['global_alert'] ?? null) === 929
    && ($bad['session_user'] ?? null) === null && ($bad['logs'] ?? null) === [] && ($bad['redirect'] ?? null) === null && ($bad['exited'] ?? null) === false
);
check('login con token scaduto → email e continue ripopolati, password e token vuoti (chiave presente)', fn () =>
    ($bad['render']['values']['email'] ?? null) === 'mario@example.com' && ($bad['render']['values']['continue'] ?? null) === '/carrello/'
    && ($bad['render']['values']['password'] ?? null) === '' && array_key_exists('csrf_token', (array) ($bad['render']['values'] ?? []))
    && $bad['render']['values']['csrf_token'] === ''
);
$badSignup = $run('signup', ['signup' => 'new', 'bad_csrf' => true]);
check('registrazione con token scaduto → form con 929 e i dati scritti, nessun utente né mail', fn () =>
    ($badSignup['render']['surface'] ?? null) === 'signup-request' && ($badSignup['render']['alert'] ?? null) === 929
    && ($badSignup['render']['values']['name'] ?? null) === 'Mario' && ($badSignup['render']['values']['email'] ?? null) === 'mario@example.com'
    && ($badSignup['redirect'] ?? null) === null && ($badSignup['mails'] ?? null) === []
);
$badCompletion = $run('completion', ['google' => true, 'metadata' => ['federated_provider' => 'google'], 'bad_csrf' => true, 'post' => ['password' => 'Nuova-12']]);
check('completamento con token scaduto → stessa pagina con 929, nessun accesso, password vuota', fn () =>
    ($badCompletion['render']['surface'] ?? null) === 'signup-completion' && ($badCompletion['render']['alert'] ?? null) === 929
    && ($badCompletion['session_user'] ?? null) === null && ($badCompletion['render']['values']['password'] ?? null) === ''
);
$badRecovery = $run('recovery', ['user' => 'local', 'bad_csrf' => true]);
check('recupero password con token scaduto → form con 929 e email, nessuna mail', fn () =>
    ($badRecovery['render']['surface'] ?? null) === 'password-recovery' && ($badRecovery['render']['alert'] ?? null) === 929
    && ($badRecovery['render']['values']['email'] ?? null) === 'mario@example.com' && ($badRecovery['mails'] ?? null) === []
);
$badLogout = $run('logout', ['bad_csrf' => true]);
check('logout con token scaduto → resta dentro, redirect all\'area con l\'avviso 929', fn () =>
    ($badLogout['session_user'] ?? null) === 7 && ($badLogout['redirect'] ?? null) === '/account/'
    && ($badLogout['flash']['level'] ?? null) === 'error' && ($badLogout['flash']['message'] ?? null) === 'notifications.929.text'
    && ($badLogout['flash']['title'] ?? null) === 'notifications.929.title'
);
$badGoogle = $run('federated', ['google' => true, 'bad_csrf' => true, 'post' => ['continue' => '/carrello/']]);
check('Google con token scaduto → nessun accesso, redirect al login (continue conservato) con l\'avviso', fn () =>
    ($badGoogle['session_user'] ?? null) === null && ($badGoogle['redirect'] ?? null) === '/account.auth.login?continue=%2Fcarrello%2F'
    && ($badGoogle['flash']['message'] ?? null) === 'notifications.929.text' && ($badGoogle['render'] ?? null) === null
);
check('Google dalla registrazione con token scaduto → redirect alla registrazione', fn () =>
    ($run('federated', ['google' => true, 'bad_csrf' => true, 'post' => ['auth_surface' => 'signup']])['redirect'] ?? null) === '/account.auth.signup.request'
);
check('token scaduto → nessun «CSRF» né «token» nelle risposte (salvo il nome del campo, vuoto)', fn () =>
    $noTech([$bad['render']['alert'], $bad['render']['errors'], $badLogout['flash'], $badGoogle['flash'], $badRecovery['render']['errors']])
);
check('notifications 929 in tutte le lingue: titolo e testo senza «CSRF» né «token»', function () use ($noTech) {
    foreach (['it', 'en', 'de', 'fr', 'es'] as $lang) {
        $n = json_decode((string) file_get_contents(__DIR__.'/../../resources/lang/'.$lang.'/notifications.json'), true)['929'] ?? null;
        if (!is_array($n) || trim((string) ($n['text'] ?? '')) === '' || !$noTech($n)) { return false; }
    }
    return true;
});

// --- Csrf::oldInput(): cosa torna nei form ----------------------------------
$old = \Wonder\Http\Csrf::oldInput([
    'email' => 'a@b.it', 'csrf_token' => 'x', '_csrf' => 'y', 'logout_token' => 'z', 'password' => 'p', 'password_confirm' => 'p',
    'current_password' => 'p', 'billing' => ['name' => 'Mario', 'password' => 'p'], 'token' => 'link-di-completamento',
], ['secret']);
check('Csrf::oldInput() svuota token di sessione e password, chiavi conservate', fn () =>
    $old['csrf_token'] === '' && $old['_csrf'] === '' && $old['logout_token'] === '' && $old['password'] === ''
    && $old['password_confirm'] === '' && $old['current_password'] === '' && $old['billing'] === ['name' => 'Mario', 'password' => '']
    && $old['email'] === 'a@b.it'
);
check('Csrf::oldInput() conserva il token del link (non è il CSRF) e accetta chiavi extra', fn () =>
    $old['token'] === 'link-di-completamento' && \Wonder\Http\Csrf::oldInput(['secret' => 's', 'a' => 1], ['secret']) === ['secret' => '', 'a' => 1]
);
// customer-account (HasSignupFields) legge `isset($values['csrf_token'])` per capire che il form è stato inviato.
check('Csrf::oldInput() lascia «inviato» il form di registrazione: isset(csrf_token) resta vero', fn () =>
    isset(\Wonder\Http\Csrf::oldInput(['csrf_token' => 'scaduto', 'name' => 'Mario'])['csrf_token'])
);

// --- Backend: stesso status, testo generico ---------------------------------
check('CsrfGuard: route con ->csrf() e token errato → 403 senza «CSRF» né «token»', function () use ($noTech) {
    $_POST = ['_csrf' => 'sbagliato'];
    $_SESSION = [];
    try {
        \Wonder\Http\CsrfGuard::enforce(['csrf' => true], 'POST');
    } catch (\Wonder\Http\Exceptions\ForbiddenHttpException $e) {
        return $e->statusCode() === 403 && $noTech($e->publicMessage()) && $noTech($e->getMessage()) && str_contains($e->getMessage(), 'Ricarica la pagina');
    } finally {
        $_POST = [];
    }
    return false;
});
check('quick-create: risposta di token errato con 403 e testo generico', function () use ($noTech) {
    $src = (string) file_get_contents(__DIR__.'/../../app/http/backend/resource/quick-create.php');
    return preg_match("/: \\['success' => false, 'error' => '([^']+)', 'status' => 403\\]/", $src, $m) === 1 && $noTech($m[1]);
});

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
    $_ENV['APP_ENV'] = (string) ($opts['env'] ?? 'local');
    \Wonder\App\Environment::reset();

    // ROOT temporaneo: log della guardia e cache JWKS fuori dal repo.
    $root = sys_get_temp_dir().'/wi-auth-root-'.getmypid();
    @mkdir($root.'/storage/cache', 0775, true);
    define('ROOT', $root);
    $GLOBALS['TOKENS'] = [];

    $_SESSION = [];
    $_SERVER = ['REQUEST_METHOD' => 'POST', 'REMOTE_ADDR' => '203.0.113.9'];
    $_COOKIE = [];
    $_POST = ['csrf_token' => \Wonder\Http\Csrf::token()];

    $view = sys_get_temp_dir().'/wi-auth-controller-'.getmypid().'.php';
    file_put_contents($view, '<?php $GLOBALS["RENDER"] = ["surface" => $surface, "alert" => $alert === null ? null : (int) $alert, "errors" => $errors, "federated_error" => $federated_error, "fields" => array_keys($fields), "values" => isset($values["csrf_token"]) && $values["csrf_token"] !== "" ? ["csrf_token" => "valido"] + $values : ($values ?? null)];');
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
            'tokens' => $GLOBALS['TOKENS'],
            'flash' => $_SESSION[\Wonder\Frontend\Support\FlashMessage::KEY] ?? null,
            'global_alert' => $GLOBALS['ALERT'] ?? null,
            'captcha' => array_map(static fn (string $line) => json_decode($line, true)['context']['reason'] ?? null,
                array_filter(explode("\n", (string) @file_get_contents(ROOT.'/storage/logs/error/recaptcha.log')))),
        ]);
        @unlink($view);
        exec('rm -rf '.escapeshellarg(ROOT));
    });

    authStubs();

    // `saturate`: satura solo i bucket che contano quell'evento.
    \Wonder\Auth\LoginThrottle::useCounter(static fn (string $where): int => !empty($GLOBALS['OPTS']['throttled'])
        || (isset($GLOBALS['OPTS']['saturate']) && str_contains($where, "'".$GLOBALS['OPTS']['saturate']."'")) ? 100000 : 0);

    $profile = new class extends \Wonder\Auth\Frontend\AuthProfile {
        public function viewPath(string $page): string { return $GLOBALS['VIEW']; }
        public function googleEnabled(): bool { return !empty($GLOBALS['OPTS']['google']); }
        public function rememberMe(): string { return (string) ($GLOBALS['OPTS']['policy'] ?? parent::rememberMe()); }
        public function requiresCompletion(array $user): bool { return !empty($GLOBALS['OPTS']['completion']); }
        public function gateway(): \Wonder\Auth\Federated\Contract\UserAccountGatewayInterface { return new \FakeGateway(); }
    };
    if (!empty($opts['google']) || !empty($opts['recaptcha'])) {
        authCredentials(!empty($opts['recaptcha']));
    }
    if (!empty($opts['google'])) {
        $_SESSION['wonder_oidc_nonce'][$profile->key()]['google'] = 'nonce-di-prova';
    }
    $controller = new \Wonder\Auth\Frontend\AuthController($profile);
    // `bad_csrf`: il token della pagina è scaduto (sessione nuova, form vecchio).
    $expire = static function (): void {
        if (!empty($GLOBALS['OPTS']['bad_csrf'])) { $_POST['csrf_token'] = 'scaduto'; }
    };

    switch ($scenario) {
        case 'login':
            $_POST += (array) ($opts['post'] ?? []) + ['email' => 'mario@example.com', 'password' => 'Sbagliata-1'];
            $expire();
            $controller->handle('login');
            break;
        case 'federated':
            $_POST += (array) ($opts['post'] ?? []) + ['credential' => authIdToken('nonce-di-prova')];
            $expire();
            $controller->handle('federated', ['provider' => 'google']);
            break;
        case 'completion':
            $_POST += (array) ($opts['post'] ?? []) + ['token' => authCompletionToken((array) ($opts['metadata'] ?? []))];
            $expire();
            $controller->handle('signup.completion');
            break;
        case 'signup':
            $_POST += ['name' => 'Mario', 'surname' => 'Rossi', 'email' => 'mario@example.com', 'accept_privacy_policy' => 'true'];
            $expire();
            $controller->handle('signup.request');
            break;
        case 'recovery':
            $_POST += ['email' => 'mario@example.com'];
            $expire();
            $controller->handle('password.recovery');
            break;
        case 'logout':
            $_SESSION['user_id'] = 7;
            $expire();
            $controller->handle('logout');
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
                if ($table === 'auth_federated' && !empty($GLOBALS['OPTS']['google'])) {
                    return (object) ['exists' => true, 'row' => ['id' => 1, 'user_id' => 7, 'provider' => 'google', 'provider_user_id' => 'g-7', 'email' => 'mario@example.com']];
                }
                if ($table === 'auth_one_time_tokens' && isset($GLOBALS['TOKEN_ROW']) && isset($cond['selector'])) {
                    return (object) ['exists' => true, 'row' => $GLOBALS['TOKEN_ROW']];
                }
                if ($table === 'auth_federated' && ($GLOBALS['OPTS']['user'] ?? '') === 'federated') {
                    return (object) ['exists' => true, 'row' => [['id' => 1, 'user_id' => 7, 'provider' => 'google', 'subject' => 'g-7', 'email' => 'mario@example.com']]];
                }
                return (object) ['exists' => false, 'row' => []];
            }
            function sqlInsert($table, $values) {
                if ($table === 'auth_log') { $GLOBALS['LOGS'][] = $values; }
                if ($table === 'auth_remember') { $GLOBALS['REMEMBER'][] = $values; }
                if ($table === 'auth_one_time_tokens') { $GLOBALS['TOKENS'][] = $values; }
                return (object) ['success' => true, 'insert_id' => 1];
            }
            function sqlModify($table, $values, $col = null, $id = null) { return (object) ['success' => true]; }

            final class FakeGateway implements \Wonder\Auth\Federated\Contract\UserAccountGatewayInterface {
                public function findUserByEmail(string $email): ?array { return ['id' => 7, 'email' => $email]; }
                public function findUserById(int $userId): ?array { return ['id' => $userId, 'email' => 'mario@example.com', 'phone' => '']; }
                public function createUserFromFederatedIdentity(\Wonder\Auth\Federated\FederatedIdentityPayload $identity, string $area): int { return 0; }
                public function hasLocalPassword(int $userId): bool { return false; }
                public function setLocalPassword(int $userId, string $passwordHash): void {}
                public function canAccessArea(int $userId, string $area, array $requiredAuthorities = []): bool { return true; }
            }

            // `markConsumed()` e `Transaction::run()` usano mysqli: connessione finta in cache.
            final class FakeStmt {
                public int $affected_rows = 1;
                public function bind_param(string $types, mixed &...$vars): bool { return true; }
                public function execute(): bool { return true; }
                public function close(): bool { return true; }
            }
            final class FakeMysqli extends \mysqli {
                public function __construct() {}
                #[\ReturnTypeWillChange] public function prepare(string $query) { return new FakeStmt(); }
                public function begin_transaction(int $flags = 0, ?string $name = null): bool { return true; }
                public function commit(int $flags = 0, ?string $name = null): bool { return true; }
                public function rollback(int $flags = 0, ?string $name = null): bool { return true; }
            }
        }
    STUBS);

    require_once __DIR__.'/../../app/config/array/characters.php';
    require_once __DIR__.'/../../app/function/string/common.php';
    require_once __DIR__.'/../../app/function/string/sanitize.php';
    require_once __DIR__.'/../../app/function/string/password.php';
    require_once __DIR__.'/../../app/function/user/auth.php';
}

/** Credenziali finte; con $engineDown il costruttore dell'engine reCAPTCHA lancia. */
function authCredentials(bool $engineDown): void
{
    $api = new class($engineDown) {
        public string $google_oauth_client_id = 'client-di-prova';
        public string $g_recaptcha_site_key = 'site-checkbox';
        public string $g_recaptcha_score_site_key = '';
        public string $g_recaptcha_badge = '';
        public string $g_maps_place_id = '';
        public string $g_maps_map_id = '';
        public string $gcp_api_key = 'api-key';
        private int $reads = 0;
        public function __construct(private bool $engineDown) {}
        // Prima lettura: chiavi della guardia; la seconda è nel costruttore dell'engine.
        public function __get(string $name): mixed
        {
            if ($name === 'gcp_project_id') {
                if ($this->engineDown && ++$this->reads > 1) { throw new \RuntimeException('engine giù'); }
                return 'progetto';
            }
            return null;
        }
    };
    (new ReflectionProperty(\Wonder\App\Credentials::class, 'API'))->setValue(null, $api);

    $set = static fn (string $class, string $prop, mixed $value) => (new ReflectionProperty($class, $prop))->setValue(null, $value);
    $set(\Wonder\App\Credentials::class, 'DB', (object) [
        'hostname' => 'stub.invalid', 'username' => 'u', 'password' => 'p',
        'database' => ['main' => 'stubdb'], 'charset' => 'latin1', 'collation' => '',
    ]);
    foreach (['hostname' => 'stub.invalid', 'username' => 'u', 'password' => 'p', 'database' => 'stubdb'] as $k => $v) {
        $set(\Wonder\Sql\Connection::class, $k, $v);
    }
    $set(\Wonder\Sql\Connection::class, 'connection', [base64_encode('stub.invalid | u | p | stubdb') => new FakeMysqli()]);
}

/** Id token Google firmato con una chiave RSA locale, pubblicata nella cache JWKS del verificatore. */
function authIdToken(string $nonce): string
{
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $pem);
    $rsa = openssl_pkey_get_details($key)['rsa'];
    $b64 = static fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
    $jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'k1', 'use' => 'sig', 'alg' => 'RS256', 'n' => $b64($rsa['n']), 'e' => $b64($rsa['e'])]]];
    file_put_contents(ROOT.'/storage/cache/oidc-'.hash('sha256', 'https://www.googleapis.com/oauth2/v3/certs').'.json', json_encode($jwks));

    return \Firebase\JWT\JWT::encode([
        'iss' => 'https://accounts.google.com', 'aud' => 'client-di-prova', 'sub' => 'g-7', 'nonce' => $nonce,
        'email' => 'mario@example.com', 'email_verified' => true, 'iat' => time(), 'exp' => time() + 300,
    ], $pem, 'RS256', 'k1');
}

/** Token di completamento valido: la riga che `OneTimeToken::inspect()` legge. */
function authCompletionToken(array $metadata): string
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $GLOBALS['TOKEN_ROW'] = [
        'id' => 5, 'subject_user_id' => 7, 'actor_user_id' => null, 'continue_url' => '/account/',
        'validator_hash' => hash('sha256', $validator), 'metadata_json' => json_encode($metadata),
        'expires_at' => date('Y-m-d H:i:s', time() + 600), 'consumed_at' => null, 'revoked_at' => null,
    ];

    return $selector.'.'.$validator;
}

if (($argv[1] ?? '') === '--scenario') {
    authScenario((string) $argv[2], (array) json_decode((string) ($argv[3] ?? '{}'), true));
}
