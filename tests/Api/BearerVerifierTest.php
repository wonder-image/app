<?php
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

use Firebase\JWT\JWT;
use Wonder\Api\Contracts\ApiUserProvider;
use Wonder\Api\Endpoint;
use Wonder\Api\EndpointException;
use Wonder\Api\Token\{ ApiTokens, BearerVerifier, VerifiedBearer };
use Wonder\App\Support\ApiActivityTokenMigration;

# Verifica dei bearer senza database: gli utenti arrivano da un provider in
# memoria con la stessa forma di infoUser() (riga api_users sotto l'authority).

const KEY = 'wonder-test-signing-secret-not-real-xxxxxxxxxxxxxxxx';
const NOW = 1_800_000_000;

final class MemoryUsers implements ApiUserProvider
{
    /** @var array<string, object> */
    public array $users = [];

    public function user(int|string $id): ?object
    {
        return $this->users[(string) $id] ?? (object) ['exists' => false];
    }
}

function apiUserRow(int $userId, string $token, array $overrides = []): object
{
    return (object) array_merge([
        'id' => $userId,
        'username' => 'user'.$userId,
        'exists' => true,
        'active' => true,
        'authority' => ['api_internal_user'],
        'api_internal_user' => (object) [
            'exists' => true,
            'id' => 100 + $userId,
            'user_id' => $userId,
            'token' => $token,
            'active' => 'true',
            'allowed_ips' => '[]',
            'allowed_domains' => '[]',
        ],
    ], $overrides);
}

function rejection(callable $fn): ?EndpointException
{
    try {
        $fn();
    } catch (EndpointException $e) {
        return $e;
    }

    return null;
}

function rejectedWith(int $code, callable $fn, ?string $message = null): bool
{
    $e = rejection($fn);

    return $e !== null && $e->getCode() === $code && ($message === null || $e->getMessage() === $message);
}

$users = new MemoryUsers();
$verifier = new BearerVerifier(KEY, $users, false);
$auth = ['api_internal_user'];

$token = ApiTokens::issue(7, 3600, KEY, NOW);
$users->users['7'] = apiUserRow(7, $token);

echo "Token API\n";

check('Il token salvato e valido viene accettato', function () use ($verifier, $token, $auth) {
    $ok = $verifier->verify($token, $auth, '1.2.3.4', 'example.com', true, NOW + 10);
    return $ok->kind === VerifiedBearer::KIND_API && (int) $ok->userId === 7 && $ok->tokenId === 107 && $ok->expiresAt === NOW + 3600;
});

check('Token rigenerato: il vecchio JWT riceve 401', function () use ($verifier, $users, $token, $auth) {
    $old = $token;
    $users->users['7'] = apiUserRow(7, ApiTokens::issue(7, 3600, KEY, NOW + 5));
    $result = rejectedWith(401, fn () => $verifier->verify($old, $auth, null, null, true, NOW + 10), 'Token non valido');
    $users->users['7'] = apiUserRow(7, $old);
    return $result;
});

check('JWT firmato correttamente ma diverso da quello salvato: 401', function () use ($verifier, $auth) {
    $forged = JWT::encode(['sub' => 7, 'iat' => NOW, 'jti' => ApiTokens::newJti()], KEY, 'HS256');
    return rejectedWith(401, fn () => $verifier->verify($forged, $auth, null, null, true, NOW + 10), 'Token non valido');
});

check('Token revocato (riga api_users senza token): 401', function () use ($verifier, $users, $token, $auth) {
    $users->users['7'] = apiUserRow(7, '');
    $result = rejectedWith(401, fn () => $verifier->verify($token, $auth, null, null, true, NOW + 10));
    $users->users['7'] = apiUserRow(7, $token);
    return $result;
});

check('Token scaduto: 401 "Token scaduto" (prima era un 500)', function () use ($verifier, $token, $auth) {
    return rejectedWith(401, fn () => $verifier->verify($token, $auth, null, null, true, NOW + 3600), 'Token scaduto');
});

check('Firma errata o bearer non JWT: 401 (prima era un 500)', function () use ($verifier, $auth) {
    $other = JWT::encode(['sub' => 7, 'iat' => NOW], 'altra-chiave-0123456789abcdef0123456789', 'HS256');
    return rejectedWith(401, fn () => $verifier->verify($other, $auth, null, null, true, NOW))
        && rejectedWith(401, fn () => $verifier->verify('non-un-jwt', $auth, null, null, true, NOW));
});

