<?php
/** php tests/Api/SessionAccessTest.php */
declare(strict_types=1);

# Output in buffer: la sessione deve poter partire a meta test.
ob_start();

require dirname(__DIR__).'/harness.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';

# vendor/ puo essere un symlink a un altro checkout: le classi Wonder vengono
# sempre da questo albero.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Wonder\\')) {
        $file = dirname(__DIR__, 2).'/class/'.str_replace('\\', '/', substr($class, 7)).'.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);

use Wonder\Api\Contracts\ApiUserProvider;
use Wonder\Api\Endpoint;
use Wonder\Api\EndpointException;
use Wonder\Api\Token\{ BearerVerifier, VerifiedBearer };

# Accesso di pagina senza bearer (#85): le pagine non stampano piu un token,
# le chiamate API del sito valgono con sessione, stesso sito e CSRF.

const KEY = 'wonder-test-signing-secret-not-real-xxxxxxxxxxxxxxxx';

final class SessionUsers implements ApiUserProvider
{
    /** @var array<string, object> */
    public array $users = [];

    public function user(int|string $id): ?object
    {
        return $this->users[(string) $id] ?? (object) ['exists' => false];
    }
}

function systemRow(array $token = [], array $user = []): object
{
    return (object) array_merge([
        'id' => 1,
        'username' => '@system',
        'exists' => true,
        'active' => true,
        'authority' => ['api_internal_user'],
        'api_internal_user' => (object) array_merge([
            'exists' => true,
            'id' => 101,
            'user_id' => 1,
            'token' => 'jwt-salvato',
            'active' => 'true',
            'allowed_ips' => '[]',
            'allowed_domains' => '[]',
        ], $token),
    ], $user);
}

function refusedWith(int $code, callable $fn, ?string $message = null): bool
{
    try {
        $fn();
    } catch (EndpointException $e) {
        return $e->getCode() === $code && ($message === null || $e->getMessage() === $message);
    }

    return false;
}

$users = new SessionUsers();
$verifier = new BearerVerifier(KEY, $users, false);
$auth = ['api_internal_user'];
$users->users['1'] = systemRow();

echo "BearerVerifier::verifySession\n";

check('Sessione valida: utente @system come token di pagina, senza scadenza', function () use ($verifier, $auth) {
    $ok = $verifier->verifySession('1', $auth, '1.2.3.4', 'example.com');
    return $ok->kind === VerifiedBearer::KIND_PAGE && $ok->isPageToken()
        && (string) $ok->userId === '1' && $ok->tokenId === 101 && $ok->expiresAt === null;
});

check('Authority non posseduta: 403', fn () => refusedWith(403, fn () => $verifier->verifySession('1', ['api_public_access'])));

check('Utente di sistema mancante (DB vuoto): 401', fn () => refusedWith(401, fn () => $verifier->verifySession(null, $auth), 'Sessione non valida per questa richiesta')
    && refusedWith(401, fn () => $verifier->verifySession('', $auth))
    && refusedWith(401, fn () => $verifier->verifySession('99', $auth), 'Utente non valido'));

check('Token di sistema disattivato o revocato: 401', function () use ($verifier, $users, $auth) {
    $users->users['1'] = systemRow(['active' => 'false']);
    $inactive = refusedWith(401, fn () => $verifier->verifySession('1', $auth), 'Token non attivo');
    $users->users['1'] = systemRow(['token' => '']);
    $revoked = refusedWith(401, fn () => $verifier->verifySession('1', $auth), 'Token non valido');
    $users->users['1'] = systemRow([], ['active' => false]);
    $user = refusedWith(401, fn () => $verifier->verifySession('1', $auth), 'Utente non attivo');
    $users->users['1'] = systemRow();
    return $inactive && $revoked && $user;
});

check('IP e domini ammessi del token valgono anche per la sessione', function () use ($verifier, $users, $auth) {
    $users->users['1'] = systemRow(['allowed_ips' => '["10.0.0.1"]', 'allowed_domains' => '["example.com"]']);
    $ok = $verifier->verifySession('1', $auth, '10.0.0.1', 'example.com') instanceof VerifiedBearer;
    $ip = refusedWith(401, fn () => $verifier->verifySession('1', $auth, '10.0.0.2', 'example.com'), 'Token non valido per questo IP');
    $domain = refusedWith(401, fn () => $verifier->verifySession('1', $auth, '10.0.0.1', 'altro.it'), 'Token non valido per questo dominio');
    $users->users['1'] = systemRow();
    return $ok && $ip && $domain;
});

