<?php
/** php tests/Security/ResourceApiPermissionTest.php */
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

use Wonder\Api\Contracts\ApiUserProvider;
use Wonder\Api\EndpointException;
use Wonder\Api\Support\{ ResourceApiAccess, ResourceApiController };
use Wonder\Api\Token\{ ApiTokens, BearerVerifier };
use Wonder\App\{ Model, Resource, ResourceRouteRegistrar };
use Wonder\App\ResourceSchema\{ ApiSchema, PermissionSchema };

# Core-2: permessi `api` vuoti negano sulle rotte Resource, `apiSchema()->guard()`
# e un'authority in piu, il token di pagina serve l'opt-in allowPageToken().

const KEY = 'wonder-test-signing-secret-not-real-xxxxxxxxxxxxxxxx';
const NOW = 1_800_000_000;

final class ApiPermTestModel extends Model
{
    public static string $table = 'api_perm_test';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

/** Nessun permesso api dichiarato (default della PermissionSchema). */
final class ApiPermEmptyResource extends Resource
{
    public static string $model = ApiPermTestModel::class;
}

/** Come RequestResource del sito: solo store, nessun guard, nessun opt-in. */
final class ApiPermStoreResource extends Resource
{
    public static string $model = ApiPermTestModel::class;

    public static function permission(): PermissionSchema
    {
        return PermissionSchema::for(static::class)->api('store', ['api_internal_user']);
    }
}

final class ApiPermGuardedResource extends Resource
{
    public static string $model = ApiPermTestModel::class;

    public static function permission(): PermissionSchema
    {
        return PermissionSchema::for(static::class)->apiCrud(['api_internal_user', 'api_partner']);
    }

    public static function api(): ApiSchema
    {
        return ApiSchema::for(static::class)->guard();
    }
}

final class ApiPermPageResource extends Resource
{
    public static string $model = ApiPermTestModel::class;

    public static function permission(): PermissionSchema
    {
        return PermissionSchema::for(static::class)->api('index', ['api_internal_user']);
    }

    public static function api(): ApiSchema
    {
        return ApiSchema::for(static::class)->allowPageToken();
    }
}

final class MemoryUsers implements ApiUserProvider
{
    /** @var array<string, object> */
    public array $users = [];

