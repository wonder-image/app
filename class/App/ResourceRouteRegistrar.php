<?php

namespace Wonder\App;

use Wonder\Backend\Support\ReadonlyFields;
use Wonder\Http\Route;

final class ResourceRouteRegistrar
{
    public static function registerBackend(string $rootApp): void
    {
        Route::name('resource.')
            ->group(function () use ($rootApp) {
                foreach (ResourceRegistry::all() as $slug => $resourceClass) {
                    $pages = (array) $resourceClass::pageSchema()->get('pages');
                    $permissions = (array) $resourceClass::permissionSchema()->get('backend');
                    $path = trim((string) $resourceClass::path(), '/');
                    $readonly = $resourceClass::isReadonly();
                    // In sola lettura la modifica resta possibile se la Resource
                    // dichiara dei campi modificabili (es. orari e chiusure).
                    $updatable = ReadonlyFields::allowsUpdate(
                        $readonly,
                        $resourceClass::editableWhenReadonly()
                    );

                    if ($path === '') {
                        continue;
                    }

                    Route::name($slug.'.')
                        ->prefix('/'.$path)
                        ->group(function () use ($rootApp, $slug, $pages, $permissions, $resourceClass, $readonly, $updatable) {
                            // Pagina-form: un solo indirizzo, in lettura e in
                            // salvataggio; nessuna route CRUD.
                            if ($resourceClass::isFormPage()) {
                                Route::get('/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'form',
                                ])->name('form')->permit($permissions['edit'] ?? $permissions['list'] ?? []);

                                if ($updatable) {
                                    Route::post('/', $rootApp.'/http/backend/resource/index.php', [
                                        'resource' => $slug,
                                        'resource_action' => 'submit',
                                    ])->name('submit')->permit($permissions['update'] ?? $permissions['edit'] ?? []);
                                }
                            }

                            if (!empty($pages['list'])) {
                                Route::get('/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'list',
                                ])->name('list')->permit($permissions['list'] ?? []);
                            }

                            if (!empty($pages['create']) && !$readonly && !$resourceClass::hasCustomBackendPage('create')) {
                                Route::get('/create/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'create',
                                ])->name('create')->permit($permissions['create'] ?? []);
                            }

                            if (!empty($pages['store']) && !$readonly && !$resourceClass::hasCustomBackendPage('store')) {
                                Route::post('/create/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'store',
                                ])->name('store')->permit($permissions['store'] ?? []);
                            }

                            if (!empty($pages['view']) && !$resourceClass::hasCustomBackendPage('view')) {
                                Route::get('/{id}/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'view',
                                ])->name('view')
                                    ->permit($permissions['view'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            if (!empty($pages['edit']) && !$resourceClass::hasCustomBackendPage('edit')) {
                                Route::get('/{id}/edit/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'edit',
                                ])->name('edit')
                                    ->permit($permissions['edit'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            if (!empty($pages['update']) && $updatable && !$resourceClass::hasCustomBackendPage('update')) {
                                Route::post('/{id}/edit/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'update',
                                ])->name('update')
                                    ->permit($permissions['update'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            if (!empty($pages['delete']) && !$readonly && !$resourceClass::hasCustomBackendPage('delete')) {
                                Route::post('/{id}/delete/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'delete',
                                ])->name('delete')
                                    ->permit($permissions['delete'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            // Export tabella: route attivata sempre, il
                            // ResourceDownloadController fa il check
                            // ulteriore sul `tableLayoutSchema()->download`.
                            // Reusa i permessi di `list` (chi vede la
                            // tabella può anche esportarla).
                            Route::get('/export/{format}/', $rootApp.'/http/backend/resource/export.php', [
                                'resource' => $slug,
                                'resource_action' => 'export',
                            ])->name('export')
                                ->permit($permissions['list'] ?? [])
                                ->where('format', '[a-z]+');

                            $resourceClass::registerBackendRoutes($rootApp, $slug);
                        });
                }
            });
    }

    public static function registerApi(string $rootApp): void
    {
        Route::name('api.resource.')
            ->prefix('/resource')
            ->group(function () use ($rootApp) {
                foreach (ResourceRegistry::all() as $slug => $resourceClass) {
                    $apiSchema = $resourceClass::apiSchema();

                    if (!$apiSchema->get('enabled')) {
                        continue;
                    }

                    $routes = (array) $apiSchema->get('routes');
                    $permissions = (array) $resourceClass::permissionSchema()->get('api');
                    $readonly = $resourceClass::isReadonly();

                    Route::name($slug.'.')
                        ->prefix('/'.$slug)
                        ->group(function () use ($rootApp, $slug, $routes, $permissions, $resourceClass, $readonly) {
                            if (!empty($routes['index'])) {
                                Route::get('/', $rootApp.'/http/api/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'index',
                                ])->name('index')->permit($permissions['index'] ?? []);
                            }

                            if (!empty($routes['store']) && !$readonly) {
                                Route::post('/', $rootApp.'/http/api/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'store',
                                ])->name('store')->permit($permissions['store'] ?? []);
                            }

                            if (!empty($routes['show'])) {
                                Route::get('/{id}/', $rootApp.'/http/api/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'show',
                                ])->name('show')
                                    ->permit($permissions['show'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            if (!empty($routes['update']) && !$readonly) {
                                Route::put('/{id}/', $rootApp.'/http/api/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'update',
                                ])->name('update')
                                    ->permit($permissions['update'] ?? [])
                                    ->where('id', '[0-9]+');

                                Route::patch('/{id}/', $rootApp.'/http/api/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'update',
                                ])->name('update.patch')
                                    ->permit($permissions['update'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            if (!empty($routes['destroy']) && !$readonly) {
                                Route::delete('/{id}/', $rootApp.'/http/api/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'destroy',
                                ])->name('destroy')
                                    ->permit($permissions['destroy'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            $resourceClass::registerApiRoutes($rootApp, $slug);
                        });
                }
            });
    }
}