check('Token legacy senza exp: valido finche coincide, rifiutato con requireExp', function () use ($users, $auth) {
    $legacy = JWT::encode(['sub' => 8, 'iat' => NOW, 'jti' => uniqid()], KEY, 'HS256');
    $users->users['8'] = apiUserRow(8, $legacy);
    $lenient = new BearerVerifier(KEY, $users, false);
    $strict = new BearerVerifier(KEY, $users, true);
    return $lenient->verify($legacy, $auth, null, null, true, NOW)->expiresAt === null
        && rejectedWith(401, fn () => $strict->verify($legacy, $auth, null, null, true, NOW), 'Token senza scadenza');
});

check('Authority mancante: 403; utente o token disattivati: 401', function () use ($verifier, $users, $token) {
    $forbidden = rejectedWith(403, fn () => $verifier->verify($token, ['api_public_access'], null, null, true, NOW));
    $users->users['7'] = apiUserRow(7, $token, ['active' => false]);
    $inactiveUser = rejectedWith(401, fn () => $verifier->verify($token, ['api_internal_user'], null, null, true, NOW), 'Utente non attivo');
    $row = apiUserRow(7, $token);
    $row->api_internal_user->active = 'false';
    $users->users['7'] = $row;
    $inactiveToken = rejectedWith(401, fn () => $verifier->verify($token, ['api_internal_user'], null, null, true, NOW), 'Token non attivo');
    $users->users['7'] = apiUserRow(7, $token);
    return $forbidden && $inactiveUser && $inactiveToken;
});

check('IP e domini ammessi restano applicati', function () use ($verifier, $users, $token, $auth) {
    $row = apiUserRow(7, $token);
    $row->api_internal_user->allowed_ips = '["9.9.9.9"]';
    $row->api_internal_user->allowed_domains = '["example.com"]';
    $users->users['7'] = $row;
    $ip = rejectedWith(401, fn () => $verifier->verify($token, $auth, '1.2.3.4', 'example.com', true, NOW), 'Token non valido per questo IP');
    $domain = rejectedWith(401, fn () => $verifier->verify($token, $auth, '9.9.9.9', 'evil.test', true, NOW), 'Token non valido per questo dominio');
    $ok = $verifier->verify($token, $auth, '9.9.9.9', 'example.com', true, NOW)->kind === VerifiedBearer::KIND_API;
    $users->users['7'] = apiUserRow(7, $token);
    return $ip && $domain && $ok;
});

check('Emissione: jti casuale di 32 hex ed exp secondo il TTL (0 = senza exp)', function () {
    JWT::$timestamp = NOW;
    $a = JWT::decode(ApiTokens::issue(5, 600, KEY, NOW), new Firebase\JWT\Key(KEY, 'HS256'));
    $b = JWT::decode(ApiTokens::issue(5, 0, KEY, NOW), new Firebase\JWT\Key(KEY, 'HS256'));
    JWT::$timestamp = null;
    return preg_match('/^[0-9a-f]{32}$/', $a->jti) === 1 && $a->jti !== $b->jti
        && $a->exp === NOW + 600 && !isset($b->exp);
});

echo "\nToken di pagina\n";

$systemToken = ApiTokens::issue(1, 0, KEY, NOW);
$users->users['1'] = apiUserRow(1, $systemToken, ['username' => '@system']);
$systemRow = ['id' => 101, 'user_id' => 1, 'token' => $systemToken];
$page = ApiTokens::page($systemRow, 3600, KEY, NOW);

check('Il token di pagina non contiene il token di sistema ed e accettato', function () use ($verifier, $page, $systemToken, $auth) {
    $ok = $verifier->verify($page, $auth, null, null, true, NOW + 10);
    return $page !== $systemToken && !str_contains($page, $systemToken) && $ok->isPageToken() && (int) $ok->userId === 1;
});

check('Gli endpoint solo server rifiutano il token di pagina', function () use ($verifier, $page, $systemToken, $auth) {
    return rejectedWith(401, fn () => $verifier->verify($page, $auth, null, null, false, NOW + 10))
        && $verifier->verify($systemToken, $auth, null, null, false, NOW + 10)->kind === VerifiedBearer::KIND_API;
});

check('Token di pagina scaduto: 401', function () use ($verifier, $page, $auth) {
    return rejectedWith(401, fn () => $verifier->verify($page, $auth, null, null, true, NOW + 3600), 'Token scaduto');
});

