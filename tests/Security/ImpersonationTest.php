<?php
/** php tests/Security/ImpersonationTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Auth\Impersonation;

// Output in buffer: `session_id()` si può cambiare solo prima degli header.
ob_start();

// Stub sql: token monouso e audit in memoria, nessuna connessione reale.
$GLOBALS['tTokens'] = [];
$GLOBALS['tAudits'] = [];
$GLOBALS['tUsers'] = [];

function infoUser($value, $filter = 'id'): object
{
    return $GLOBALS['tUsers'][(int) $value] ?? (object) ['exists' => false];
}
function sqlInsert($table, $values): object
{
    if ($table === 'auth_one_time_tokens') {
        $id = count($GLOBALS['tTokens']) + 1;
        $GLOBALS['tTokens'][$id] = ['id' => $id, 'consumed_at' => null, 'revoked_at' => null] + $values;

        return (object) ['success' => true, 'insert_id' => $id];
    }

    if ($table === 'auth_impersonation_audits') {
        $GLOBALS['tAudits'][] = $values;
    }

    return (object) ['success' => true, 'insert_id' => 0];
}
function sqlSelect($table, $cond = null, $limit = null): object
{
    $rows = array_values(array_filter($GLOBALS['tTokens'], static function (array $row) use ($cond): bool {
        foreach ((array) $cond as $k => $v) {
            if ((string) ($row[$k] ?? '') !== (string) $v) {
                return false;
            }
        }

        return true;
    }));

    if ($rows === []) {
        return (object) ['exists' => false, 'row' => []];
    }

    return (object) ['exists' => true, 'row' => $limit === 1 || count($rows) === 1 ? $rows[0] : $rows];
}
function sqlModify($table, $values, $column = null, $value = null): object
{
    if ($table === 'auth_one_time_tokens' && isset($GLOBALS['tTokens'][(int) $value])) {
        $GLOBALS['tTokens'][(int) $value] = $values + $GLOBALS['tTokens'][(int) $value];
    }

    return (object) ['success' => true];
}

// `OneTimeToken::markConsumed()` usa mysqli: connessione finta già in cache.
final class FakeStmt
{
    public int $affected_rows = 0;
    private int $id = 0;

    public function bind_param(string $types, mixed &...$vars): bool
    {
        $this->id = (int) $vars[1];

        return true;
    }

    public function execute(): bool
    {
        $row = $GLOBALS['tTokens'][$this->id] ?? null;
        if ($row !== null && $row['consumed_at'] === null && $row['revoked_at'] === null) {
            $GLOBALS['tTokens'][$this->id]['consumed_at'] = date('Y-m-d H:i:s');
            $this->affected_rows = 1;
        }

        return true;
    }

    public function close(): bool
    {
        return true;
    }
}

final class FakeMysqli extends mysqli
{
    public function __construct()
    {
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query)
    {
        return new FakeStmt();
    }
}

(function (): void {
    $set = static function (string $class, string $prop, mixed $value): void {
        $p = new ReflectionProperty($class, $prop);
        $p->setValue(null, $value);
    };
    \Wonder\App\Credentials::database();
    $set(\Wonder\App\Credentials::class, 'DB', (object) [
        'hostname' => 'stub.invalid', 'username' => 'u', 'password' => 'p',
        'database' => ['main' => 'stubdb'], 'charset' => 'latin1', 'collation' => '',
    ]);
    foreach (['hostname' => 'stub.invalid', 'username' => 'u', 'password' => 'p', 'database' => 'stubdb'] as $k => $v) {
        $set(\Wonder\Sql\Connection::class, $k, $v);
    }
    $set(\Wonder\Sql\Connection::class, 'connection', [
        base64_encode('stub.invalid | u | p | stubdb') => new FakeMysqli(),
    ]);
})();

$active = static fn (array $areas, array $authority = []): object => (object) [
    'exists' => true, 'deleted' => false, 'active' => true, 'area' => $areas, 'authority' => $authority,
];

$reset = static function () use ($active): void {
    $GLOBALS['tTokens'] = [];
    $GLOBALS['tAudits'] = [];
    $GLOBALS['tUsers'] = [
        1 => $active(['backend'], ['administrator']),
        2 => $active(['frontend'], ['client']),
    ];
    $_SESSION = ['user_id' => 1];
    session_id('sessione-attore-'.str_repeat('a', 10));
};

$events = static fn (): array => array_column($GLOBALS['tAudits'], 'event');
$service = new Impersonation(['administrator'], 120);

check('issue: il token porta lo sha256 della sessione, non l\'id in chiaro', function () use ($reset, $service) {
    $reset();
    $service->issue(1, 2, '/account/', '/backend/', '/stop/');
    $meta = json_decode((string) ($GLOBALS['tTokens'][1]['metadata_json'] ?? ''), true);

    return ($meta['actor_session'] ?? null) === hash('sha256', session_id())
        && !str_contains((string) $GLOBALS['tTokens'][1]['metadata_json'], session_id());
});

check('start nella stessa sessione → riesce, attore e soggetto separati', function () use ($reset, $service) {
    $reset();
    $issued = $service->issue(1, 2, '/account/', '/backend/', '/stop/');
    $started = $service->start($issued->token);
    $current = $service->current();

    return ($started->success ?? false) && ($current->actor_user_id ?? 0) === 1
        && ($current->subject_user_id ?? 0) === 2 && ($_SESSION['user_id'] ?? 0) === 2;
});

check('start da un\'altra sessione → rifiutato con audit start_denied', function () use ($reset, $service, $events) {
    $reset();
    $issued = $service->issue(1, 2, '/account/', '/backend/', '/stop/');
    session_id('sessione-altrui-'.str_repeat('b', 10));
    $started = $service->start($issued->token);

    return ($started->success ?? true) === false && ($started->reason ?? '') === 'impersonation_session_mismatch'
        && in_array('start_denied', $events(), true) && ($_SESSION['user_id'] ?? 0) === 1
        && $service->current() === null;
});

check('token rifiutato non riutilizzabile nemmeno dalla sessione giusta', function () use ($reset, $service) {
    $reset();
    $own = session_id();
    $issued = $service->issue(1, 2);
    session_id('sessione-altrui-'.str_repeat('b', 10));
    $service->start($issued->token);
    session_id($own);

    return ($service->start($issued->token)->success ?? true) === false;
});

check('token senza actor_session → rifiutato', function () use ($reset, $service) {
    $reset();
    $issued = $service->issue(1, 2);
    $GLOBALS['tTokens'][1]['metadata_json'] = json_encode(['return_url' => '/backend/']);

    return ($service->start($issued->token)->reason ?? '') === 'impersonation_session_mismatch';
});

check('start salva expires_at = avvio + 1800 s di default', function () use ($reset, $service) {
    $reset();
    $before = time();
    $service->start($service->issue(1, 2)->token);
    $state = $_SESSION['auth_impersonation'] ?? [];

    return ($state['expires_at'] ?? 0) >= $before + 1800 && ($state['expires_at'] ?? 0) <= time() + 1800;
});

check('durata massima dal costruttore', function () use ($reset) {
    $reset();
    $service = new Impersonation(['administrator'], 120, 600);
    $service->start($service->issue(1, 2)->token);

    return (($_SESSION['auth_impersonation']['expires_at'] ?? 0) - time()) <= 600
        && (($_SESSION['auth_impersonation']['expires_at'] ?? 0) - time()) >= 599;
});

check('prima della scadenza current() resta attivo senza audit di fine', function () use ($reset, $service, $events) {
    $reset();
    $service->start($service->issue(1, 2)->token);

    return $service->current() !== null && !in_array('expired', $events(), true);
});

check('dopo expires_at current() è null e l\'attore torna se stesso', function () use ($reset, $service, $events) {
    $reset();
    $service->start($service->issue(1, 2)->token);
    $_SESSION['auth_impersonation']['expires_at'] = time() - 1;

    return $service->current() === null && ($_SESSION['user_id'] ?? 0) === 1
        && !isset($_SESSION['auth_impersonation']) && in_array('expired', $events(), true);
});

check('sessione avviata senza expires_at: scade da started_at + durata', function () use ($reset, $service) {
    $reset();
    $service->start($service->issue(1, 2)->token);
    unset($_SESSION['auth_impersonation']['expires_at']);
    $_SESSION['auth_impersonation']['started_at'] = time() - 1801;

    return $service->current() === null && ($_SESSION['user_id'] ?? 0) === 1;
});

check('scadenza con attore non più attivo → sessione chiusa', function () use ($reset, $service) {
    $reset();
    $service->start($service->issue(1, 2)->token);
    $GLOBALS['tUsers'][1]->active = false;
    $_SESSION['auth_impersonation']['expires_at'] = time() - 1;

    return $service->current() === null && !isset($_SESSION['user_id']);
});

check('stop ripristina l\'attore e la destinazione backend (invariato)', function () use ($reset, $service, $events) {
    $reset();
    $service->start($service->issue(1, 2, '/account/', '/backend/', '/stop/')->token);
    $stopped = $service->stop($service->csrfToken('stop'));

    return ($stopped->success ?? false) && ($_SESSION['user_id'] ?? 0) === 1 && ($stopped->return_url ?? '') === '/backend/'
        && $service->current() === null && in_array('stopped', $events(), true);
});

check('stop senza CSRF valido → rifiutato, impersonificazione attiva', function () use ($reset, $service) {
    $reset();
    $service->start($service->issue(1, 2)->token);

    return ($service->stop('x')->success ?? true) === false && $service->current() !== null;
});

// R2: scadenza applicata dal bootstrap di sessione, senza overlay né current().
$bootstrapSession = static function (): void {
    $_SESSION['system_cache'] = $_SESSION['system_cache'] ?? ['country' => 'IT', 'geo' => []];
    require __DIR__.'/../../app/config/app/session.php';
};

check('bootstrap di sessione (API, layout custom): impersonificazione scaduta → attore ripristinato e audit expired', function () use ($reset, $service, $events, $bootstrapSession) {
    $reset();
    $service->start($service->issue(1, 2)->token);
    $_SESSION['auth_impersonation']['expires_at'] = time() - 1;
    $bootstrapSession();

    return ($_SESSION['user_id'] ?? 0) === 1 && !isset($_SESSION['auth_impersonation'])
        && in_array('expired', $events(), true);
});

check('bootstrap di sessione: impersonificazione valida resta attiva, nessun audit', function () use ($reset, $service, $events, $bootstrapSession) {
    $reset();
    $service->start($service->issue(1, 2)->token);
    $bootstrapSession();

    return ($_SESSION['user_id'] ?? 0) === 2 && isset($_SESSION['auth_impersonation'])
        && !in_array('expired', $events(), true);
});

check('bootstrap di sessione: scaduta con attore disattivato → sessione anonima', function () use ($reset, $service, $bootstrapSession) {
    $reset();
    $service->start($service->issue(1, 2)->token);
    $GLOBALS['tUsers'][1]->active = false;
    $_SESSION['auth_impersonation']['expires_at'] = time() - 1;
    $bootstrapSession();

    return array_key_exists('user_id', $_SESSION) && $_SESSION['user_id'] === null && !isset($_SESSION['auth_impersonation']);
});

check('bootstrap di sessione senza impersonificazione: utente invariato', function () use ($reset, $events, $bootstrapSession) {
    $reset();
    $bootstrapSession();

    return ($_SESSION['user_id'] ?? 0) === 1 && $events() === [];
});

check('audit: con APP_TRUST_PROXY l\'ip è quello di ClientIp, non REMOTE_ADDR', function () use ($reset, $service) {
    $reset();
    $saved = [$_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null, $_ENV['APP_TRUST_PROXY'] ?? null];
    $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 203.0.113.9';
    $_ENV['APP_TRUST_PROXY'] = 'true';
    $service->issue(1, 2);
    $proxied = $GLOBALS['tAudits'][0]['ip'] ?? null;
    $_ENV['APP_TRUST_PROXY'] = 'false';
    $GLOBALS['tAudits'] = [];
    $service->issue(1, 2);
    $direct = $GLOBALS['tAudits'][0]['ip'] ?? null;
    [$r, $x, $e] = $saved;
    if ($r === null) { unset($_SERVER['REMOTE_ADDR']); } else { $_SERVER['REMOTE_ADDR'] = $r; }
    if ($x === null) { unset($_SERVER['HTTP_X_FORWARDED_FOR']); } else { $_SERVER['HTTP_X_FORWARDED_FOR'] = $x; }
    if ($e === null) { unset($_ENV['APP_TRUST_PROXY']); } else { $_ENV['APP_TRUST_PROXY'] = $e; }

    return $proxied === '203.0.113.9' && $direct === '10.0.0.1';
});

summary();