echo "Endpoint::bearerFromHeader\n";

check('Header della lib con API_TOKEN vuoto: nessun bearer, si passa alla sessione', fn () => Endpoint::bearerFromHeader('Bearer ') === null
    && Endpoint::bearerFromHeader('Bearer') === null
    && Endpoint::bearerFromHeader(null) === null
    && Endpoint::bearerFromHeader('') === null);

check('Bearer esplicito: letto come prima', fn () => Endpoint::bearerFromHeader('Bearer abc.def.ghi') === 'abc.def.ghi');

echo "Endpoint::sessionRequestAllowed\n";

$post = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'example.test', 'HTTPS' => 'on', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];

check('Senza sessione PHP: rifiutata', fn () => Endpoint::sessionRequestAllowed($post) === false);

session_save_path(sys_get_temp_dir());
session_start();
$_SESSION['wonder_auth_csrf'] = 'csrf-valido';

check('POST same-origin senza CSRF: rifiutata', function () use ($post) {
    unset($_SERVER['HTTP_X_WI_CSRF']);
    $_POST = [];
    return Endpoint::sessionRequestAllowed($post) === false;
});

check('POST same-origin con CSRF errato: rifiutata', function () use ($post) {
    $_SERVER['HTTP_X_WI_CSRF'] = 'csrf-errato';
    return Endpoint::sessionRequestAllowed($post) === false;
});

check('POST same-origin con X-WI-CSRF o _csrf validi: ammessa', function () use ($post) {
    $_SERVER['HTTP_X_WI_CSRF'] = 'csrf-valido';
    $header = Endpoint::sessionRequestAllowed($post);
    unset($_SERVER['HTTP_X_WI_CSRF']);
    $_POST = ['_csrf' => 'csrf-valido'];
    $field = Endpoint::sessionRequestAllowed($post);
    $_POST = [];
    return $header && $field;
});

check('Cross-site o Origin di un altro host con CSRF valido: rifiutata', function () use ($post) {
    $_SERVER['HTTP_X_WI_CSRF'] = 'csrf-valido';
    $cross = Endpoint::sessionRequestAllowed(['HTTP_SEC_FETCH_SITE' => 'cross-site'] + $post);
    $server = $post;
    unset($server['HTTP_SEC_FETCH_SITE']);
    $origin = Endpoint::sessionRequestAllowed($server + ['HTTP_ORIGIN' => 'https://attacker.example']);
    $none = Endpoint::sessionRequestAllowed($server);
    unset($_SERVER['HTTP_X_WI_CSRF']);
    return !$cross && !$origin && !$none;
});

check('GET same-origin: ammessa senza CSRF, cross-site no', function () use ($post) {
    $get = ['REQUEST_METHOD' => 'GET'] + $post;
    return Endpoint::sessionRequestAllowed($get) === true
        && Endpoint::sessionRequestAllowed(['HTTP_SEC_FETCH_SITE' => 'cross-site'] + $get) === false;
});

session_destroy();

echo "Pagine senza bearer\n";

$root = dirname(__DIR__, 2);
$views = [
    'app/view/components/frontend/layout/head.php',
    'app/view/components/backend/layout/head.php',
    'app/view/pages/docs/preview.php',
    'app/http/backend/docs/preview.php',
];

check('Head frontend, backend e anteprima docs non emettono token di pagina', function () use ($root, $views) {
    foreach ($views as $view) {
        $source = (string) file_get_contents($root.'/'.$view);
        if (str_contains($source, 'pageToken(') || str_contains($source, 'appToken(') || preg_match('/eyJ[\w-]+\./', $source)) {
            echo "    {$view}\n";
            return false;
        }
    }
    return true;
});

check('Nessuna vista in app/ stampa pageToken() o appToken()', function () use ($root) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app', FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        $path = $file->getPathname();
        if (!str_ends_with($path, '.php') || !str_contains($path, '/view/')) {
            continue;
        }
        if (preg_match('/(pageToken|appToken)\(/', (string) file_get_contents($path))) {
            echo "    {$path}\n";
            return false;
        }
    }
    return true;
});

check('API_TOKEN resta definito vuoto (retrocompatibilita con ApiClient della lib)', function () use ($root, $views) {
    foreach (array_slice($views, 0, 3) as $view) {
        if (!str_contains((string) file_get_contents($root.'/'.$view), "const API_TOKEN = '';")) {
            return false;
        }
    }
    return true;
});

summary();
