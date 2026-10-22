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
    && ($personal['personal']['values'] ?? null) === ['name' => 'Mario'] && ($personal['alert'] ?? null) === null
);
check('dati personali senza modal indicato → pagina con il toast 929', function () use ($run) {
    $r = $run('personal', ['name' => 'Mario']);

    return ($r['personal']['open'] ?? null) === '' && ($r['personal']['values'] ?? null) === [] && ($r['alert'] ?? null) === 929;
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
    $_SESSION = [];
    $_SERVER = ['REQUEST_METHOD' => 'POST'];
    $_POST = ['_csrf' => 'scaduto'] + $post;

    register_shutdown_function(static function (): void {
        echo "\n@@".json_encode($GLOBALS['OUT'] + [
            'alert' => $GLOBALS['ALERT'] ?? null,
            'flash' => $_SESSION[\Wonder\Frontend\Support\FlashMessage::KEY] ?? null,
            'status' => http_response_code(),
        ]);
    });

    eval(<<<'STUBS'
        namespace {
            function __t(string $key, array $params = []): string { return $key; }
            function __r(string $name, array $params = []): string { return ''; }
            function sqlSelect($table, $cond = null, $limit = null) { return (object) ['exists' => false, 'row' => []]; }
        }
    STUBS);

    $controller = $scenario === 'module'
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
        };

    $controller->handle($scenario === 'module' ? 'orders' : $scenario);
    $GLOBALS['OUT']['after'] = true;
}

if (($argv[1] ?? '') === '--scenario') {
    accountScenario((string) $argv[2], (array) json_decode((string) ($argv[3] ?? '{}'), true));
}
