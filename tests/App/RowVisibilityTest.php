<?php
/** php tests/App/RowVisibilityTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Api\Endpoint;
use Wonder\Api\EndpointException;
use Wonder\Api\Support\ResourceApiController;
use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\Visibility\ResourceRows;
use Wonder\App\Visibility\RowVisibility;
use Wonder\Sql\Query;

// Query che esegue su SQLite in memoria: le condizioni girano davvero.
final class SqliteQuery extends Query
{
    public static ?PDO $pdo = null;
    public static array $log = [];

    public static function make(): self
    {
        $query = (new ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $escaper = (new ReflectionProperty(Query::class, 'escaper'));
        $escaper->setValue($query, Closure::fromCallable(static fn (string $value): string => str_replace("'", "''", $value)));

        return $query;
    }

    public function Select(string|array $table, string|array|null $condition = null, string|int|null $limit = null, ?string $order = null, ?string $orderDirection = null, string|array $attributes = '*')
    {
        $where = $condition === null || $condition === [] ? '' : ' WHERE '.RowVisibility::toSql($condition);
        $columns = is_array($attributes) ? implode(',', $attributes) : $attributes;
        $sql = "SELECT {$columns} FROM `{$table}`{$where} ORDER BY id".($limit !== null ? " LIMIT {$limit}" : '');
        self::$log[] = $sql;
        $rows = self::$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        return (object) [
            'success' => true,
            'exists' => $rows !== [],
            'row' => (string) $limit === '1' ? ($rows[0] ?? []) : $rows,
        ];
    }
}

final class VisOrderModel extends Model
{
    public static string $table = 'vis_orders';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
    public static function query(): Query { return SqliteQuery::make(); }
}

final class VisNoteModel extends Model
{
    public static string $table = 'vis_notes';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
    public static function query(): Query { return SqliteQuery::make(); }
}

// Resource di prova con la regola: ogni agente vede solo i propri ordini.
final class VisOrderResource extends Resource
{
    public static string $model = VisOrderModel::class;

    public static function rowVisibility(object $user): string|array|null
    {
        return [ 'agent_id' => (int) ($user->id ?? 0) ];
    }
}

final class VisNoteResource extends Resource
{
    public static string $model = VisNoteModel::class;
}

$pdo = new PDO('sqlite::memory:');
$pdo->exec("CREATE TABLE vis_orders (id INTEGER PRIMARY KEY, agent_id INTEGER, title TEXT, deleted TEXT DEFAULT 'false')");
$pdo->exec("INSERT INTO vis_orders VALUES (1, 10, 'Rossi', 'false'), (2, 20, 'Bianchi', 'false'), (3, 10, 'Cancellato', 'true'), (4, NULL, 'Senza agente', 'false'), (5, 10, 'Verdi', 'false')");
$pdo->exec("CREATE TABLE vis_notes (id INTEGER PRIMARY KEY, title TEXT, deleted TEXT DEFAULT 'false')");
$pdo->exec("INSERT INTO vis_notes VALUES (1, 'a', 'false'), (2, 'b', 'false')");
SqliteQuery::$pdo = $pdo;

$boot = static function (): void {
    RowVisibility::reset();
    RowVisibility::useResources([ VisOrderResource::class, VisNoteResource::class ]);
    RowVisibility::useQuery(SqliteQuery::make());
};
$boot();

$agent = (object) [ 'id' => 10, 'username' => 'agente', 'authority' => [ 'agent' ] ];
$other = (object) [ 'id' => 20, 'username' => 'altro', 'authority' => '["agent"]' ];
$admin = (object) [ 'id' => 1, 'username' => 'admin', 'authority' => [ 'admin' ] ];
$administrator = (object) [ 'id' => 2, 'username' => 'boss', 'authority' => [ 'administrator' ] ];
$ids = static fn (mixed $rows): array => array_map('intval', array_column(is_array($rows) && !array_is_list($rows) ? [ $rows ] : (array) $rows, 'id'));
$where = static fn (string $sql): array => array_map('intval', array_column($pdo->query("SELECT id FROM vis_orders WHERE {$sql} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC), 'id'));

echo "Area senza regola\n";

check('tabella senza regola: apply restituisce la condizione identica', static function () use ($agent): bool {
    return !RowVisibility::hasRule('vis_notes')
        && RowVisibility::conditionFor('vis_notes', $agent) === null
        && RowVisibility::apply('vis_notes', [ 'id' => 1 ], $agent) === [ 'id' => 1 ]
        && RowVisibility::apply('vis_notes', "`title` = 'a'", $agent) === "`title` = 'a'"
        && RowVisibility::apply('vis_notes', null, $agent) === null
        && RowVisibility::applySql('vis_notes', '', $agent) === ''
        && RowVisibility::applySql('vis_notes', " AND `x` = 1", $agent) === " AND `x` = 1";
});

check('tabella senza regola: visible() non interroga il database', static function () use ($agent): bool {
    SqliteQuery::$log = [];

    return RowVisibility::visible('vis_notes', 999, $agent) && SqliteQuery::$log === [];
});

check('tabella senza regola: ResourceRows::find legge come prima', static function () use ($agent): bool {
    return ResourceRows::conditionForId(VisNoteResource::class, 2, $agent) === array_merge(VisNoteResource::getQuery('condition'), [ 'id' => 2 ])
        && (int) (ResourceRows::find(VisNoteResource::class, 2, $agent)['id'] ?? 0) === 2;
});

echo "\nRegola della Resource\n";

check('la regola vale per l\'agente e passa dall\'hook', static function () use ($agent, $other): bool {
    return RowVisibility::hasRule('vis_orders')
        && RowVisibility::conditionFor('vis_orders', $agent) === [ 'agent_id' => 10 ]
        && RowVisibility::conditionFor('vis_orders', $other) === [ 'agent_id' => 20 ];
});

check('admin, administrator e utente null vedono tutto', static function () use ($admin, $administrator): bool {
    return RowVisibility::conditionFor('vis_orders', $admin) === null
        && RowVisibility::conditionFor('vis_orders', $administrator) === null
        && RowVisibility::conditionFor('vis_orders', null) === null;
});

check('lista: la regola entra nella query di SSP e nel conteggio', static function () use ($agent, $admin, $where): bool {
    $agentSql = RowVisibility::applySql('vis_orders', "`deleted` = 'false'", $agent);

    return $where($agentSql) === [ 1, 5 ]
        && $where(RowVisibility::applySql('vis_orders', "`deleted` = 'false'", $admin)) === [ 1, 2, 4, 5 ];
});

check('lista con ricerca: la regola resta in AND con la condizione', static function () use ($agent, $where): bool {
    return $where(RowVisibility::applySql('vis_orders', "`title` LIKE '%i%' OR `title` LIKE '%B%'", $agent)) === [ 1, 5 ];
});

check('URL diretto: la riga di un altro agente non si apre (null → 404)', static function () use ($agent, $other): bool {
    return ResourceRows::find(VisOrderResource::class, 2, $agent) === null
        && (int) (ResourceRows::find(VisOrderResource::class, 1, $agent)['id'] ?? 0) === 1
        && ResourceRows::find(VisOrderResource::class, 1, $other) === null;
});

check('URL diretto: il soft delete resta applicato con la regola', static function () use ($agent): bool {
    return ResourceRows::find(VisOrderResource::class, 3, $agent) === null;
});

check('URL diretto: l\'admin apre la riga di qualunque agente', static function () use ($admin): bool {
    return (int) (ResourceRows::find(VisOrderResource::class, 2, $admin)['id'] ?? 0) === 2
        && (int) (ResourceRows::find(VisOrderResource::class, 4, $admin)['id'] ?? 0) === 4;
});

check('azioni per id: visible() nega la riga di un altro', static function () use ($agent, $admin): bool {
    return RowVisibility::visible('vis_orders', 1, $agent)
        && !RowVisibility::visible('vis_orders', 2, $agent)
        && !RowVisibility::visible('vis_orders', 4, $agent)
        && RowVisibility::visible('vis_orders', 2, $admin);
});

check('MCP: lo scope dell\'utente filtra find, findById e count', static function () use ($agent, $admin, $ids): bool {
    $scope = RowVisibility::for($agent);

    return $ids($scope->find(VisOrderModel::class)) === [ 1, 5 ]
        && $scope->findById(VisOrderModel::class, 2) === null
        && $scope->count(VisOrderModel::class) === 2
        && $scope->count(VisOrderModel::class, [ 'title' => 'Verdi' ]) === 1
        && RowVisibility::for($admin)->count(VisOrderModel::class) === 4;
});

check('utente corrente: as() vince su $USER e si annida', static function () use ($agent, $other): bool {
    $GLOBALS['USER'] = $agent;

    try {
        $outer = RowVisibility::current()->user();
        $inner = RowVisibility::as($other, static fn (): ?object => RowVisibility::as(null, static fn () => RowVisibility::currentUser()) ?? RowVisibility::currentUser());

        return $outer === $agent && $inner === $other && RowVisibility::currentUser() === $agent;
    } finally {
        unset($GLOBALS['USER']);
    }
});

echo "\nAPI generate\n";

$endpoint = static function (?object $user, string $kind = 'bearer'): Endpoint {
    $endpoint = (new ReflectionClass(Endpoint::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(Endpoint::class, 'user'))->setValue($endpoint, $user);
    (new ReflectionProperty(Endpoint::class, 'tokenKind'))->setValue($endpoint, $kind);

    return $endpoint;
};
$apiUser = static function (Endpoint $endpoint): ?object {
    $controller = (new ReflectionClass(ResourceApiController::class))->newInstanceWithoutConstructor();

    return (new ReflectionMethod(ResourceApiController::class, 'visibilityUser'))->invoke($controller, $endpoint);
};

check('API: il bearer di un utente applica la sua regola', static function () use ($endpoint, $apiUser, $agent): bool {
    $user = $apiUser($endpoint($agent));

    return $user === $agent && ResourceRows::find(VisOrderResource::class, 2, $user) === null;
});

check('API: il bearer @system legge senza restrizioni', static function () use ($endpoint, $apiUser): bool {
    return $apiUser($endpoint((object) [ 'id' => 3, 'username' => '@system', 'authority' => [ 'api_internal_user' ] ])) === null;
});

check('API: il token di pagina senza sessione è l\'anonimo e non vede righe', static function () use ($endpoint, $apiUser): bool {
    $user = $apiUser($endpoint((object) [ 'username' => '@system' ], 'page'));

    return is_object($user) && (int) $user->id === 0
        && ResourceRows::find(VisOrderResource::class, 1, $user) === null
        && ResourceRows::find(VisOrderResource::class, 4, $user) === null;
});

check('API: la riga nascosta dà EndpointException 404', static function () use ($agent): bool {
    $controller = (new ReflectionClass(ResourceApiController::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(ResourceApiController::class, 'resourceClass'))->setValue($controller, VisOrderResource::class);

    try {
        (new ReflectionMethod(ResourceApiController::class, 'resourceRow'))->invoke($controller, 2, $agent, [ '*' ]);
    } catch (EndpointException $exception) {
        return $exception->getCode() === 404;
    }

    return false;
});

echo "\nRegole d'area e forme della condizione\n";

check('regola d\'area con ruolo "vede tutto" personalizzato', static function () use ($boot, $where): bool {
    $boot();
    RowVisibility::rule('vis_notes', static fn (object $user): array => [ 'id' => 1 ], [ 'capo' ]);
    $capo = (object) [ 'id' => 7, 'authority' => [ 'capo' ] ];
    $admin = (object) [ 'id' => 1, 'authority' => [ 'admin' ] ];
    $ok = RowVisibility::conditionFor('vis_notes', $capo) === null
        && RowVisibility::conditionFor('vis_notes', $admin) === [ 'id' => 1 ];
    $boot();

    return $ok;
});

check('regola d\'area e della Resource vanno in AND', static function () use ($boot, $agent, $where): bool {
    $boot();
    RowVisibility::rule('vis_orders', static fn (object $user): string => "`title` <> 'Verdi'");
    $sql = RowVisibility::conditionFor('vis_orders', $agent);
    $boot();

    return is_string($sql) && $where($sql) === [ 1, 3 ];
});

check('IN, IS NULL e lista vuota', static function () use ($boot, $agent, $where): bool {
    $results = [];

    foreach ([ [ 'agent_id' => [ 10, 20 ] ], [ 'agent_id' => null ], [ 'agent_id' => [] ] ] as $rule) {
        $boot();
        RowVisibility::rule('vis_notes', static fn (): array => $rule);
        $results[] = $where(RowVisibility::toSql(RowVisibility::conditionFor('vis_notes', $agent)));
    }

    $boot();

    return $results === [ [ 1, 2, 3, 5 ], [ 4 ], [] ];
});

check('valori con apice bindati, mai interpolati', static function () use ($boot, $agent, $where): bool {
    $boot();
    RowVisibility::rule('vis_notes', static fn (): array => [ 'title' => "x' OR '1'='1" ]);
    $sql = RowVisibility::toSql(RowVisibility::conditionFor('vis_notes', $agent));
    $boot();

    return $where($sql) === [];
});

check('array + array senza chiavi in comune resta array (per Model::find)', static function () use ($agent): bool {
    return RowVisibility::apply('vis_orders', [ 'title' => 'Rossi' ], $agent) === [ 'title' => 'Rossi', 'agent_id' => 10 ]
        && is_string(RowVisibility::apply('vis_orders', [ 'agent_id' => 20 ], $agent));
});

check('chiave in comune: AND in SQL, nessuna riga', static function () use ($agent, $where): bool {
    return $where(RowVisibility::toSql(RowVisibility::apply('vis_orders', [ 'agent_id' => 20 ], $agent))) === [];
});

check('regola che nomina deleted, stringa vuota o tipo errato: LogicException', static function () use ($boot, $agent): bool {
    $failures = 0;

    foreach ([ [ 'deleted' => 'false' ], "`deleted` = 'true'", '  ', 42 ] as $rule) {
        $boot();
        RowVisibility::rule('vis_notes', static fn () => $rule);

        try {
            RowVisibility::conditionFor('vis_notes', $agent);
        } catch (LogicException) {
            $failures++;
        } catch (TypeError) {
            $failures++;
        }
    }

    $boot();

    return $failures === 4;
});

check('authorities() accetta array, JSON e stringa singola', static function (): bool {
    return RowVisibility::authorities((object) [ 'authority' => [ 'a' ] ]) === [ 'a' ]
        && RowVisibility::authorities((object) [ 'authority' => '["a","b"]' ]) === [ 'a', 'b' ]
        && RowVisibility::authorities((object) [ 'authority' => 'admin' ]) === [ 'admin' ]
        && RowVisibility::authorities((object) []) === [];
});

summary();
