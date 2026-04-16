<?php

use Wonder\Http\Route;

Route::area('backend')
    ->prefix('/backend')
    ->response('html')
    ->theme('backend')
    ->guarded()
    ->name('backend.')
    ->group(function () use ($ROOT_APP) {

        Route::get('/', $ROOT_APP.'/http/backend/home.php')
            ->name('home')
            ->permit([]);

        Route::post('/', $ROOT_APP.'/http/backend/home.php')
            ->permit([]);

        Route::redirect('/login/', '/backend/account/login/')
            ->name('login.legacy')
            ->guarded(false)
            ->permit([]);

        Route::name('account.')
            ->prefix('/account')
            ->group(function () use ($ROOT_APP) {

                Route::get('/', $ROOT_APP.'/http/backend/account/index.php')
                    ->name('index')
                    ->permit([]);

                Route::get('/login/', $ROOT_APP.'/http/backend/account/login.php')
                    ->name('login')
                    ->guarded(false)
                    ->permit([]);

                Route::post('/login/', $ROOT_APP.'/http/backend/account/login.php')
                    ->guarded(false)
                    ->permit([]);

                Route::get('/logout/', $ROOT_APP.'/http/backend/account/logout.php')
                    ->name('logout')
                    ->guarded(false)
                    ->permit([]);

                Route::get('/password-recovery/', $ROOT_APP.'/http/backend/account/password-recovery.php')
                    ->name('password.recovery')
                    ->guarded(false)
                    ->permit([]);

                Route::post('/password-recovery/', $ROOT_APP.'/http/backend/account/password-recovery.php')
                    ->guarded(false)
                    ->permit([]);

                Route::get('/password-restore/', $ROOT_APP.'/http/backend/account/password-restore.php')
                    ->name('password.restore')
                    ->guarded(false)
                    ->permit([]);

                Route::post('/password-restore/', $ROOT_APP.'/http/backend/account/password-restore.php')
                    ->guarded(false)
                    ->permit([]);

                Route::get('/password-set/', $ROOT_APP.'/http/backend/account/password-set.php')
                    ->name('password.set')
                    ->guarded(false)
                    ->permit([]);

                Route::post('/password-set/', $ROOT_APP.'/http/backend/account/password-set.php')
                    ->guarded(false)
                    ->permit([]);

            });

        \Wonder\App\ResourceRouteRegistrar::registerBackend($ROOT_APP);

    });
