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
function sqlCount($table, $query = null, $column = '*', $distinct = false): int
{
    $GLOBALS['tLastQuery'] = (string) $query;
    $pattern = "/^`event` IN \\((.+)\\) AND `creation` >= DATE_SUB\\(NOW\\(\\), INTERVAL (\\d+) SECOND\\) AND (`ip`|JSON_UNQUOTE\\(JSON_EXTRACT\\(`meta`, '\\$\\.identifier'\\)\\)) = '(.*)'$/";
    if ($table !== 'auth_log' || preg_match($pattern, (string) $query, $m) !== 1) {
        throw new RuntimeException('Condizione inattesa: '.$query);
    }
    preg_match_all("/'([^']*)'/", $m[1], $events);
    $since = $GLOBALS['tNow'] - (int) $m[2];
    $count = 0;
    foreach ($GLOBALS['tLog'] as $row) {
        if (!in_array($row['event'], $events[1], true) || $row['creation'] < $since) {
            continue;
        }
        $meta = json_decode((string) $row['meta'], true) ?: [];
        $value = $m[3] === '`ip`' ? $row['ip'] : ($meta['identifier'] ?? null);
        if ($value === stripslashes($m[4])) {
            $count++;
        }
    }
    return $count;
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
    $blocked = LoginThrottle::check('recovery', 'nuovo@example.test', '203.0.113.1') === false && LoginThrottle::retryAfter() === 3600;
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

summary();
