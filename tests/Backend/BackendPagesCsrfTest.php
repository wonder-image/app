<?php
/** php tests/Backend/BackendPagesCsrfTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

// Ogni scenario gira in un processo figlio: i handler chiudono con exit().
// Il figlio parte in fondo al file, dopo le classi di prova.
if (($argv[1] ?? '') !== '--scenario') {
require __DIR__.'/../harness.php';

$run = static function (string $scenario, string $token, string $extra = ''): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --scenario '.escapeshellarg($scenario).' '.escapeshellarg($token).' '.escapeshellarg($extra).' 2>&1';
    $out = (string) shell_exec($cmd);
    $pos = strrpos($out, "\n@@");
    $json = $pos === false ? null : json_decode(substr($out, $pos + 3), true);

    return is_array($json) ? $json : ['raw' => $out];
};

$csrfAlert = 929;

// Pagine con alert: senza token o con token errato → alert 929 e nessuna scrittura.
$pageScenarios = [
    'resource-store' => 'store della Resource',
    'resource-update' => 'update della Resource',
    'user-store' => 'creazione utente',
    'user-update' => 'modifica utente',
    'account-modify' => 'account: profilo',
    'account-password' => 'account: password',
    'home' => 'POST /backend/',
    'upload-massive' => 'upload massivo',
    'configuration-file' => 'file di configurazione',
];

foreach ($pageScenarios as $scenario => $label) {
    foreach (['none', 'wrong'] as $token) {
        check("{$label}: token {$token} → alert {$csrfAlert} e nessuna scrittura", function () use ($run, $scenario, $token, $csrfAlert) {
            $r = $run($scenario, $token);

            return ($r['alert'] ?? null) == $csrfAlert && ($r['writes'] ?? null) === [] && !empty($r['rendered']);
        });
    }

    check("{$label}: token valido → nessun alert CSRF e arriva alla scrittura", function () use ($run, $scenario, $csrfAlert) {
        $r = $run($scenario, 'valid');
        // POST /backend/ non scrive nulla: con token valido mostra la pagina.
        $reached = $scenario === 'home' ? !empty($r['rendered']) : ($r['writes'] ?? []) !== [];

        return ($r['alert'] ?? null) != $csrfAlert && $reached;
    });
}

// Cambio password dell'account: la password nuova vuota o di soli spazi è rifiutata (C-PWD-I1).
foreach (['empty' => 'vuota', 'spaces' => 'di soli spazi'] as $extra => $label) {
    check("account: password nuova {$label} → alert 977 e nessuna scrittura", function () use ($run, $extra) {
        $r = $run('account-password', 'valid', $extra);

        return ($r['alert'] ?? null) == 977 && ($r['writes'] ?? null) === [] && !empty($r['rendered']);
    });
}
check('account: password nuova valida → salva la password', fn () => ($run('account-password', 'valid')['writes'] ?? []) === ['modify user']);

// Form legacy (`app/html/backend/index.php`): non rende pagina, scrive e redirige.
foreach (['none', 'wrong'] as $token) {
    check("form legacy: token {$token} → alert {$csrfAlert}, nessuna scrittura e nessun redirect", function () use ($run, $token, $csrfAlert) {
        $r = $run('legacy-index', $token);

        return ($r['alert'] ?? null) == $csrfAlert && ($r['writes'] ?? null) === [] && ($r['exited'] ?? null) === false;
    });
}

foreach (['upload' => 'Salva', 'upload-add' => 'Salva e aggiungi'] as $button => $label) {
    check("form legacy ({$label}): token valido → scrive e redirige", function () use ($run, $button, $csrfAlert) {
        $r = $run('legacy-index', 'valid', $button);

        return ($r['alert'] ?? null) != $csrfAlert && ($r['writes'] ?? []) === ['insert csrf_legacy'] && ($r['exited'] ?? null) === true;
    });
}

check('form legacy: modifica con token valido → sqlModify', fn () => ($run('legacy-index', 'valid', 'modify')['writes'] ?? []) === ['modify csrf_legacy']);

// Il core emette `_csrf` nel form del sito tramite i pulsanti del template legacy.
$legacy = $run('legacy-render', 'none');
$forms = $legacy['extra']['forms'] ?? [];
$countCsrf = static fn (string $html): int => preg_match_all('/name="_csrf"/', $html);

check('form legacy reso: submitAdd() emette un solo _csrf per i due pulsanti', fn () => isset($forms['submitAdd']) && $countCsrf($forms['submitAdd']) === 1);
check('form legacy reso: form con Csrf::field() già presente → nessun doppione', fn () => isset($forms['giaPresente']) && $countCsrf($forms['giaPresente']) === 1);
check('form legacy reso: due submit() nello stesso form → un solo _csrf', fn () => isset($forms['dueSubmit']) && $countCsrf($forms['dueSubmit']) === 1);
check('form legacy reso: senza buffer, due submit() → un solo _csrf', fn () => isset($forms['senzaBuffer']) && $countCsrf($forms['senzaBuffer']) === 1);
check('form legacy reso: POST con il token della pagina supera il controllo e scrive', fn () =>
    ($legacy['alert'] ?? null) != $csrfAlert && ($legacy['writes'] ?? []) === ['insert csrf_legacy'] && ($legacy['exited'] ?? null) === true
);
check('fuori dal template legacy submit() non emette _csrf', fn () => isset($forms['fuoriLegacy']) && $countCsrf($forms['fuoriLegacy']) === 0);

// Il token arriva anche dall'header (stesso comportamento di `Csrf::verify()`).
check('store della Resource: token nell\'header X-WI-CSRF → arriva alla scrittura', function () use ($run) {
    return ($run('resource-store', 'header')['writes'] ?? []) !== [];
});

// Redirect con FlashAlert: delete e pagina-form.
foreach (['resource-delete' => 'delete della Resource', 'resource-submit' => 'pagina-form della Resource'] as $scenario => $label) {
    foreach (['none', 'wrong'] as $token) {
        check("{$label}: token {$token} → flash {$csrfAlert}, redirect e nessuna scrittura", function () use ($run, $scenario, $token, $csrfAlert) {
            $r = $run($scenario, $token);

            return ($r['flash'] ?? null) === (string) $csrfAlert && ($r['writes'] ?? null) === [] && !empty($r['exited']);
        });
    }

    check("{$label}: token valido → arriva alla scrittura", function () use ($run, $scenario) {
        return ($run($scenario, 'valid')['writes'] ?? []) !== [];
    });
}

// Chiamata JSON: 403 senza toccare il controller.
foreach (['none', 'wrong'] as $token) {
    check("quick-create: token {$token} → 403 JSON e controller non chiamato", function () use ($run, $token) {
        $r = $run('quick-create', $token);
        $body = json_decode((string) ($r['output'] ?? ''), true);

        return ($r['status'] ?? null) === 403 && is_array($body) && ($body['success'] ?? null) === false
            && ($body['response'] ?? null) === 'notifications.929.text' && ($body['status'] ?? null) === 403;
    });
}

check('quick-create: token valido → risponde il controller (risorsa inesistente = 404)', function () use ($run) {
    $r = $run('quick-create', 'valid');

    return ($r['status'] ?? null) === 404;
});

// I form delle pagine portano il campo `_csrf`.
$root = dirname(__DIR__, 2);
$forms = [
    'app/view/pages/backend/account/index.php' => 2,
    'app/view/pages/backend/user/manage.php' => 1,
    'app/view/pages/backend/config/configuration-file.php' => 1,
    'app/view/pages/backend/account/login.php' => 1,
    'app/view/pages/backend/account/password-recovery.php' => 1,
    'app/view/pages/backend/account/password-restore.php' => 1,
    'app/view/pages/backend/account/password-set.php' => 1,
];

foreach ($forms as $file => $count) {
    check("{$file} emette _csrf in ogni form", function () use ($root, $file, $count) {
        $src = (string) file_get_contents($root.'/'.$file);

        return substr_count($src, '<form') === $count
            && substr_count($src, 'Csrf::field()->render()') === $count;
    });
}

check('upload massivo: il form passa il campo _csrf al renderer', fn () =>
    str_contains((string) file_get_contents($root.'/app/view/pages/backend/media/upload-massive.php'), "'footer' => \\Wonder\\Http\\Csrf::fieldFor('post')")
);

check('QuickCreateModal manda l\'header X-WI-CSRF letto dal meta wi-csrf', function () use ($root) {
    $src = (string) file_get_contents($root.'/class/Backend/Support/QuickCreateModal.php');

    return str_contains($src, "'X-WI-CSRF': csrfToken()") && str_contains($src, 'meta[name="wi-csrf"]');
});

check('login, recupero, restore e set del backend controllano il CSRF (C-PWD-I4)', function () use ($root) {
    $files = glob($root.'/app/http/backend/account/{login,password-recovery,password-restore,password-set}.php', GLOB_BRACE) ?: [];

    foreach ($files as $file) {
        if (!str_contains((string) file_get_contents($file), 'ResourcePageController::CSRF_ALERT')) {
            return false;
        }
    }

    return count($files) === 4;
});

summary();
}

// --- Processo figlio ---------------------------------------------------------

function csrfPagesScenario(string $scenario, string $tokenMode): void
{
    $GLOBALS['W'] = [];
    $GLOBALS['RENDERED'] = false;
    $GLOBALS['ALERT'] = null;
    $GLOBALS['EXITED'] = true;

    $_SESSION = [];
    $token = \Wonder\Http\Csrf::token();
    $_SERVER = ['REQUEST_METHOD' => 'POST'];
    $csrf = match ($tokenMode) {
        'valid' => ['_csrf' => $token],
        'wrong' => ['_csrf' => str_repeat('0', 64)],
        default => [],
    };

    if ($tokenMode === 'header') {
        $_SERVER['HTTP_X_WI_CSRF'] = $token;
    }

    $appDir = sys_get_temp_dir().'/wi-csrf-pages-'.getmypid();
    $GLOBALS['TMP_APP'] = $appDir;

    foreach (['account/index', 'user/manage', 'home', 'media/upload-massive', 'config/configuration-file', 'resource/form'] as $view) {
        @mkdir(dirname($appDir.'/view/pages/backend/'.$view.'.php'), 0777, true);
        file_put_contents($appDir.'/view/pages/backend/'.$view.'.php', '<?php $GLOBALS["RENDERED"] = true;');
    }

    register_shutdown_function(static function () use ($appDir): void {
        $flash = $_SESSION[(new ReflectionClassConstant(\Wonder\Backend\Support\FlashAlert::class, 'KEY'))->getValue()]['code'] ?? null;
        $output = ob_get_level() > 0 ? (string) ob_get_clean() : '';
        echo "\n@@".json_encode([
            'alert' => $GLOBALS['ALERT'] ?? null,
            'writes' => $GLOBALS['W'],
            'rendered' => $GLOBALS['RENDERED'],
            'flash' => $flash,
            'exited' => $GLOBALS['EXITED'],
            'status' => http_response_code(),
            'output' => $output,
            'extra' => $GLOBALS['EXTRA'] ?? null,
        ]);
        exec('rm -rf '.escapeshellarg($appDir));
    });

    csrfPagesStubs();
    \Wonder\App\LegacyGlobals::set('ROOT_APP', $appDir);
    $ROOT_APP = $appDir;
    $GLOBALS['ROOT_APP'] = $appDir;

    switch ($scenario) {
        case 'resource-store':
        case 'resource-update':
        case 'resource-delete':
        case 'resource-submit':
            $_POST = $csrf + ['name' => 'nuovo'];
            $controller = (new ReflectionClass(\Wonder\Backend\Support\ResourcePageController::class))->newInstanceWithoutConstructor();
            (new ReflectionMethod($controller, '__construct'))->invoke($controller, CsrfPagesResource::class);
            $action = substr($scenario, 9);
            try {
                $controller->handle($action, ['id' => 5]);
            } catch (CsrfPagesReached $e) {
            }
            break;

        case 'user-store':
        case 'user-update':
            $_POST = $csrf + ['name' => 'Mario'];
            $controller = (new ReflectionClass(\Wonder\Backend\Support\UserManagementPageController::class))->newInstanceWithoutConstructor();
            (new ReflectionMethod($controller, '__construct'))->invoke($controller, CsrfPagesUserResource::class, 'users');
            $controller->handle($scenario === 'user-store' ? 'store' : 'update', ['id' => 7]);
            break;

        case 'account-modify':
        case 'account-password':
            $_POST = $csrf + ($scenario === 'account-modify'
                ? ['modify' => '1', 'password' => 'x']
                : ['modify-password' => '1', 'old-password' => 'x', 'new-password' => match ($GLOBALS['argv'][4] ?? '') {
                    // $argv[4]: 'empty' o 'spaces' per la password nuova (C-PWD-I1).
                    'empty' => '',
                    'spaces' => "   \t",
                    default => 'y',
                }]);
            csrfPagesInclude('/../../app/http/backend/account/index.php', [
                'USER' => (object) ['id' => 7, 'authority' => []],
                'DEFAULT' => (object) ['colorUser' => []],
            ]);
            break;

        case 'home':
            $_POST = $csrf;
            csrfPagesInclude('/../../app/http/backend/home.php', []);
            break;

        case 'upload-massive':
            $_POST = $csrf + ['upload' => '1', 'type' => 'document'];
            $_FILES = ['file' => ['name' => ['a.pdf'], 'type' => ['application/pdf'], 'tmp_name' => ['/tmp/x'], 'error' => [0], 'size' => [1]]];
            try {
                csrfPagesInclude('/../../app/http/backend/media/upload-massive.php', []);
            } catch (Throwable $e) {
                // Il primo passo verso il DB basta: il controllo CSRF è stato superato.
                $GLOBALS['W'][] = 'upload: '.$e::class;
            }
            break;

        case 'configuration-file':
            $_POST = $csrf + ['modify' => '1', 'htaccess' => 'X', 'robots' => 'Y'];
            // Cartella inesistente: con token valido la scrittura viene tentata e fallisce senza exit.
            $root = $tokenMode === 'valid' ? $appDir.'/missing' : $appDir;
            csrfPagesInclude('/../../app/http/backend/config/configuration-file.php', ['ROOT' => $root]);
            if (is_file($appDir.'/.htaccess') || is_file($appDir.'/robots.txt')) {
                $GLOBALS['W'][] = 'file scritti';
            }
            if ($tokenMode === 'valid' && !is_dir($root)) {
                $GLOBALS['W'][] = 'scrittura tentata';
            }
            break;

        case 'legacy-render':
            require_once __DIR__.'/../../app/function/backend/input.php';
            session_save_path($appDir);
            session_start();
            $forms = [];
            $forms['fuoriLegacy'] = '<form method="post">'.submit().'</form>';
            $legacyVars = [
                'NAME' => (object) ['table' => 'csrf_legacy', 'database' => '', 'folder' => 'legacy'],
                'TEXT' => (object) ['titleS' => 'voce'],
                'PATH' => (object) ['backend' => '/backend'],
                'PAGE' => (object) ['redirect' => '/backend/legacy/list.php', 'redirectBase64' => ''],
                'PAGE_TABLE' => ['name' => []],
            ];
            // GET: la pagina del sito include il template e rende il suo form.
            $_POST = [];
            csrfPagesInclude('/../../app/html/backend/index.php', $legacyVars);
            ob_start();
            echo '<form method="post">'.submitAdd().'</form>';
            echo '<form method="post">'.\Wonder\Http\Csrf::field()->render();
            echo submit();
            echo '</form>';
            echo '<form method="post">';
            echo submit('Salva', 'upload');
            echo submit('Salva e aggiungi', 'upload-add');
            echo '</form>';
            $page = (string) ob_get_clean();
            preg_match_all('/<form.*?<\/form>/s', $page, $m);
            [$forms['submitAdd'], $forms['giaPresente'], $forms['dueSubmit']] = $m[0] + ['', '', ''];
            \Wonder\Http\Csrf::enableLegacyFormField();
            $a = submit();
            $b = submit('Salva e aggiungi', 'upload-add');
            $forms['senzaBuffer'] = '<form method="post">'.$a.$b.'</form>';
            $GLOBALS['EXTRA'] = ['forms' => $forms];
            // POST con il token letto dalla pagina resa.
            preg_match('/name="_csrf" value="([0-9a-f]+)"/', $forms['submitAdd'], $t);
            $_POST = ['_csrf' => $t[1] ?? '', 'upload' => '1', 'name' => 'nuovo'];
            csrfPagesInclude('/../../app/html/backend/index.php', $legacyVars);
            break;

        case 'legacy-index':
            // $argv[4]: 'upload', 'upload-add' o 'modify' (upload con $_POST['modify']).
            $button = ($GLOBALS['argv'][4] ?? '') ?: 'upload';
            $_POST = $csrf + ['name' => 'nuovo', ($button === 'modify' ? 'upload' : $button) => '1'] + ($button === 'modify' ? ['modify' => '5'] : []);
            csrfPagesInclude('/../../app/html/backend/index.php', [
                'NAME' => (object) ['table' => 'csrf_legacy', 'database' => '', 'folder' => 'legacy'],
                'TEXT' => (object) ['titleS' => 'voce'],
                'PATH' => (object) ['backend' => '/backend'],
                'PAGE' => (object) ['redirect' => '/backend/legacy/list.php', 'redirectBase64' => ''],
                'PAGE_TABLE' => ['name' => []],
            ]);
            break;

        case 'quick-create':
            $_POST = $csrf + ['resource' => 'inesistente'];
            ob_start();
            csrfPagesInclude('/../../app/http/backend/resource/quick-create.php', ['USER' => (object) ['authority' => ['admin']]]);
            break;
    }

    $GLOBALS['EXITED'] = false;
}

/** Include il handler in uno scope di funzione, come fa il dispatcher. */
function csrfPagesInclude(string $relative, array $vars): void
{
    extract($vars + ['ROOT_APP' => $GLOBALS['TMP_APP'], 'ROOT' => $GLOBALS['TMP_APP']]);
    include __DIR__.$relative;
}

