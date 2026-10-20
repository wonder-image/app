<?php
/** php tests/Security/SingletonApiSchemaTest.php */
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

use Wonder\App\ResourceRouteRegistrar;
use Wonder\App\Resources\Config\{ AnalyticsResource, SeoResource };
use Wonder\App\Resources\Media\LogoResource;

# Le Resource singleton del core espongono via API solo show/update, come
# SingletonResource; le azioni di scrittura readonly non passano dal controllo
# dei permessi (niente log per route che non verrebbero registrate).

foreach ([SeoResource::class, AnalyticsResource::class, LogoResource::class] as $class) {
    check(basename(str_replace('\\', '/', $class)).': apiSchema() espone solo show e update', function () use ($class) {
        $routes = array_keys(array_filter((array) $class::apiSchema()->get('routes')));

        return $routes === ['show', 'update'];
    });
}

check('Registrar: !$readonly valutato prima di $allowed() per store, update e destroy', function () {
    $source = (string) file_get_contents((new ReflectionClass(ResourceRouteRegistrar::class))->getFileName());

    foreach (['store', 'update', 'destroy'] as $action) {
        if (!str_contains($source, "!empty(\$routes['{$action}']) && !\$readonly && \$allowed('{$action}')")) {
            return false;
        }
    }

    return true;
});

summary();
