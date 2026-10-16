<?php
/** php tests/App/Permission/AccessTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Model;
use Wonder\App\Module\ConfigRepository;
use Wonder\App\Permission\Access;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PermissionSchema;

Access::reset(loadModules: false);

check('una lista vuota lascia solo admin', function (): bool {
    Access::define('log', []);

    return Access::roles('log', 'view') === ['admin'] && Access::roles('log', 'delete') === ['admin'];
});

check('una lista vale per tutte le azioni', function (): bool {
    Access::define('catalogo', ['administrator']);

    return Access::roles('catalogo', 'edit') === ['admin', 'administrator'];
});

check('una mappa usa * per le azioni non indicate', function (): bool {
    Access::define('vendite', ['*' => ['administrator', 'sales'], 'delete' => ['administrator']]);

    return Access::roles('vendite', 'create') === ['admin', 'administrator', 'sales']
        && Access::roles('vendite', 'delete') === ['admin', 'administrator'];
});

check('set() sostituisce la regola intera, admin resta', function (): bool {
    Access::define('avvisi', ['administrator']);
    Access::set('avvisi', ['view' => ['administrator']]);

    return Access::roles('avvisi', 'view') === ['admin', 'administrator']
        && Access::roles('avvisi', 'edit') === ['admin'];
});

check('un\'azione sconosciuta è un errore', function (): bool {
    try {
        Access::define('x', ['publish' => ['administrator']]);
    } catch (RuntimeException) {
        return true;
    }

    return false;
});

check('un\'area non definita è un errore', function (): bool {
    try {
        Access::roles('inesistente');
    } catch (RuntimeException) {
        return true;
    }

    return false;
});

check('backendActions espande view/create/edit/delete', function (): bool {
    $actions = Access::backendActions('vendite');

    return $actions['list'] === $actions['view']
        && $actions['store'] === $actions['create']
        && $actions['update'] === $actions['edit']
        && $actions['delete'] === ['admin', 'administrator'];
});

check('viewers unisce i ruoli di più aree', fn (): bool =>
    Access::viewers('log', 'vendite') === ['admin', 'administrator', 'sales']
);

check('override del modulo: un ruolo tolto resta tolto', function (): bool {
    $default = ['features' => ['a' => true], 'permissions' => [
        'spedizioni' => ['administrator'],
        'catalogo' => ['view' => ['administrator', 'sales'], 'edit' => ['administrator']],
    ]];
    $merged = ConfigRepository::merge($default, ['permissions' => ['catalogo' => ['view' => ['administrator']]]]);

    return $merged['permissions']['catalogo'] === ['view' => ['administrator']]
        && $merged['permissions']['spedizioni'] === ['administrator']
        && $merged['features'] === ['a' => true];
});

check('override del modulo: lista più corta del default', function (): bool {
    $merged = ConfigRepository::merge(
        ['permissions' => ['x' => ['administrator', 'sales']]],
        ['permissions' => ['x' => ['sales']]]
    );

    return $merged['permissions']['x'] === ['sales'];
});

final class AccessTestModel extends Model
{
    public static string $table = 'access_test';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class AccessTestResource extends Resource
{
    public static string $model = AccessTestModel::class;
}

check('PermissionSchema::access e NavigationSchema::access', function (): bool {
    $backend = PermissionSchema::for(AccessTestResource::class)->access('avvisi')->get('backend');
    $nav = NavigationSchema::for(AccessTestResource::class)->access('avvisi')->get('authority');

    return $backend['list'] === ['admin', 'administrator']
        && $backend['delete'] === ['admin']
        && $nav === ['admin', 'administrator'];
});

check('PermissionSchema::access limita le azioni indicate', function (): bool {
    $backend = PermissionSchema::for(AccessTestResource::class)->access('avvisi', ['list', 'view'])->get('backend');

    return $backend['list'] === ['admin', 'administrator']
        && $backend['view'] === ['admin', 'administrator']
        && $backend['create'] === []
        && $backend['delete'] === [];
});

summary();