final class CsrfPagesReached extends RuntimeException
{
}

function csrfPagesStubs(): void
{
    eval(<<<'STUBS'
        function __r(string $name, array $params = []): string { return '/'.$name; }
        function __t(string $key, array $params = []): string { return $key; }
        function sqlSelect($table, $cond = null, $limit = null) { return (object) ['row' => ['id' => 7, 'password' => 'hash'], 'exists' => true]; }
        function sqlInsert($table, $values) { $GLOBALS['W'][] = "insert {$table}"; return (object) ['success' => false]; }
        function sqlModify($table, $values, $col = null, $id = null) { $GLOBALS['W'][] = "modify {$table}"; return (object) ['success' => false]; }
        function user($post, $id = null) { $GLOBALS['W'][] = 'user()'; $GLOBALS['ALERT'] = 900; return (object) ['values' => $post, 'written' => false]; }
        function sanitize($v) { return $v; }
        function checkPassword($a, $b) { return true; }
        function checkUserPassword($a, $b) { return true; }
        function hashPassword($v) { return 'h'; }
        function formToArray($table, $post, $fields, $old = null) { return ['name' => $post['name'] ?? '']; }
    STUBS);
}

final class CsrfPagesModel extends \Wonder\App\Model
{
    public static string $table = 'csrf_pages';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }

    public static function find(string|array|null $condition = null, string|int|null $limit = null, ?string $order = null, ?string $orderDirection = null, string|array $columns = '*'): mixed
    {
        return ['id' => 5, 'name' => 'vecchio'];
    }
}

