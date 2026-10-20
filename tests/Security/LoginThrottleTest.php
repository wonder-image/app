<?php
/** php tests/Security/LoginThrottleTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\App\Environment;
use Wonder\Auth\LoginThrottle;

// Stub sql: auth_log in memoria, orologio controllato dal test.
$GLOBALS['tLog'] = [];
$GLOBALS['tNow'] = 1_000_000;
$GLOBALS['tInfoUserCalls'] = 0;
$GLOBALS['tUser'] = null;

function infoUser($value, $filter = 'id'): object
{
    $GLOBALS['tInfoUserCalls']++;
    return $GLOBALS['tUser'] ?? (object) ['exists' => false];
}
function sqlModify($table, $values, $column, $value): object
{
    return (object) ['success' => true];
}
function sqlInsert($table, $values): object
{
    if ($table === 'auth_log') {
        $values['creation'] = $GLOBALS['tNow'];
        $GLOBALS['tLog'][] = $values;
    }
    return (object) ['success' => true, 'insert_id' => count($GLOBALS['tLog'])];
}
function sqlBind(string $sql, array $params): string
{
    foreach ($params as $param) {
        $sql = preg_replace('/\?/', "'".addslashes((string) $param)."'", $sql, 1);
    }
    return $sql;
}
// Interpreta la condizione generata da LoginThrottle::condition() sul log in memoria.
function tMatchingRows($table, $query): array
{
    $GLOBALS['tLastQuery'] = (string) $query;
    $pattern = "/^`event` IN \\((.+)\\) AND `creation` >= DATE_SUB\\(NOW\\(\\), INTERVAL (\\d+) SECOND\\) AND (`ip`|JSON_UNQUOTE\\(JSON_EXTRACT\\(`meta`, '\\$\\.identifier'\\)\\)) = '(.*)'$/";
    if ($table !== 'auth_log' || preg_match($pattern, (string) $query, $m) !== 1) {
        throw new RuntimeException('Condizione inattesa: '.$query);
    }
    preg_match_all("/'([^']*)'/", $m[1], $events);
    $since = $GLOBALS['tNow'] - (int) $m[2];
    $rows = [];
    foreach ($GLOBALS['tLog'] as $row) {
        if (!in_array($row['event'], $events[1], true) || $row['creation'] < $since) {
            continue;
        }
        $meta = json_decode((string) $row['meta'], true) ?: [];
        $value = $m[3] === '`ip`' ? $row['ip'] : ($meta['identifier'] ?? null);
        if ($value === stripslashes($m[4])) {
            $rows[] = $row;
        }
    }
    return $rows;
}
function sqlCount($table, $query = null, $column = '*', $distinct = false): int
{
    return count(tMatchingRows($table, $query));
}
// Solo la lettura dell'età del tentativo più vecchio usata per Retry-After.
function sqlSelect($table, $condition = null, $limit = null, $order = null, $orderDirection = null, $attributes = '*'): object
{
    if (!str_contains((string) $attributes, 'MIN(`creation`)')) {
        throw new RuntimeException('Select inattesa: '.$attributes);
    }
    $rows = tMatchingRows($table, $condition);
    $age = $rows === [] ? null : $GLOBALS['tNow'] - min(array_column($rows, 'creation'));
    return (object) ['success' => true, 'exists' => true, 'row' => ['age' => $age]];
}
function isUserEmailVerified($userId): bool
{
    return true;
}

require __DIR__.'/../../app/config/array/characters.php';
require __DIR__.'/../../app/function/string/common.php';
require __DIR__.'/../../app/function/string/sanitize.php';
require __DIR__.'/../../app/function/string/password.php';
require __DIR__.'/../../app/function/user/auth.php';

$_ENV['APP_ENV'] = 'production';
Environment::reset();

$reset = static function (): void {
    $GLOBALS['tLog'] = [];
    $GLOBALS['tNow'] = 1_000_000;
    $GLOBALS['tInfoUserCalls'] = 0;
    $GLOBALS['tUser'] = null;
    $GLOBALS['ALERT'] = null;
    $_SERVER['REMOTE_ADDR'] = '203.0.113.1';
    $_SESSION = [];
};
$fail = static function (string $email, string $ip = '203.0.113.1'): bool {
    $_SERVER['REMOTE_ADDR'] = $ip;
    return authenticateUserLogin($email, 'sbagliata', 'frontend', null, false);
};
$events = static fn (): array => array_column($GLOBALS['tLog'], 'event');

check('6° tentativo per la stessa email: 930, nessuna ricerca utente né verifica password', function () use ($reset, $fail, $events) {
    $reset();
    for ($i = 0; $i < 5; $i++) {
        $fail('Mario@Example.test');
    }
    $callsBefore = $GLOBALS['tInfoUserCalls'];
    $ok = $fail('mario@example.test ');
    return $ok === false
        && (int) $GLOBALS['ALERT'] === LoginThrottle::ALERT
        && $GLOBALS['tInfoUserCalls'] === $callsBefore
        && LoginThrottle::retryAfter() === 900
        && end($GLOBALS['tLog'])['event'] === 'login_throttled'
        && count(array_keys($events(), 'login_failed', true)) === 5;
});

check('stessa email da IP diverso: bloccata comunque', function () use ($reset, $fail) {
    $reset();
    for ($i = 0; $i < 5; $i++) {
        $fail('mario@example.test', '203.0.113.'.($i + 10));
    }
    return $fail('mario@example.test', '198.51.100.7') === false
        && (int) $GLOBALS['ALERT'] === LoginThrottle::ALERT;
});

check('email diverse dallo stesso IP: passano fino a 20, la 21ª è bloccata', function () use ($reset, $fail) {
    $reset();
    for ($i = 0; $i < 19; $i++) {
        $fail("u{$i}@example.test");
    }
    $fail('u19@example.test');
    $twentieth = (int) $GLOBALS['ALERT'];
    $fail('nuova@example.test');
    return $twentieth === 925 && (int) $GLOBALS['ALERT'] === LoginThrottle::ALERT;
});

check('dopo la finestra di 15 minuti il conteggio riparte', function () use ($reset, $fail) {
    $reset();
    for ($i = 0; $i < 5; $i++) {
        $fail('mario@example.test');
    }
    $GLOBALS['tNow'] += 901;
    $fail('mario@example.test');
    return (int) $GLOBALS['ALERT'] === 925;
});

check('login riuscito sotto il limite non è toccato dal throttle', function () use ($reset, $fail) {
    $reset();
    for ($i = 0; $i < 4; $i++) {
        $fail('mario@example.test');
    }
    $GLOBALS['tUser'] = (object) [
        'exists' => true, 'id' => 7, 'password' => hashPassword('Giusta-1'), 'deleted' => false,
        'active' => true, 'area' => ['frontend'], 'authority' => [], 'email' => 'mario@example.test',
    ];
    return authenticateUserLogin('mario@example.test', 'Giusta-1', 'frontend', null, false) === true;
});

check('login_failed salva meta.identifier normalizzato, anche per utente inesistente', function () use ($reset, $fail) {
    $reset();
    $fail('  Mario@Example.TEST ');
    $meta = json_decode((string) $GLOBALS['tLog'][0]['meta'], true);
    return $GLOBALS['tLog'][0]['event'] === 'login_failed' && $meta['identifier'] === 'mario@example.test';
});

check('in locale i limiti sono x10', function () use ($reset, $fail) {
    $reset();
    $_ENV['APP_ENV'] = 'local';
    Environment::reset();
    for ($i = 0; $i < 6; $i++) {
        $fail('mario@example.test');
    }
    $sixth = (int) $GLOBALS['ALERT'];
    for ($i = 0; $i < 44; $i++) {
        $fail('mario@example.test');
    }
    $fiftyFirst = (bool) LoginThrottle::check('login', 'mario@example.test', '203.0.113.1');
    $_ENV['APP_ENV'] = 'production';
    Environment::reset();
    return $sixth === 925 && $fiftyFirst === false && LoginThrottle::limit('login', 'identifier') === [5, 900];
});

check('recovery: 3 richieste per identificatore, la 4ª è bloccata (tutti gli eventi recovery)', function () use ($reset) {
    $reset();
    $log = static fn (string $event) => Wonder\Auth\AuthLog::write($event, null, 'frontend', true, ['identifier' => 'mario@example.test']);
    $log('recovery_request');
    $log('password_recovery');
    $before = LoginThrottle::check('recovery', 'mario@example.test', '198.51.100.9');
    $log('recovery_unknown');
    return $before === true
        && LoginThrottle::check('recovery', 'MARIO@example.test', '198.51.100.9') === false
        && LoginThrottle::retryAfter() === 900
        && LoginThrottle::check('recovery', 'altro@example.test', '198.51.100.9') === true;
});

check('recovery: 10 richieste per IP in un\'ora, l\'11ª è bloccata', function () use ($reset) {
    $reset();
    for ($i = 0; $i < 10; $i++) {
        Wonder\Auth\AuthLog::write('recovery_request', null, 'frontend', false, ['identifier' => "u{$i}@example.test"]);
        $GLOBALS['tNow'] += 300;
    }
    // Il più vecchio ha 3000 secondi: ne mancano 600 all'uscita dalla finestra.
    $blocked = LoginThrottle::check('recovery', 'nuovo@example.test', '203.0.113.1') === false && LoginThrottle::retryAfter() === 600;
    $GLOBALS['tNow'] += 3600;
    return $blocked && LoginThrottle::check('recovery', 'nuovo@example.test', '203.0.113.1') === true;
});

check('gli eventi *_throttled non allungano il blocco', function () use ($reset, $fail) {
    $reset();
    for ($i = 0; $i < 5; $i++) {
        $fail('mario@example.test');
    }
    for ($i = 0; $i < 10; $i++) {
        $fail('mario@example.test');
    }
    $GLOBALS['tNow'] += 901;
    $fail('mario@example.test');
    return (int) $GLOBALS['ALERT'] === 925;
});

check('condizione SQL: valori escapati, finestra sull\'orologio del DB', function () {
    $sql = LoginThrottle::condition(['login_failed'], 'identifier', "o'hara@example.test", 900);
    return str_contains($sql, "DATE_SUB(NOW(), INTERVAL 900 SECOND)")
        && str_contains($sql, "JSON_EXTRACT(`meta`, '$.identifier')")
        && str_contains($sql, "'o\\'hara@example.test'");
});

check('bucket sconosciuto: eccezione', function () {
    try {
        LoginThrottle::check('altro', 'x', 'y');
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
});

check('Retry-After: secondi mancanti all\'uscita del tentativo più vecchio, non la finestra intera', function () use ($reset, $fail) {
    $reset();
    for ($i = 0; $i < 5; $i++) {
        $fail('mario@example.test');
        $GLOBALS['tNow'] += 60;
    }
    // Il primo tentativo ha 300 secondi: ne mancano 600.
    $blocked = LoginThrottle::check('login', 'mario@example.test', '198.51.100.1') === false;
    return $blocked && LoginThrottle::retryAfter() === 600;
});

check('Retry-After: età non leggibile → finestra intera', function () use ($reset, $fail) {
    $reset();
    for ($i = 0; $i < 5; $i++) {
        $fail('mario@example.test');
    }
    LoginThrottle::useOldestAge(fn (string $where): ?int => null);
    $blocked = LoginThrottle::check('login', 'mario@example.test', '198.51.100.1') === false;
    $retry = LoginThrottle::retryAfter();
    LoginThrottle::useOldestAge(null);
    return $blocked && $retry === 900;
});

// IP del client (R1): helper unico per throttle e auth_log.
$proxy = static function (?string $flag): void {
    unset($_ENV['APP_TRUST_PROXY'], $_SERVER['APP_TRUST_PROXY']);
    putenv('APP_TRUST_PROXY');
    if ($flag !== null) {
        $_ENV['APP_TRUST_PROXY'] = $flag;
    }
};
$server = ['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 198.51.100.20, 203.0.113.77'];

check('ClientIp senza APP_TRUST_PROXY: REMOTE_ADDR, X-Forwarded-For ignorato', function () use ($proxy, $server) {
    $proxy(null);
    $off = Wonder\Http\ClientIp::resolve($server);
    $proxy('false');
    $false = Wonder\Http\ClientIp::resolve($server);
    $proxy(null);
    return $off === '10.0.0.5' && $false === '10.0.0.5';
});

check('ClientIp con APP_TRUST_PROXY e XFF a più voci: l\'ultima, aggiunta dal proxy', function () use ($proxy, $server) {
    $proxy('true');
    $last = Wonder\Http\ClientIp::resolve($server);
    $single = Wonder\Http\ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '2001:db8::1']);
    $proxy(null);
    return $last === '203.0.113.77' && $single === '2001:db8::1';
});

check('ClientIp con APP_TRUST_PROXY: XFF assente, vuoto o non valido → REMOTE_ADDR', function () use ($proxy) {
    $proxy('true');
    $none = Wonder\Http\ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5']);
    $empty = Wonder\Http\ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, ']);
    $bad = Wonder\Http\ClientIp::resolve(['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4, unknown']);
    $proxy(null);
    return $none === '10.0.0.5' && $empty === '10.0.0.5' && $bad === '10.0.0.5';
});

check('login dietro proxy con APP_TRUST_PROXY: bucket IP per client, non per proxy; voce falsificata dal client ignorata', function () use ($reset, $proxy) {
    $reset();
    $proxy('true');
    $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
    // 20 utenti diversi dallo stesso client reale, che falsifica la prima voce.
    for ($i = 0; $i < 20; $i++) {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = "9.9.9.{$i}, 203.0.113.50";
        authenticateUserLogin("u{$i}@example.test", 'sbagliata', 'frontend', null, false);
    }
    $sameClient = authenticateUserLogin('nuovo@example.test', 'sbagliata', 'frontend', null, false) === false
        && (int) $GLOBALS['ALERT'] === LoginThrottle::ALERT;
    // Un altro client dietro lo stesso proxy non è bloccato.
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.51';
    authenticateUserLogin('altro@example.test', 'sbagliata', 'frontend', null, false);
    $otherClient = (int) $GLOBALS['ALERT'] === 925;
    $loggedIp = $GLOBALS['tLog'][0]['ip'];
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    $proxy(null);
    return $sameClient && $otherClient && $loggedIp === '203.0.113.50';
});

check('login dietro proxy senza APP_TRUST_PROXY: tutti condividono REMOTE_ADDR (comportamento documentato)', function () use ($reset, $proxy) {
    $reset();
    $proxy(null);
    $_SERVER['REMOTE_ADDR'] = '10.0.0.5';
    for ($i = 0; $i < 20; $i++) {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = "203.0.113.{$i}";
        authenticateUserLogin("u{$i}@example.test", 'sbagliata', 'frontend', null, false);
    }
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.99';
    authenticateUserLogin('nuovo@example.test', 'sbagliata', 'frontend', null, false);
    unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    return (int) $GLOBALS['ALERT'] === LoginThrottle::ALERT && $GLOBALS['tLog'][0]['ip'] === '10.0.0.5';
});

summary();
