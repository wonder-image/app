<?php

use Wonder\Http\Route;

Route::area('api')
    ->prefix('/api')
    ->response('json')
    ->group(function () use ($ROOT_APP) {

        Route::name('app.')
            ->prefix('/app')
            ->group(function () use ($ROOT_APP) {

                Route::post('/update/', $ROOT_APP.'/http/api/app/update.php')
                    ->name('update');

            });

        Route::name('task.')
            ->prefix('/task')
            ->group(function () use ($ROOT_APP) {

                Route::get('/sitemap/', $ROOT_APP.'/http/api/task/sitemap.php')
                    ->name('sitemap')
                    ->frontend();

            });

        \Wonder\App\ResourceRouteRegistrar::registerApi($ROOT_APP);

    });