final class CsrfPagesResource extends \Wonder\App\Resource
{
    public static string $model = CsrfPagesModel::class;

    public static function isReadonly(): bool { return false; }

    public static function slug(): string { return 'csrf-pages'; }

    public static function hydrateRepeaterFormValues(array $values, int|string|null $parentId = null, ?array $post = null, ?array $files = null): array
    {
        $GLOBALS['RENDERED'] = true;

        throw new CsrfPagesReached('render');
    }

    public static function findStoreExistingValues(array $requestValues, string $context = 'backend'): ?array
    {
        $GLOBALS['W'][] = 'store';

        throw new CsrfPagesReached('store');
    }

    public static function mutateRequestValues(array $values, string $action, string $context = 'backend', ?array $oldValues = null): array
    {
        $GLOBALS['W'][] = $action;

        throw new CsrfPagesReached($action);
    }

    public static function deleteRecord(int|string $id): object
    {
        $GLOBALS['W'][] = 'delete';

        throw new CsrfPagesReached('delete');
    }

    public static function submitFormPage(array $values): string
    {
        $GLOBALS['W'][] = 'submit';

        throw new CsrfPagesReached('submit');
    }
}

final class CsrfPagesUserResource
{
    public static function sendsWelcomeMail(): bool { return false; }
    public static function selectedAuthority(array $values): string { return ''; }
    public static function label(): string { return 'utente'; }
    public static function managedArea(): string { return 'backend'; }
    public static function availableAuthorities(): array { return []; }
    public static function existingUserOptions(): array { return []; }
    public static function canCreateFromExisting(): bool { return false; }
    public static function getInput(string $key): object { return new class { public function get(string $k): array { return []; } }; }
}

if (($argv[1] ?? '') === '--scenario') {
    csrfPagesScenario((string) $argv[2], (string) $argv[3]);
}
