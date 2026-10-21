<?php

use Wonder\Http\Route;

// Conferma webhook Stripe: pagina HTML del backend (niente CORS), solo admin.
// Registrata prima del gruppo api perché matchByPath prende la prima rotta che combacia.
Route::area('backend')
    ->response('html')
    ->theme('backend')
    ->guarded()
    ->permit(['admin'])
    ->translatable(false)
    ->name('service.stripe.')
    ->group(function () use ($ROOT_APP) {

        // GET mostra la conferma, POST (con CSRF) collega il webhook.
        Route::get('/api/service/stripe/connect/', $ROOT_APP.'/http/api/service/stripe/connect.php')
            ->name('connect');

        Route::post('/api/service/stripe/connect/', $ROOT_APP.'/http/api/service/stripe/connect.php');
    });

Route::area('api')
    ->prefix('/api')
    ->response('json')
    ->group(function () use ($ROOT_APP, $ROOT) {

        Route::post('/alert/', $ROOT_APP.'/http/api/alert.php')
            ->name('alert');

        Route::post('/states/', $ROOT_APP.'/http/api/states.php')
            ->name('states')
            ->frontend();

        // Rinnovo del token di pagina (`API_TOKEN`): same-origin, sessione e CSRF, senza guard.
        Route::post('/token/page/', $ROOT_APP.'/http/api/token/page.php')
            ->name('token.page');

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

        // API backend di sessione: ogni POST richiede il token CSRF
        // (`_csrf` o header `X-WI-CSRF`, mandato dalla lib).
        Route::name('backend.')
            ->prefix('/backend')
            ->backend()
            ->csrf()
            ->group(function () use ($ROOT_APP) {

                // Solo Bearer (`Handler::run`): niente cookie di sessione, niente CSRF.
                Route::post('/alert/', $ROOT_APP.'/http/api/backend/alert.php')
                    ->name('alert')
                    ->csrf(false);

                Route::post('/list/table/', $ROOT_APP.'/http/api/backend/list-table.php')
                    ->name('list.table')
                    ->guarded()
                    ->permit([]);

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
                    ->permit(['admin']);

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

                // Revoca di una connessione OAuth: la proprietà del grant si
                // controlla nell'handler, ogni utente backend revoca le sue.
                if (\Wonder\OAuth\OAuth::enabled()) {
                    Route::post('/oauth/revoke/', $ROOT_APP.'/http/api/backend/oauth-revoke.php')
                        ->name('oauth.revoke')
                        ->guarded()
                        ->permit([]);
                }

                Route::post('/editorjs/file/', $ROOT_APP.'/http/api/backend/editorjs-file.php')
                    ->name('editorjs.file')
                    ->guarded()
                    ->permit([]);

                Route::post('/editorjs/image/', $ROOT_APP.'/http/api/backend/editorjs-image.php')
                    ->name('editorjs.image')
                    ->guarded()
                    ->permit([]);

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

                Route::post('/scheduler/', $ROOT_APP.'/http/api/task/scheduler.php')
                    ->name('scheduler')->frontend();

                Route::post('/sitemap/', $ROOT_APP.'/http/api/task/sitemap.php')
                    ->name('sitemap')
                    ->frontend();

            });

        \Wonder\App\ResourceRouteRegistrar::registerApi($ROOT_APP);
        \Wonder\App\ModuleRouteRegistrar::registerApi($ROOT, $ROOT_APP);

    });