check('Rigenerare il token di sistema invalida i token di pagina gia emessi', function () use ($verifier, $users, $page, $systemToken, $auth) {
    $users->users['1'] = apiUserRow(1, ApiTokens::issue(1, 0, KEY, NOW + 1), ['username' => '@system']);
    $result = rejectedWith(401, fn () => $verifier->verify($page, $auth, null, null, true, NOW + 10), 'Token non valido');
    $users->users['1'] = apiUserRow(1, $systemToken, ['username' => '@system']);
    return $result;
});

check('Token di pagina senza exp, con tid diverso o typ sconosciuto: 401', function () use ($verifier, $systemToken, $auth) {
    $base = ['sub' => 1, 'iat' => NOW, 'jti' => ApiTokens::newJti(), 'typ' => 'page', 'tid' => 101, 'tkh' => ApiTokens::binding($systemToken)];
    $noExp = JWT::encode($base, KEY, 'HS256');
    $wrongTid = JWT::encode(['exp' => NOW + 60, 'tid' => 999] + $base, KEY, 'HS256');
    $otherTyp = JWT::encode(['exp' => NOW + 60, 'typ' => 'refresh'] + $base, KEY, 'HS256');
    return rejectedWith(401, fn () => $verifier->verify($noExp, $auth, null, null, true, NOW))
        && rejectedWith(401, fn () => $verifier->verify($wrongTid, $auth, null, null, true, NOW))
        && rejectedWith(401, fn () => $verifier->verify($otherTyp, $auth, null, null, true, NOW));
});

echo "\napi_activity\n";

check('La riga di api_activity non contiene il bearer, solo la sua impronta', function () use ($token) {
    $reflection = new ReflectionClass(Endpoint::class);
    $endpoint = $reflection->newInstanceWithoutConstructor();
    foreach (['token' => $token, 'tokenId' => 107, 'userId' => 7, 'ip' => '1.2.3.4', 'domain' => 'example.com', 'endpoint' => '/api/test', 'requestMethod' => 'POST', 'contentType' => 'application/json'] as $property => $value) {
        $reflection->getProperty($property)->setValue($endpoint, $value);
    }
    $endpoint->data = ['name' => 'Mario'];
    $endpoint->files = [];
    $values = $reflection->getMethod('activityValues')->invoke($endpoint, ['ok' => true], 200);
    $serialized = json_encode($values);
    return !str_contains((string) $serialized, $token)
        && preg_match('/^sha256:[0-9a-f]{16}$/', $values['token']) === 1
        && $values['token'] === ApiTokens::fingerprint($token)
        && $values['token_id'] === 107 && $values['success'] === true;
});

check('Migrazione: svuota solo i bearer in chiaro, idempotente, salta se la tabella manca', function () use ($token) {
    $rows = [['token' => $token], ['token' => ApiTokens::fingerprint($token)], ['token' => '']];
    $connection = new class($rows) extends mysqli {
        public array $queries = [];
        public bool $tableExists = true;
        public function __construct(public array $rows) {}
        #[\ReturnTypeWillChange]
        public function query($query, $resultMode = MYSQLI_STORE_RESULT)
        {
            $this->queries[] = $query;
            $dirty = array_keys(array_filter($this->rows, fn ($row) => $row['token'] !== '' && !str_starts_with($row['token'], 'sha256:')));
            if (str_starts_with($query, 'SHOW TABLES')) { return (object) ['num_rows' => $this->tableExists ? 1 : 0]; }
            if (str_starts_with($query, 'SELECT COUNT')) {
                return new class(count($dirty)) {
                    public function __construct(private int $n) {}
                    public function fetch_row(): array { return [$this->n]; }
                };
            }
            foreach ($dirty as $key) { $this->rows[$key]['token'] = ''; }
            return true;
        }
    };
    $first = ApiActivityTokenMigration::run($connection);
    $second = ApiActivityTokenMigration::run($connection);
    $connection->tableExists = false;
    $before = count($connection->queries);
    $missing = ApiActivityTokenMigration::run($connection);
    $updates = count(array_filter($connection->queries, fn ($query) => str_starts_with($query, 'UPDATE')));
    return $first === 1 && $second === 0 && $missing === 0 && $updates === 1 && count($connection->queries) === $before + 1
        && array_column($connection->rows, 'token') === ['', ApiTokens::fingerprint($token), '']
        && ApiActivityTokenMigration::sql() === "UPDATE `api_activity` SET `token` = '' WHERE `token` <> '' AND `token` NOT LIKE 'sha256:%'";
});

summary();
