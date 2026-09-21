<?php

use Wonder\Http\Route;

Route::area('backend')
    ->prefix('/backend')
    ->response('html')
    ->theme('backend')
    ->guarded()
    ->name('backend.')
    ->group(function () use ($ROOT_APP, $ROOT) {

        Route::get('/', $ROOT_APP.'/http/backend/home.php')
            ->name('home')
            ->permit([]);

        Route::post('/', $ROOT_APP.'/http/backend/home.php')
            ->permit([]);

        // Pagina "Riepilogo" dello scheduler: nessun handler dedicato, la logica
        // vive in DashboardResource; l'entry condivisa `resource/page.php` la
        // risolve dallo slug e ne chiama handle().
        $schedulerSlug = \Wonder\App\Resources\Scheduler\DashboardResource::slug();
        Route::get('/app/scheduler/', $ROOT_APP.'/http/backend/resource/page.php', ['resource' => $schedulerSlug])
            ->name('scheduler')->permit(['admin']);
        Route::post('/app/scheduler/', $ROOT_APP.'/http/backend/resource/page.php', ['resource' => $schedulerSlug])
            ->permit(['admin']);

        // Creazione rapida dal modal FK: proxy che crea la risorsa collegata via
        // store API come @system. Ogni utente backend autenticato può chiamarlo;
        // l'autorizzazione fine per-target è nel QuickCreateController.
        Route::post('/resource/quick-create/', $ROOT_APP.'/http/backend/resource/quick-create.php')
            ->name('resource.quick-create')->permit([]);

        Route::name('media.')
            ->prefix('/app/media')
            ->group(function () use ($ROOT_APP) {

                Route::get('/upload-massive/', $ROOT_APP.'/http/backend/media/upload-massive.php')
                    ->name('upload-massive')
                    ->permit(['admin']);

                Route::post('/upload-massive/', $ROOT_APP.'/http/backend/media/upload-massive.php')
                    ->permit(['admin']);

            });

        Route::name('config.')
            ->prefix('/app/config')
            ->group(function () use ($ROOT_APP) {

                Route::get('/configuration-file/', $ROOT_APP.'/http/backend/config/configuration-file.php')
                    ->name('configuration-file')
                    ->permit(['admin']);

                Route::post('/configuration-file/', $ROOT_APP.'/http/backend/config/configuration-file.php')
                    ->permit(['admin']);

                Route::get('/sql-download/', $ROOT_APP.'/http/backend/config/sql-download.php')
                    ->name('sql-download')
                    ->permit(['admin']);

            });

        Route::get('/login/', $ROOT_APP.'/http/backend/account/login.php')
            ->name('login.legacy')
            ->guarded(false)
            ->permit([]);

        Route::post('/login/', $ROOT_APP.'/http/backend/account/login.php')
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
        \Wonder\App\ModuleRouteRegistrar::registerBackend($ROOT, $ROOT_APP);

    });
