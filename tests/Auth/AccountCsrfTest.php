<?php
/** php tests/Auth/AccountCsrfTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

// Pannello account con il token della pagina scaduto: niente 419 tecnico, la pagina torna con l'avviso.
// Ogni scenario gira in un processo figlio perché invalidCsrf() chiude con exit().
if (($argv[1] ?? '') !== '--scenario') {
require __DIR__.'/../harness.php';

$run = static function (string $scenario, array $post = []): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '.escapeshellarg($scenario).' '.escapeshellarg(json_encode($post)).' 2>&1';
    $out = (string) shell_exec($cmd);
    $pos = strrpos($out, "\n@@");
    $json = $pos === false ? null : json_decode(substr($out, $pos + 3), true);

    return is_array($json) ? $json : ['raw' => $out];
};

$personal = $run('personal', ['form' => 'personal', 'name' => 'Mario', 'current_password' => 'p', 'password' => 'p']);
check('dati personali con token scaduto → nessun salvataggio, uscita dopo il render', fn () =>
    ($personal['saved'] ?? null) === false && ($personal['after'] ?? null) === false && ($personal['status'] ?? null) !== 419
);
check('dati personali con token scaduto → modal riaperto con l\'errore 929 e i valori senza password né token', fn () =>
    ($personal['personal']['open'] ?? null) === 'personal' && ($personal['personal']['errors'] ?? null) === ['notifications.929.text']
    && ($personal['personal']['values'] ?? null) === ['_csrf' => '', 'name' => 'Mario', 'current_password' => '', 'password' => '']
    && ($personal['alert'] ?? null) === null
);
check('dati personali senza modal indicato → pagina con il toast 929', function () use ($run) {
    $r = $run('personal', ['name' => 'Mario']);

    return ($r['personal']['open'] ?? null) === '' && ($r['personal']['values'] ?? null) === [] && ($r['alert'] ?? null) === 929;
});
check('modal email senza password (modal assente) → pagina con il toast 929', function () use ($run) {
    $r = $run('personal', ['form' => 'email', 'email' => 'nuova@example.com']);

    return ($r['personal']['open'] ?? null) === 'email' && ($r['alert'] ?? null) === 929 && ($r['saved'] ?? null) === false;
});
check('conferma email con token scaduto e link non più valido → esito «invalid», nessun cambio', function () use ($run) {
    $r = $run('email.confirm', ['token' => 'non-valido']);

    return ($r['outcome'] ?? null) === 'invalid' && ($r['confirmed'] ?? null) === false && ($r['after'] ?? null) === false;
});
check('azione di un modulo (handle senza parent) → redirect al pannello con l\'avviso 929', function () use ($run) {
    $r = $run('module');

    return ($r['redirect'] ?? null) === '/account/' && ($r['flash']['message'] ?? null) === 'notifications.929.text'
        && ($r['flash']['level'] ?? null) === 'error' && ($r['after'] ?? null) === false;
});
// Render vero di pagina, layout del pannello e modal: solo il layout del sito (`frontend.main`) è uno stub.
$secret = ['password_confirmation' => 'segreta-1', 'csrf_token' => 'vecchio-1', 'logout_token' => 'vecchio-2'];
$page = $run('personal.render', ['form' => 'personal', 'name' => 'Mario<b>', 'current_password' => 'p', 'password' => 'p'] + $secret);
check('render vero di personal → modal riaperto con l\'errore 929, nessun salvataggio', fn () =>
    ($page['saved'] ?? null) === false && ($page['after'] ?? null) === false && ($page['status'] ?? null) !== 419
    && ($page['modal']['open'] ?? null) === 'personal' && ($page['modal']['errors'] ?? null) === ['notifications.929.text']
    && preg_match('/class="wi-modal wi-show" id="account-personal"/', (string) ($page['html'] ?? '')) === 1
    && str_contains((string) $page['html'], 'notifications.929.text') && ($page['alert'] ?? null) === null
);
check('render vero di personal → password_confirmation, csrf_token e logout_token arrivano vuoti ai modal (chiavi presenti)', function () use ($page, $secret) {
    $values = (array) ($page['modal']['values'] ?? []);
    foreach (array_keys($secret) as $key) {
        if (!array_key_exists($key, $values) || $values[$key] !== '') {
            return false;
        }
    }

    return ($values['name'] ?? null) === 'Mario<b>' && ($values['password'] ?? null) === '' && ($values['_csrf'] ?? null) === '';
});
check('render vero di personal → nome ripopolato ed escapato, nessun segreto né token scaduto nell\'HTML, _csrf nuovo', function () use ($page) {
    $html = (string) ($page['html'] ?? '');
    preg_match_all('/name="_csrf" value="([^"]*)"/', $html, $m);

    return str_contains($html, 'name="name" value="Mario&lt;b&gt;"')
        && preg_match('/segreta-1|vecchio-1|vecchio-2|scaduto/', $html) !== 1
        && $m[1] !== [] && array_unique($m[1]) === [(string) $page['token']];
});
// Formato del link: selettore di 24 esadecimali, punto, validatore di 64.
$link = str_repeat('a', 24).'.'.str_repeat('b', 64);
$confirm = $run('email.confirm.render', ['token' => $link]);
check('render vero di email.confirm con link valido → pagina di conferma con il 929, nessun cambio email', function () use ($confirm, $link) {
    $html = (string) ($confirm['html'] ?? '');
    preg_match_all('/name="_csrf" value="([^"]*)"/', $html, $m);

    return ($confirm['after'] ?? null) === false && ($confirm['status'] ?? null) !== 419 && ($confirm['alert'] ?? null) === 929
        && str_contains($html, 'account.email.confirm_title') && str_contains($html, 'notifications.929.text')
        && str_contains($html, 'name="token" value="'.$link.'"') && !str_contains($html, 'scaduto')
        && $m[1] !== [] && array_unique($m[1]) === [(string) $confirm['token']] && ($confirm['writes'] ?? null) === [];
});
check('nessun «CSRF» né «token» negli avvisi resi', fn () =>
    preg_match('/csrf|token/i', (string) json_encode([$personal['personal']['errors'] ?? null, $run('module')['flash'] ?? null])) !== 1
);

summary();
}

// --- Processo figlio ---------------------------------------------------------

function accountScenario(string $scenario, array $post): void
{
    $GLOBALS['OUT'] = ['saved' => false, 'confirmed' => false, 'after' => false, 'personal' => null, 'outcome' => null, 'redirect' => null];
    $GLOBALS['ALERT'] = null;
    $GLOBALS['SEO'] = (object) [];
    $GLOBALS['WRITES'] = [];
    $render = str_ends_with($scenario, '.render');
    $_SESSION = [];
    $_SERVER = ['REQUEST_METHOD' => 'POST'];
    $_POST = ['_csrf' => 'scaduto'] + $post;

    // Render vero: ROOT temporaneo con il solo layout del sito, sessione attiva per il campo _csrf.
    $root = '';
    if ($render) {
        $root = sys_get_temp_dir().'/wi-account-csrf-'.getmypid();
        @mkdir($root.'/custom/view/layout/frontend', 0777, true);
        file_put_contents($root.'/custom/view/layout/frontend/main.php', '<?=$PAGE_CONTENT?>');
        file_put_contents($root.'/custom/view/layout/frontend/minimal.php', '<?=$PAGE_CONTENT?>');
        $GLOBALS['ROOT'] = $root;
        $GLOBALS['ROOT_APP'] = dirname(__DIR__, 2).'/app';
        session_save_path($root);
        session_id('csrf'.getmypid());
        @session_start();
        ob_start();
    }

    register_shutdown_function(static function () use ($render, $root): void {
        $html = $render ? (string) ob_get_clean() : null;
        $token = $render ? (string) ($_SESSION['wonder_auth_csrf'] ?? '') : null;
        if ($render) {
            session_destroy();
            array_map('unlink', array_filter(glob($root.'/{,custom/view/layout/frontend/}*', GLOB_BRACE) ?: [], 'is_file'));
            foreach (['/custom/view/layout/frontend', '/custom/view/layout', '/custom/view', '/custom', ''] as $dir) {
                @rmdir($root.$dir);
            }
        }
        echo "\n@@".json_encode($GLOBALS['OUT'] + [
            'alert' => $GLOBALS['ALERT'] ?? null,
            'flash' => $_SESSION[\Wonder\Frontend\Support\FlashMessage::KEY] ?? null,
            'status' => http_response_code(),
            'html' => $html,
            'token' => $token,
            'writes' => $GLOBALS['WRITES'],
        ]);
    });

    eval(<<<'STUBS'
        namespace {
            function __t(string $key, array $params = []): string { return $key; }
            function __r(string $name, array $params = []): string { return ''; }
            function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
            function phonePrefix(): array { return ['+39' => '+39']; }
            function infoUser($value, $filter = 'id'): object { return (object) ['id' => 7, 'name' => 'Mario', 'email' => 'mario@example.com']; }
            function sqlSelect($table, $cond = null, $limit = null) {
                $row = $GLOBALS['DB'][$table] ?? null;
                return (object) ['exists' => $row !== null, 'row' => $row ?? []];
            }
            function sqlModify(...$args) { $GLOBALS['WRITES'][] = 'modify'; return (object) ['success' => true]; }
        }
    STUBS);

    // Con il render vero l'utente ha una password (c'è il modal email) e il link di conferma è ancora valido.
    $GLOBALS['DB'] = $render ? [
        'user' => ['password' => 'hash'],
        'auth_one_time_tokens' => [
            'id' => 1, 'subject_user_id' => 7, 'validator_hash' => hash('sha256', str_repeat('b', 64)),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'metadata_json' => '{"email":"nuova@example.com"}',
        ],
    ] : [];

    $panel = $render
        ? new class extends \Wonder\Auth\Frontend\AccountPanel {
            // Registra quello che arriva ai modal dei moduli, poi li lascia invariati.
            public function personalModals(array $modals, object $user, string $open, array $errors, array $values): array
            {
                $GLOBALS['OUT']['modal'] = compact('open', 'errors', 'values');
                return parent::personalModals($modals, $user, $open, $errors, $values);
            }
        }
        : new \Wonder\Auth\Frontend\AccountPanel();

    $controller = $render
        ? new class($panel, new \Wonder\Auth\Frontend\AuthProfile()) extends \Wonder\Auth\Frontend\AccountController {
            protected function user(): object { return (object) ['id' => 7, 'name' => 'Mario', 'email' => 'mario@example.com']; }
            protected function savePersonal(int $userId): array { $GLOBALS['OUT']['saved'] = true; return []; }
        }
        : ($scenario === 'module'
        ? new class(new \Wonder\Auth\Frontend\AccountPanel(), new \Wonder\Auth\Frontend\AuthProfile()) extends \Wonder\Auth\Frontend\AccountController {
            // Come i moduli: le proprie azioni non passano da parent::handle().
            public function handle(string $action, array $parameters = []): void { $this->requireCsrf(); $GLOBALS['OUT']['saved'] = true; }
            protected function redirect(string $url): never { $GLOBALS['OUT']['redirect'] = $url; exit; }
        }
        : new class(new \Wonder\Auth\Frontend\AccountPanel(), new \Wonder\Auth\Frontend\AuthProfile()) extends \Wonder\Auth\Frontend\AccountController {
            protected function user(): object { return (object) ['id' => 7]; }
            protected function savePersonal(int $userId): array { $GLOBALS['OUT']['saved'] = true; return []; }
            protected function renderPersonal(string $open, array $errors, array $values): void { $GLOBALS['OUT']['personal'] = compact('open', 'errors', 'values'); }
            protected function renderEmailConfirm(string $token, string $email, ?int $alert = null): void { $GLOBALS['OUT']['outcome'] = 'confirm-page'; }
            protected function emailOutcome(string $outcome): void { $GLOBALS['OUT']['outcome'] = $outcome; }
            protected function redirect(string $url): never { $GLOBALS['OUT']['redirect'] = $url; exit; }
        });

    $controller->handle($scenario === 'module' ? 'orders' : str_replace('.render', '', $scenario));
    $GLOBALS['OUT']['after'] = true;
}

if (($argv[1] ?? '') === '--scenario') {
    accountScenario((string) $argv[2], (array) json_decode((string) ($argv[3] ?? '{}'), true));
}
