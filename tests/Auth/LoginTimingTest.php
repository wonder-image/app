<?php
/** php tests/Auth/LoginTimingTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

// Login senza utente o senza password: stessa verifica (e stesso costo) di una password errata.
// Il seam è checkUserPassword(): qui conta le chiamate e l'hash ricevuto.
if (($argv[1] ?? '') === '--real') {
    require __DIR__.'/../../app/function/string/password.php';
    $dummy = dummyPasswordHash();
    $real = hashPassword('Qualunque-1');
    echo json_encode([
        'info_equal' => password_get_info($dummy) == password_get_info($real),
        'algo' => password_get_info($dummy)['algoName'],
        'needs_rehash' => passwordNeedsRehash($dummy),
        'cached' => dummyPasswordHash() === $dummy,
        'rejects' => !checkUserPassword('Qualunque-1', $dummy) && !checkUserPassword('', $dummy),
    ]);
    exit;
}

require __DIR__.'/../harness.php';

$GLOBALS['VERIFY'] = [];
$GLOBALS['MODE'] = 'none';

// Stub in eval: dichiarati in cima al file verrebbero creati anche nel processo --real.
eval(<<<'STUBS'
function sanitize($value) { return trim((string) $value); }
function infoUser($value, $filter = 'id'): object
{
    return match ($GLOBALS['MODE']) {
        'none' => (object) ['exists' => false],
        'partial' => (object) ['exists' => true, 'id' => 7, 'password' => ''],
        default => (object) ['exists' => true, 'id' => 7, 'password' => 'HASH-UTENTE'],
    };
}
function sqlInsert($table, $values) { return (object) ['success' => true, 'insert_id' => 1]; }
function sqlBind($sql, $params) { return (string) $sql; }
function checkUserPassword($password, $hashPassword): bool
{
    $GLOBALS['VERIFY'][] = (string) $hashPassword;

    return false;
}
function dummyPasswordHash(): string { return 'HASH-FITTIZIO'; }
STUBS);

require __DIR__.'/../../app/function/user/auth.php';

\Wonder\Auth\LoginThrottle::useCounter(static fn (): int => 0);
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

$login = static function (string $mode): array {
    global $ALERT;
    $GLOBALS['MODE'] = $mode;
    $GLOBALS['VERIFY'] = [];
    $ALERT = null;
    $ok = authenticateUserLogin('mario@example.com', 'Sbagliata-1', 'frontend', null, false);

    return [$ok, (int) $ALERT, $GLOBALS['VERIFY']];
};

$wrong = $login('local');
check('password errata → una verifica sull\'hash dell\'utente, alert 925', fn () => $wrong === [false, 925, ['HASH-UTENTE']]);
check('utente inesistente → una verifica sull\'hash fittizio, alert 925', fn () => $login('none') === [false, 925, ['HASH-FITTIZIO']]);
check('registrazione senza password → una verifica sull\'hash fittizio, alert 925', fn () => $login('partial') === [false, 925, ['HASH-FITTIZIO']]);

$real = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --real 2>&1'), true);
check('hash fittizio: stesso algoritmo e stessi parametri di hashPassword()', fn () => ($real['info_equal'] ?? false) === true && ($real['needs_rehash'] ?? true) === false);
check('hash fittizio: stabile fra le chiamate', fn () => ($real['cached'] ?? false) === true);
check('hash fittizio: nessuna password lo verifica', fn () => ($real['rejects'] ?? false) === true);

summary();
