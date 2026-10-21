<?php
/** php tests/Security/PasswordHashTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

// Stub sql: utente in memoria, log e update catturati.
$GLOBALS['pwUser'] = null;
$GLOBALS['pwModified'] = [];
$GLOBALS['pwLog'] = [];

function infoUser($value, $filter = 'id'): object
{
    return $GLOBALS['pwUser'] ?? (object) ['exists' => false];
}
function sqlModify($table, $values, $column, $value): object
{
    $GLOBALS['pwModified'][] = compact('table', 'values', 'column', 'value');
    if ($table === 'user' && isset($values['password']) && $GLOBALS['pwUser'] !== null) {
        $GLOBALS['pwUser']->password = $values['password'];
    }
    return (object) ['success' => true, 'query' => ''];
}
function sqlInsert($table, $values): object
{
    if ($table === 'auth_log') {
        $GLOBALS['pwLog'][] = $values;
    }
    return (object) ['success' => true, 'insert_id' => 1];
}
function sqlCount($table, $query = null, $column = '*', $distinct = false): int
{
    return 0;
}
function sqlBind(string $sql, array $params): string
{
    return $sql;
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

$legacyHash = static fn (string $plain): string => (new Wonder\Password(8, true))->HashPassword($plain);
$makeUser = static fn (string $hash): object => (object) [
    'exists' => true,
    'id' => 42,
    'password' => $hash,
    'deleted' => false,
    'active' => true,
    'area' => ['backend'],
    'authority' => [],
    'email' => 'mario@example.test',
];
$reset = static function (?object $user = null): void {
    $GLOBALS['pwUser'] = $user;
    $GLOBALS['pwModified'] = [];
    $GLOBALS['pwLog'] = [];
    $GLOBALS['ALERT'] = null;
    $_SESSION = [];
};
$events = static fn (): array => array_column($GLOBALS['pwLog'], 'event');

check('hash nuovo: argon2id (o bcrypt se argon2id manca)', function () {
    $hash = hashPassword('Segreta-123');
    $expected = defined('PASSWORD_ARGON2ID') ? '$argon2id$' : '$2y$';
    return str_starts_with($hash, $expected) && checkPassword('Segreta-123', $hash);
});

check('hash phpass $P$ della classe legacy resta verificabile', function () use ($legacyHash) {
    $hash = $legacyHash('Segreta-123');
    return str_starts_with($hash, '$P$') && checkPassword('Segreta-123', $hash);
});

check('password errata rifiutata su entrambi i formati', function () use ($legacyHash) {
    return !checkPassword('sbagliata', hashPassword('Segreta-123'))
        && !checkPassword('sbagliata', $legacyHash('Segreta-123'))
        && !checkPassword('Segreta-123', '')
        && !checkPassword('Segreta-123', 'testo-non-hash');
});

check('passwordNeedsRehash: vero per phpass, falso per hash nuovo', function () use ($legacyHash) {
    return passwordNeedsRehash($legacyHash('x')) === true
        && passwordNeedsRehash('$H$9abcdefghijklmnopqrstuvwxyz0123') === true
        && passwordNeedsRehash(hashPassword('x')) === false;
});

check('caratteri speciali \' " \\ è €: hash verificabile con la stringa grezza', function () {
    $plain = "a'b\"c\\dè€";
    $hash = hashPassword($plain);
    return checkPassword($plain, $hash) && !checkPassword(sanitize($plain), $hash);
});

check('transitorio: phpass di sanitize("a\'b") verifica a\'b ed è marcato per rehash', function () use ($legacyHash) {
    $hash = $legacyHash(sanitize("a'b"));
    return !checkPassword("a'b", $hash)
        && checkUserPassword("a'b", $hash)
        && passwordNeedsRehash($hash);
});

check('il ripiego su sanitize() non vale per gli hash nuovi', function () {
    return !checkUserPassword("a'b", hashPassword(sanitize("a'b")));
});

check('authenticateUser: login phpass riuscito aggiorna l\'hash e logga password_rehash', function () use ($legacyHash, $makeUser, $reset, $events) {
    $reset($makeUser($legacyHash('Segreta-123')));
    $ok = authenticateUser('username', 'mario', 'Segreta-123', 'backend', null, false);
    $newHash = (string) $GLOBALS['pwUser']->password;
    return $ok === true
        && count($GLOBALS['pwModified']) === 1
        && $GLOBALS['pwModified'][0]['value'] === 42
        && !isLegacyPasswordHash($newHash)
        && checkPassword('Segreta-123', $newHash)
        && in_array('password_rehash', $events(), true)
        && in_array('login_success', $events(), true);
});

check('authenticateUser: password con apice entra e il rehash usa il valore grezzo', function () use ($legacyHash, $makeUser, $reset) {
    $reset($makeUser($legacyHash(sanitize("l'amico"))));
    $ok = authenticateUser('username', 'mario', "l'amico", 'backend', null, false);
    $newHash = (string) $GLOBALS['pwUser']->password;
    return $ok === true && checkPassword("l'amico", $newHash) && !checkPassword(sanitize("l'amico"), $newHash);
});

check('authenticateUser: hash già nuovo, nessun rehash', function () use ($makeUser, $reset, $events) {
    $reset($makeUser(hashPassword('Segreta-123')));
    $ok = authenticateUser('username', 'mario', 'Segreta-123', 'backend', null, false);
    return $ok === true && $GLOBALS['pwModified'] === [] && !in_array('password_rehash', $events(), true);
});

check('authenticateUser: password errata, 905 e nessun update', function () use ($legacyHash, $makeUser, $reset) {
    $reset($makeUser($legacyHash('Segreta-123')));
    $ok = authenticateUser('username', 'mario', 'sbagliata', 'backend', null, false);
    return $ok === false && (int) $GLOBALS['ALERT'] === 905 && $GLOBALS['pwModified'] === [];
});

check('authenticateUser: login riuscito ma area negata, nessun rehash', function () use ($legacyHash, $makeUser, $reset) {
    $user = $makeUser($legacyHash('Segreta-123'));
    $user->area = ['frontend'];
    $reset($user);
    $ok = authenticateUser('username', 'mario', 'Segreta-123', 'backend', null, false);
    return $ok === false && (int) $GLOBALS['ALERT'] === 911 && $GLOBALS['pwModified'] === [];
});

summary();