    public function user(int|string $id): ?object
    {
        return $this->users[(string) $id] ?? (object) ['exists' => false];
    }
}

function codeOf(callable $fn): ?int
{
    try {
        $fn();
    } catch (EndpointException $e) {
        return $e->getCode();
    }

    return null;
}

function messageOf(callable $fn): ?string
{
    try {
        $fn();
    } catch (EndpointException $e) {
        return $e->getMessage();
    }

    return null;
}

function apiRequest(string $method, string $path): void
{
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['HTTP_HOST'] = 'example.test';
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $path;
    unset($_SERVER['HTTP_AUTHORIZATION'], $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
}

# Permessi vuoti

check('Permessi api vuoti: authorities() lancia 403', function () {
    return codeOf(fn () => ResourceApiAccess::authorities(ApiPermEmptyResource::class, 'index')) === 403;
});

check('Il default della PermissionSchema non apre nessuna azione api', function () {
    foreach (['index', 'store', 'show', 'update', 'destroy'] as $action) {
        if (ResourceApiAccess::declared(ApiPermEmptyResource::class, $action) !== []) {
            return false;
        }
    }

    return true;
});

check('Controller: azione senza permessi => 403 prima di leggere il bearer', function () {
    apiRequest('GET', '/api/resource/api-perm-empty/');

    return codeOf(fn () => ResourceApiController::forResource(ApiPermEmptyResource::class)->handle('index')) === 403;
});

check('Controller: azione con permessi arriva a Endpoint (401 bearer mancante)', function () {
    apiRequest('POST', '/api/resource/api-perm-store/');

    return messageOf(fn () => ResourceApiController::forResource(ApiPermStoreResource::class)->handle('store'))
        === 'Bearer token mancante!';
});

check('Controller: RequestResource-like nega le azioni non dichiarate (index => 403)', function () {
    apiRequest('GET', '/api/resource/api-perm-store/');

    return codeOf(fn () => ResourceApiController::forResource(ApiPermStoreResource::class)->handle('index')) === 403;
});

check('declared() normalizza come Endpoint: lista, chiavi associative, scarti', function () {
    return ResourceApiAccess::declared(ApiPermGuardedResource::class, 'show') === ['api_internal_user', 'api_partner']
        && ResourceApiAccess::declared('stdClass', 'show') === [];
});

check('Registrar: rotta abilitata ma senza permessi non registrata e loggata', function () {
    $log = tempnam(sys_get_temp_dir(), 'wi-log');
    $previous = ini_set('error_log', $log);
    $method = new ReflectionMethod(ResourceRouteRegistrar::class, 'apiActionAllowed');
    $routes = (array) ApiPermStoreResource::apiSchema()->get('routes');

    $index = $method->invoke(null, ApiPermStoreResource::class, 'api-perm-store', 'index', $routes);
    $store = $method->invoke(null, ApiPermStoreResource::class, 'api-perm-store', 'store', $routes);
    $disabled = $method->invoke(null, ApiPermStoreResource::class, 'api-perm-store', 'store', ['store' => false]);

    ini_set('error_log', (string) $previous);
    $written = (string) file_get_contents($log);
    @unlink($log);

    return $index === false && $store === true && $disabled === false
        && str_contains($written, 'api.resource.api-perm-store.index non registrata')
        && !str_contains($written, '.store non registrata');
});

# Guard

check('guard() senza argomento dichiara api_internal_user', function () {
    return ApiPermGuardedResource::apiSchema()->declaredGuard() === 'api_internal_user'
        && ApiPermStoreResource::apiSchema()->declaredGuard() === null;
});

check('Guard dichiarato: utente senza la guard => 403', function () {
    return codeOf(fn () => ResourceApiAccess::requireGuard(ApiPermGuardedResource::class, ['api_partner'])) === 403;
});

check('Guard dichiarato: utente con permesso e guard => ammesso', function () {
    return codeOf(fn () => ResourceApiAccess::requireGuard(ApiPermGuardedResource::class, ['api_partner', 'api_internal_user'])) === null;
});

check('Guard non dichiarato: nessun requisito in piu', function () {
    return codeOf(fn () => ResourceApiAccess::requireGuard(ApiPermStoreResource::class, ['api_partner'])) === null;
});

check('guard(\'\') non dichiara nulla', function () {
    return ApiSchema::for(ApiPermStoreResource::class)->guard('')->declaredGuard() === null;
});

# Token di pagina

$users = new MemoryUsers();
$systemToken = ApiTokens::issue(1, 3600, KEY, NOW);
$users->users['1'] = (object) [
    'id' => 1,
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
$verifier = new BearerVerifier(KEY, $users, true);
$page = ApiTokens::page((array) $users->users['1']->api_internal_user, 3600, KEY, NOW);

check('Senza opt-in il token di pagina e rifiutato sulle rotte Resource (401)', function () use ($verifier, $page) {
    $flag = ResourceApiAccess::allowsPageToken(ApiPermStoreResource::class);

    return $flag === false
        && messageOf(fn () => $verifier->verify($page, ['api_internal_user'], null, null, $flag, NOW + 10))
            === 'Token di pagina non ammesso per questo endpoint';
});

check('Con allowPageToken() il token di pagina e accettato', function () use ($verifier, $page) {
    $flag = ResourceApiAccess::allowsPageToken(ApiPermPageResource::class);

    return $flag === true
        && $verifier->verify($page, ['api_internal_user'], null, null, $flag, NOW + 10)->isPageToken();
});

check('Il bearer API classico resta valido senza opt-in', function () use ($verifier, $systemToken) {
    return !$verifier->verify($systemToken, ['api_internal_user'], null, null, false, NOW)->isPageToken();
});

check('Il controller passa a Endpoint il flag allowsPageToken e applica la guard', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/class/Api/Support/ResourceApiController.php');
    $access = strpos($source, 'ResourceApiAccess::authorities(');
    $new = strpos($source, 'new Endpoint(');
    $guard = strpos($source, 'ResourceApiAccess::requireGuard(');

    return $access !== false && $new !== false && $guard !== false
        && $access < $new && $new < $guard
        && str_contains($source, 'ResourceApiAccess::allowsPageToken($this->resourceClass)');
});

summary();
