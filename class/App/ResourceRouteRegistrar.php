<?php

namespace Wonder\App;

use Wonder\Http\Route;

final class ResourceRouteRegistrar
{
    public static function registerBackend(string $rootApp): void
    {
        Route::name('resource.')
            ->prefix('/resource')
            ->group(function () use ($rootApp) {
                foreach (ResourceRegistry::all() as $slug => $resourceClass) {
                    $pages = (array) ($resourceClass::pageSchema()['pages'] ?? []);
                    $permissions = (array) ($resourceClass::permissionSchema()['backend'] ?? []);

                    Route::name($slug.'.')
                        ->prefix('/'.$slug)
                        ->group(function () use ($rootApp, $slug, $pages, $permissions) {
                            if (!empty($pages['list'])) {
                                Route::get('/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'list',
                                ])->name('list')->permit($permissions['list'] ?? []);
                            }

                            if (!empty($pages['create'])) {
                                Route::get('/create/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'create',
                                ])->name('create')->permit($permissions['create'] ?? []);
                            }

                            if (!empty($pages['store'])) {
                                Route::post('/create/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'store',
                                ])->name('store')->permit($permissions['store'] ?? []);
                            }

                            if (!empty($pages['view'])) {
                                Route::get('/{id}/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'view',
                                ])->name('view')
                                    ->permit($permissions['view'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            if (!empty($pages['edit'])) {
                                Route::get('/{id}/edit/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'edit',
                                ])->name('edit')
                                    ->permit($permissions['edit'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            if (!empty($pages['update'])) {
                                Route::post('/{id}/edit/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'update',
                                ])->name('update')
                                    ->permit($permissions['update'] ?? [])
                                    ->where('id', '[0-9]+');
                            }

                            if (!empty($pages['delete'])) {
                                Route::post('/{id}/delete/', $rootApp.'/http/backend/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'delete',
                                ])->name('delete')
                                    ->permit($permissions['delete'] ?? [])
                                    ->where('id', '[0-9]+');
                            }
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

                    if (empty($apiSchema['enabled'])) {
                        continue;
                    }

                    $routes = (array) ($apiSchema['routes'] ?? []);
                    $permissions = (array) ($resourceClass::permissionSchema()['api'] ?? []);

                    Route::name($slug.'.')
                        ->prefix('/'.$slug)
                        ->group(function () use ($rootApp, $slug, $routes, $permissions) {
                            if (!empty($routes['index'])) {
                                Route::get('/', $rootApp.'/http/api/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'index',
                                ])->name('index')->permit($permissions['index'] ?? []);
                            }

                            if (!empty($routes['store'])) {
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

                            if (!empty($routes['update'])) {
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

                            if (!empty($routes['destroy'])) {
                                Route::delete('/{id}/', $rootApp.'/http/api/resource/index.php', [
                                    'resource' => $slug,
                                    'resource_action' => 'destroy',
                                ])->name('destroy')
                                    ->permit($permissions['destroy'] ?? [])
                                    ->where('id', '[0-9]+');
                            }
                        });
                }
            });
    }
}
