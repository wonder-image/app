<?php
/** php tests/Security/PageTokenTest.php */
declare(strict_types=1);

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

use Firebase\JWT\{ JWT, Key };
use Wonder\Api\Contracts\ApiUserProvider;
use Wonder\Api\EndpointException;
use Wonder\Api\Token\{ ApiTokens, BearerVerifier, TokenConfig };
use Wonder\Http\SameOrigin;

# Core-5: token di pagina di 1 ora, legato alla sessione (`sid`), rinnovo
# same-origin su POST /api/token/page/.

const KEY = 'wonder-test-signing-secret-not-real-xxxxxxxxxxxxxxxx';
const NOW = 1_800_000_000;
const SESSION = 'sess-aaaaaaaaaaaaaaaaaaaaaaaa';

final class MemoryUsers implements ApiUserProvider
{
    /** @var array<string, object> */
    public array $users = [];

    public function user(int|string $id): ?object
    {
        return $this->users[(string) $id] ?? (object) ['exists' => false];
    }
}

function rejected(callable $fn, ?string $message = null): bool
{
    try {
        $fn();
    } catch (EndpointException $e) {
        return $e->getCode() === 401 && ($message === null || $e->getMessage() === $message);
    }

    return false;
}

function claims(string $jwt): object
{
    JWT::$timestamp = NOW;
    $decoded = JWT::decode($jwt, new Key(KEY, 'HS256'));
    JWT::$timestamp = null;

    return $decoded;
}

$systemToken = ApiTokens::issue(1, 0, KEY, NOW);
$users = new MemoryUsers();
$users->users['1'] = (object) [
    'id' => 1,
    'username' => '@system',
    'exists' => true,
    'active' => true,
    'authority' => ['api_internal_user'],
    'api_internal_user' => (object) [
        'exists' => true,
        'id' => 101,
        'user_id' => 1,
        'token' => $systemToken,
        'active' => 'true',
        'allowed_ips' => '[]',
        'allowed_domains' => '[]',
    ],
];
$row = ['id' => 101, 'user_id' => 1, 'token' => $systemToken];
$auth = ['api_internal_user'];
$verifier = new BearerVerifier(KEY, $users);

echo "TTL\n";

check('API_PAGE_TOKEN_TTL: default 3600 (1 ora)', function () {
    $previous = $_ENV['API_PAGE_TOKEN_TTL'] ?? null;
    unset($_ENV['API_PAGE_TOKEN_TTL']);
    putenv('API_PAGE_TOKEN_TTL');
    $ttl = TokenConfig::pageTtl();
    if ($previous !== null) {
        $_ENV['API_PAGE_TOKEN_TTL'] = $previous;
    }

    return $ttl === 3600;
});

check('API_PAGE_TOKEN_TTL: limitato a 300-604800, non numerico => default', function () {
    $previous = $_ENV['API_PAGE_TOKEN_TTL'] ?? null;
    $results = [];
    $log = ini_set('error_log', '/dev/null');
    foreach (['10', '9999999', 'abc'] as $value) {
        $_ENV['API_PAGE_TOKEN_TTL'] = $value;
        $results[] = TokenConfig::pageTtl();
    }
    $_ENV['API_PAGE_TOKEN_TTL'] = '900';
    $results[] = TokenConfig::pageTtl();
    ini_set('error_log', (string) $log);
    if ($previous === null) {
        unset($_ENV['API_PAGE_TOKEN_TTL']);
    } else {
        $_ENV['API_PAGE_TOKEN_TTL'] = $previous;
    }

    return $results === [300, 604800, 3600, 900];
});

check('Il token emesso con il TTL di default scade dopo 1 ora', function () use ($row) {
    unset($_ENV['API_PAGE_TOKEN_TTL']);
    $c = claims(ApiTokens::page($row, null, KEY, NOW, SESSION));

    return $c->exp === NOW + 3600 && $c->typ === 'page';
});

echo "\nsid\n";

$bound = ApiTokens::page($row, 3600, KEY, NOW, SESSION);

check('Con una sessione il token porta sid (HMAC, mai l\'id in chiaro)', function () use ($bound) {
    $c = claims($bound);

    return isset($c->sid) && $c->sid === ApiTokens::sessionBinding(SESSION, KEY)
        && !str_contains($bound, SESSION) && !str_contains(json_encode($c), SESSION);
});

check('sid dipende dalla chiave dell\'app', function () {
    return ApiTokens::sessionBinding(SESSION, KEY) !== ApiTokens::sessionBinding(SESSION, KEY.'-altra');
});

check('Stessa sessione: accettato', function () use ($verifier, $bound, $auth) {
    return $verifier->verify($bound, $auth, null, null, true, NOW + 10, SESSION)->isPageToken();
});

check('Altra sessione: 401', function () use ($verifier, $bound, $auth) {
    return rejected(fn () => $verifier->verify($bound, $auth, null, null, true, NOW + 10, 'sess-bbbbbbbbbbbbbbbbbbbbbbbb'),
        'Token di pagina non valido per questa sessione');
});

