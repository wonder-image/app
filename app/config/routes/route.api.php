<?php

use Wonder\Http\Route;

Route::area('api')
    ->prefix('/api')
    ->response('json')
    ->group(function () use ($ROOT_APP, $ROOT) {

        Route::post('/alert/', $ROOT_APP.'/http/api/alert.php')
            ->name('alert');

        Route::post('/states/', $ROOT_APP.'/http/api/states.php')
            ->name('states')
            ->frontend();

        Route::get('/export/', $ROOT_APP.'/http/api/export.php')
            ->name('export')
            ->backend()
            ->guarded()
            ->permit(['admin']);

        Route::name('app.')
            ->prefix('/app')
            ->group(function () use ($ROOT_APP) {

                Route::post('/update/', $ROOT_APP.'/http/api/app/update.php')
                    ->name('update');

            });

        Route::name('backend.')
            ->prefix('/backend')
            ->backend()
            ->group(function () use ($ROOT_APP) {

                Route::post('/alert/', $ROOT_APP.'/http/api/backend/alert.php')
                    ->name('alert');

                Route::post('/list/table/', $ROOT_APP.'/http/api/backend/list-table.php')
                    ->name('list.table');

                Route::post('/change/boolean/', $ROOT_APP.'/http/api/backend/change-boolean.php')
                    ->name('change.boolean')
                    ->guarded()
                    ->permit([]);

                Route::post('/active/', $ROOT_APP.'/http/api/backend/change-boolean.php', [
                    'legacy_column' => 'active',
                ])->name('active')
                    ->guarded()
                    ->permit([]);

                Route::post('/visible/', $ROOT_APP.'/http/api/backend/change-boolean.php', [
                    'legacy_column' => 'visible',
                ])->name('visible')
                    ->guarded()
                    ->permit([]);

                Route::post('/delete/', $ROOT_APP.'/http/api/backend/delete.php')
                    ->name('delete')
                    ->guarded()
                    ->permit([]);

                Route::post('/authority/', $ROOT_APP.'/http/api/backend/authority.php')
                    ->name('authority')
                    ->guarded()
                    ->permit([]);

                Route::post('/move/', $ROOT_APP.'/http/api/backend/move.php')
                    ->name('move')
                    ->guarded()
                    ->permit([]);

                Route::post('/file/delete/', $ROOT_APP.'/http/api/backend/file-delete.php')
                    ->name('file.delete')
                    ->guarded()
                    ->permit([]);

                Route::post('/file/move/', $ROOT_APP.'/http/api/backend/file-move.php')
                    ->name('file.move')
                    ->guarded()
                    ->permit([]);

                Route::post('/delete-icon/', $ROOT_APP.'/http/api/backend/delete-icon.php')
                    ->name('delete-icon')
                    ->guarded()
                    ->permit([]);

                Route::post('/editorjs/file/', $ROOT_APP.'/http/api/backend/editorjs-file.php')
                    ->name('editorjs.file');

                Route::post('/editorjs/image/', $ROOT_APP.'/http/api/backend/editorjs-image.php')
                    ->name('editorjs.image');

            });

        Route::name('frontend.')
            ->prefix('/frontend')
            ->frontend()
            ->group(function () use ($ROOT_APP) {

                Route::post('/alert/', $ROOT_APP.'/http/api/frontend/alert.php')
                    ->name('alert');

            });

        Route::name('service.')
            ->prefix('/service')
            ->backend()
            ->group(function () use ($ROOT_APP) {

                Route::name('stripe.')
                    ->prefix('/stripe')
                    ->group(function () use ($ROOT_APP) {

                        Route::get('/onboarding/', $ROOT_APP.'/http/api/service/stripe/onboarding.php')
                            ->name('onboarding');

                        Route::get('/onboarding/check/', $ROOT_APP.'/http/api/service/stripe/onboarding-check.php')
                            ->name('onboarding.check');

                    });

                Route::name('fatture-in-cloud.')
                    ->prefix('/fatture-in-cloud')
                    ->group(function () use ($ROOT_APP) {

                        Route::get('/onboarding/', $ROOT_APP.'/http/api/service/fatture-in-cloud/onboarding.php')
                            ->name('onboarding');

                    });

            });

        Route::name('task.')
            ->prefix('/task')
            ->group(function () use ($ROOT_APP) {

                Route::get('/sitemap/', $ROOT_APP.'/http/api/task/sitemap.php')
                    ->name('sitemap')
                    ->frontend();

            });

        \Wonder\App\ResourceRouteRegistrar::registerApi($ROOT_APP);
        \Wonder\App\ModuleRouteRegistrar::registerApi($ROOT, $ROOT_APP);

    });
