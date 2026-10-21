<?php
/** php tests/Security/PasswordIssuanceTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Auth\Federated\OidcIdTokenVerifier;
use Wonder\Backend\Support\UserManagementPageController;
use Wonder\Support\Text\Random;

// Stub sql e mail in memoria, nessuna connessione reale.
$GLOBALS['tTokens'] = [];
$GLOBALS['tLogs'] = [];
$GLOBALS['tMails'] = [];
$GLOBALS['tMailOk'] = true;
$GLOBALS['tTokenFail'] = false;
$GLOBALS['PATH'] = (object) ['backend' => 'https://sito.test/backend/'];
$GLOBALS['SOCIETY'] = (object) ['email' => 'staff@sito.test'];

function sqlInsert($table, $values): object
{
    if ($table === 'auth_one_time_tokens') {
        if ($GLOBALS['tTokenFail']) {
            return (object) ['success' => false];
        }
        $id = count($GLOBALS['tTokens']) + 1;
        $GLOBALS['tTokens'][$id] = ['id' => $id] + $values;

        return (object) ['success' => true, 'insert_id' => $id];
    }
    if ($table === 'auth_log') {
        $GLOBALS['tLogs'][] = $values;
    }

    return (object) ['success' => true, 'insert_id' => 1];
}
function sqlSelect($table, $where = [], $limit = null): object
{
    return (object) ['exists' => false, 'row' => []];
}
function sqlModify(...$args): object
{
    return (object) ['success' => true];
}
function permissions($key): object
{
    return (object) ['name' => 'Amministratore'];
}
function __r(string $name, array $params = []): string
{
    return 'https://sito.test/'.$name.'/';
}
function sendMail($from, $to, $subject, $content): bool
{
    $GLOBALS['tMails'][] = compact('from', 'to', 'subject', 'content');

    return $GLOBALS['tMailOk'];
}

function welcome(array $post, object $user): void
{
    $GLOBALS['tMails'] = [];
    $GLOBALS['tLogs'] = [];
    $controller = (new ReflectionClass(UserManagementPageController::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($controller, 'sendWelcomeMail');
    $method->invoke($controller, $post, $user);
}

$post = ['username' => 'mario', 'email' => 'mario@sito.test', 'name' => 'Mario', 'authority' => 'administrator'];
$newUser = (object) ['id' => 7, 'username' => 'mario', 'email' => 'mario@sito.test', 'password' => ''];

echo "C-PWD-I8: codici casuali\n";

check('Random usa random_int e non rand', function () {
    $source = (string) file_get_contents(__DIR__.'/../../class/Support/Text/Random.php');

    return str_contains($source, 'random_int(') && !preg_match('/[^_]rand\(/', $source);
});

check('Random rispetta lunghezza, prefisso e alfabeto', function () {
    Random::init('numbers');
    $digits = Random::generate(64);
    Random::init('letters');
    $letters = Random::generate(64, 'x-');
    Random::init('all');

    return preg_match('/^[0-9]{64}$/', $digits) === 1
        && preg_match('/^x-[A-Z]{64}$/', $letters) === 1;
});

echo "\nC-PWD-I8: nuovo utente senza password stampata\n";

check('il form utente non contiene un input password precompilato', function () {
    $view = (string) file_get_contents(__DIR__.'/../../app/view/pages/backend/user/manage.php');

    return !str_contains($view, 'name="password"') && !str_contains($view, "code(2, 'letters')");
});

check('la mail di benvenuto porta il link password_set e nessuna password', function () use ($post, $newUser) {
    welcome($post + ['password' => 'NON-DEVE-USCIRE'], $newUser);
    $mail = $GLOBALS['tMails'][0] ?? null;
    $token = end($GLOBALS['tTokens']);

    return $mail !== null
        && $token['purpose'] === 'password_set'
        && (int) $token['subject_user_id'] === 7
        && str_contains($mail['content'], 'https://sito.test/backend.account.password.set/?token='.$token['selector'].'.')
        && !str_contains($mail['content'], 'NON-DEVE-USCIRE')
        && !str_contains($mail['content'], 'Password:')
        && $mail['from'] === 'staff@sito.test';
});

check('il token di benvenuto dura WELCOME_TOKEN_TTL', function () use ($post, $newUser) {
    $before = time();
    welcome($post, $newUser);
    $token = end($GLOBALS['tTokens']);
    $expires = strtotime($token['expires_at']);

    return abs($expires - ($before + UserManagementPageController::WELCOME_TOKEN_TTL)) <= 2;
});

check('invio riuscito: log welcome_mail positivo', function () use ($post, $newUser) {
    welcome($post, $newUser);
    $log = $GLOBALS['tLogs'][0] ?? [];

    return ($log['event'] ?? '') === 'welcome_mail' && ($log['success'] ?? null) === 1 && (int) $log['user_id'] === 7;
});

check('invio fallito: log mail_failed, nessuna eccezione', function () use ($post, $newUser) {
    $GLOBALS['tMailOk'] = false;
    welcome($post, $newUser);
    $GLOBALS['tMailOk'] = true;
    $log = $GLOBALS['tLogs'][0] ?? [];

    return ($log['event'] ?? '') === 'welcome_mail'
        && ($log['success'] ?? null) === 0
        && str_contains((string) $log['meta'], 'mail_failed');
});

check('token non emesso: log token_failed e nessuna mail', function () use ($post, $newUser) {
    $GLOBALS['tTokenFail'] = true;
    welcome($post, $newUser);
    $GLOBALS['tTokenFail'] = false;
    $log = $GLOBALS['tLogs'][0] ?? [];

    return $GLOBALS['tMails'] === [] && str_contains((string) ($log['meta'] ?? ''), 'token_failed');
});

check('utente esistente con password: nessun token, solo link di accesso', function () use ($post) {
    $count = count($GLOBALS['tTokens']);
    welcome($post, (object) ['id' => 9, 'email' => 'mario@sito.test', 'password' => '$argon2id$x']);
    $mail = $GLOBALS['tMails'][0] ?? null;

    return $mail !== null
        && count($GLOBALS['tTokens']) === $count
        && str_contains($mail['content'], 'https://sito.test/backend/')
        && !str_contains($mail['content'], 'token=');
});

check('senza id utente non parte nessuna mail', function () use ($post) {
    welcome($post, (object) ['exists' => false]);

    return $GLOBALS['tMails'] === [];
});

echo "\nC-PWD-I8: cache JWKS nello storage del sito\n";

$base = sys_get_temp_dir().'/wi-oidc-test-'.bin2hex(random_bytes(4));
mkdir($base.'/src', 0775, true);
$jwksFile = $base.'/src/jwks.json';
file_put_contents($jwksFile, json_encode(['keys' => [['kid' => 'a', 'kty' => 'RSA']]]));

$jwks = static function (OidcIdTokenVerifier $verifier): array {
    return (new ReflectionMethod($verifier, 'jwks'))->invoke($verifier);
};

check('con cacheDir esplicita il file finisce lì', function () use ($base, $jwksFile, $jwks) {
    $verifier = new OidcIdTokenVerifier('aud', ['iss'], $jwksFile, null, 3600, $base.'/explicit');
    $keys = $jwks($verifier);

    return ($keys['keys'][0]['kid'] ?? '') === 'a'
        && is_file($base.'/explicit/oidc-'.hash('sha256', $jwksFile).'.json');
});

check('senza cacheDir usa ROOT/storage/cache, non la /tmp condivisa', function () use ($base, $jwksFile, $jwks) {
    define('ROOT', $base.'/site');
    $legacy = rtrim(sys_get_temp_dir(), '/').'/wonder-oidc-'.hash('sha256', $jwksFile).'.json';
    $jwks(new OidcIdTokenVerifier('aud', ['iss'], $jwksFile));

    return is_file($base.'/site/storage/cache/oidc-'.hash('sha256', $jwksFile).'.json') && !is_file($legacy);
});

check('la cache valida evita una nuova lettura della sorgente', function () use ($base, $jwksFile, $jwks) {
    unlink($jwksFile);
    $keys = $jwks(new OidcIdTokenVerifier('aud', ['iss'], $jwksFile));

    return ($keys['keys'][0]['kid'] ?? '') === 'a';
});

exec('rm -rf '.escapeshellarg($base));

summary();