check('Nessuna sessione nella richiesta: 401', function () use ($verifier, $bound, $auth) {
    return rejected(fn () => $verifier->verify($bound, $auth, null, null, true, NOW + 10, null))
        && rejected(fn () => $verifier->verify($bound, $auth, null, null, true, NOW + 10, ''));
});

check('sid manomesso: firma non valida (401)', function () use ($verifier, $auth, $row) {
    $forged = JWT::encode([
        'sub' => 1, 'iat' => NOW, 'exp' => NOW + 3600, 'jti' => ApiTokens::newJti(), 'typ' => 'page',
        'tid' => 101, 'tkh' => ApiTokens::binding($row['token']), 'sid' => ApiTokens::sessionBinding(SESSION, KEY),
    ], 'chiave-diversa-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', 'HS256');

    return rejected(fn () => $verifier->verify($forged, $auth, null, null, true, NOW + 10, SESSION));
});

check('Il token API non di pagina non dipende dalla sessione', function () use ($verifier, $systemToken, $auth) {
    return !$verifier->verify($systemToken, $auth, null, null, true, NOW + 10, null)->isPageToken();
});

check('Token di pagina con sid ma scaduto: 401', function () use ($verifier, $bound, $auth) {
    return rejected(fn () => $verifier->verify($bound, $auth, null, null, true, NOW + 3600, SESSION), 'Token scaduto');
});

echo "\nRetrocompatibilita (token senza sid)\n";

$legacy = ApiTokens::page($row, 3600, KEY, NOW);

check('Senza sessione il token non porta sid', function () use ($legacy) {
    return !isset(claims($legacy)->sid);
});

check('Token senza sid accettato fino alla scadenza naturale, con log', function () use ($verifier, $legacy, $auth) {
    $log = tempnam(sys_get_temp_dir(), 'wi-log');
    $previous = ini_set('error_log', (string) $log);
    $ok = $verifier->verify($legacy, $auth, null, null, true, NOW + 10, SESSION)->isPageToken();
    ini_set('error_log', (string) $previous);
    $written = (string) file_get_contents((string) $log);
    @unlink((string) $log);

    return $ok && str_contains($written, 'token di pagina senza sid');
});

check('Token senza sid rifiutato dopo exp', function () use ($verifier, $legacy, $auth) {
    return rejected(fn () => $verifier->verify($legacy, $auth, null, null, true, NOW + 3600, SESSION), 'Token scaduto');
});

echo "\nSameOrigin\n";

$base = ['HTTP_HOST' => 'example.test', 'HTTPS' => 'on'];

check('Sec-Fetch-Site same-origin: ammesso', fn () => SameOrigin::check($base + ['HTTP_SEC_FETCH_SITE' => 'same-origin']));

check('Sec-Fetch-Site cross-site / same-site / none: rifiutato, anche con Origin uguale', function () use ($base) {
    foreach (['cross-site', 'same-site', 'none'] as $site) {
        if (SameOrigin::check($base + ['HTTP_SEC_FETCH_SITE' => $site, 'HTTP_ORIGIN' => 'https://example.test'])) {
            return false;
        }
    }

    return true;
});

check('Origin dello stesso host: ammesso (porta di default ignorata)', function () use ($base) {
    return SameOrigin::check($base + ['HTTP_ORIGIN' => 'https://example.test'])
        && SameOrigin::check($base + ['HTTP_ORIGIN' => 'https://EXAMPLE.test:443'])
        && SameOrigin::check(['HTTP_HOST' => 'example.test:8443', 'HTTPS' => 'on', 'HTTP_ORIGIN' => 'https://example.test:8443']);
});

check('Origin di altro host, sottodominio, porta o null: rifiutato', function () use ($base) {
    foreach (['https://evil.test', 'https://sub.example.test', 'https://example.test:8443', 'null', 'https://example.test.evil.test', 'nonsense'] as $origin) {
        if (SameOrigin::check($base + ['HTTP_ORIGIN' => $origin])) {
            return false;
        }
    }

    return true;
});

check('Senza Sec-Fetch-Site ne Origin: rifiutato', fn () => !SameOrigin::check($base));

echo "\nRinnovo (POST /api/token/page/)\n";

