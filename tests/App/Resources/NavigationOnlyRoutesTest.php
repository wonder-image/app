<?php
/** php tests/App/Resources/NavigationOnlyRoutesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../app/function/helper.php';

use Wonder\App\Resources\Scheduler\DashboardResource;
use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\TranslationBootstrap;
use Wonder\Http\Route;

$root = dirname(__DIR__, 3);
$rootApp = $root . '/app';

TranslationBootstrap::preload($rootApp, $root);

$routes = Route::load([$rootApp . '/config/routes/route.backend.php'], ['ROOT_APP' => $rootApp, 'ROOT' => $root]);

$paths = static function (string $needle) use ($routes): array {
    return array_values(array_filter(
        array_map(static fn (array $route): string => (string) ($route['path'] ?? ''), $routes),
        static fn (string $path): bool => str_contains($path, $needle)
    ));
};

check('le pagine non-CRUD non hanno la route di export', function () use ($paths) {
    foreach (['/app/scheduler/export/', '/app/docs/components/export/', '/app/media/upload-massive/export/', '/app/config/sql-download/export/', '/home/export/'] as $needle) {
        if ($paths($needle) !== []) {
            return false;
        }
    }

    return true;
});

check('le Resource con tabella la tengono (schedules, runs)', function () use ($paths) {
    return $paths('/app/scheduler/schedules/export/') !== [] && $paths('/app/scheduler/runs/export/') !== [];
});

check('il Riepilogo dello scheduler ha una sola route GET, quella esplicita', function () use ($routes) {
    $get = array_filter($routes, static fn (array $route): bool => ($route['path'] ?? '') === '/backend/app/scheduler/'
        && ($route['method'] ?? '') === 'GET');

    return count($get) === 1 && array_values($get)[0]['permit'] === ['admin'];
});

check('NavigationOnlyResource disabilita anche la lista', function () {
    return DashboardResource::pageSchema()->get('pages')['list'] === false
        && is_subclass_of(DashboardResource::class, NavigationOnlyResource::class);
});

summary();