check('Rotta token.page (area api) registrata in POST su /api/token/page/, senza guard', function () {
    $root = dirname(__DIR__, 2);
    $script = tempnam(sys_get_temp_dir(), 'wi-routes-');
    if (!is_string($script)) {
        return false;
    }
    $source = '<?php require '.var_export($root.'/vendor/autoload.php', true).';'
        .'spl_autoload_register(static function (string $c): void { if (str_starts_with($c, "Wonder\\\\")) {'
        .' $f = '.var_export($root.'/class/', true).'.str_replace("\\\\", "/", substr($c, 7)).".php"; if (is_file($f)) { require $f; } } }, true, true);'
        .'ini_set("error_log", "/dev/null"); $ROOT_APP = '.var_export($root.'/app', true).'; $ROOT = '.var_export($root, true).';'
        .'require $ROOT_APP."/config/routes/route.api.php";'
        .'foreach (Wonder\\Http\\Route::all() as $r) { if (($r["name"] ?? "") === "token.page") { echo json_encode($r); } }';
    file_put_contents($script, $source);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output);
    unlink($script);
    $route = json_decode(implode('', $output), true);

    return is_array($route) && $route['area'] === 'api' && $route['method'] === 'POST'
        && $route['path'] === '/api/token/page/' && $route['private'] === false && $route['permit'] === []
        && str_ends_with((string) $route['handler'], '/app/http/api/token/page.php');
});

/**
 * Esegue il handler di rinnovo in un processo separato, senza database:
 * stampa lo stato HTTP e il corpo.
 */
$runRenewal = static function (array $server, bool $session): string {
    $autoload = var_export(dirname(__DIR__, 2).'/vendor/autoload.php', true);
    $classes = var_export(dirname(__DIR__, 2).'/class/', true);
    $file = var_export(dirname(__DIR__, 2).'/app/http/api/token/page.php', true);
    $serverExport = var_export($server, true);
    $sessionExport = var_export($session, true);
    $savePath = var_export(sys_get_temp_dir(), true);
    $script = tempnam(sys_get_temp_dir(), 'wi-page-token-');

    if (!is_string($script)) {
        return '';
    }

    $source = <<<PHP
<?php
require {$autoload};
spl_autoload_register(static function (string \$class): void {
    if (str_starts_with(\$class, 'Wonder\\\\')) {
        \$file = {$classes}.str_replace('\\\\', '/', substr(\$class, 7)).'.php';
        if (is_file(\$file)) { require \$file; }
    }
}, true, true);
\$_SERVER = array_merge(\$_SERVER, {$serverExport});
if ({$sessionExport}) {
    ini_set('session.use_cookies', '0');
    session_save_path({$savePath});
    session_start();
    \$_SESSION['wonder_auth_csrf'] = 'tok-csrf-test';
}
// Nessun database: la credenziale di sistema non e disponibile.
\$property = new ReflectionProperty(\Wonder\App\Credentials::class, 'appKey');
\$property->setValue(null, 'wonder-test-signing-secret-not-real-xxxxxxxxxxxxxxxx');
ini_set('error_log', '/dev/null');
register_shutdown_function(function (): void {
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); }
    echo '|status='.http_response_code();
});
require {$file};
PHP;

    file_put_contents($script, $source);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output);
    unlink($script);

    return implode("\n", $output);
};

$post = ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'example.test', 'HTTPS' => 'on'];

check('Rinnovo cross-site: 403 prima di emettere il token', function () use ($runRenewal, $post) {
    $out = $runRenewal($post + ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://evil.test'], true);

    return str_contains($out, '|status=403') && str_contains($out, 'Richiesta non consentita.') && !str_contains($out, '"token"');
});

check('Rinnovo senza Origin ne Sec-Fetch-Site: 403', function () use ($runRenewal, $post) {
    $out = $runRenewal($post, true);

    return str_contains($out, '|status=403') && !str_contains($out, '"token"');
});

check('Rinnovo in GET: 405', function () use ($runRenewal, $post) {
    $out = $runRenewal(['REQUEST_METHOD' => 'GET'] + $post + ['HTTP_SEC_FETCH_SITE' => 'same-origin'], true);

    return str_contains($out, '|status=405');
});

check('Rinnovo same-origin senza sessione: 403 (mai un token senza sid)', function () use ($runRenewal, $post) {
    $out = $runRenewal($post + ['HTTP_SEC_FETCH_SITE' => 'same-origin'], false);

    return str_contains($out, '|status=403') && str_contains($out, 'Sessione non disponibile.');
});

check('Rinnovo same-origin con sessione e CSRF supera i controlli (senza DB: 503 generico)', function () use ($runRenewal, $post) {
    $out = $runRenewal($post + ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_X_WI_CSRF' => 'tok-csrf-test'], true);

    return str_contains($out, '|status=503') && str_contains($out, 'Token non disponibile.')
        && !str_contains(strtolower($out), 'sql');
});

check('Il token emesso per la sessione corrente vale solo per quella sessione', function () use ($verifier, $row, $auth) {
    $first = ApiTokens::page($row, 3600, KEY, NOW, SESSION);
    $renewed = ApiTokens::page($row, 3600, KEY, NOW + 3000, SESSION);

    return $first !== $renewed
        && $verifier->verify($renewed, $auth, null, null, true, NOW + 3500, SESSION)->isPageToken()
        && rejected(fn () => $verifier->verify($first, $auth, null, null, true, NOW + 3600, SESSION), 'Token scaduto')
        && rejected(fn () => $verifier->verify($renewed, $auth, null, null, true, NOW + 3500, 'sess-cccccccccccccccccccccccc'));
});

summary();
