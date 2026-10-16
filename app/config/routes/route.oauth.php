<?php

use Wonder\Http\Route;
use Wonder\OAuth\OAuth;

// Caricato dopo route.api.php (ordine alfabetico): i moduli hanno già registrato le risorse.
// Senza risorse non esiste nessuna rotta OAuth e il sito non espone superficie in più.
if (!OAuth::enabled()) {
    return;
}

$oauthHttp = $ROOT_APP.'/http/oauth';

Route::area('api')
    ->response('json')
    ->translatable(false)
    ->name('oauth.')
    ->group(function () use ($oauthHttp) {

        Route::get('/.well-known/oauth-authorization-server/', $oauthHttp.'/authorization-server.php')
            ->name('metadata');

        // Solo path: l'issuer si valida negli handler, mai al caricamento delle rotte.
        $paths = OAuth::paths();
        Route::get('/.well-known/oauth-protected-resource/', $oauthHttp.'/protected-resource.php', ['oauth_resource' => $paths[0]])
            ->name('resource.root');

        foreach ($paths as $path) {
            Route::get('/.well-known/oauth-protected-resource'.$path.'/', $oauthHttp.'/protected-resource.php', ['oauth_resource' => $path]);
        }

        Route::post('/oauth/register/', $oauthHttp.'/register.php')->name('register');
        Route::post('/oauth/token/', $oauthHttp.'/token.php')->name('token');
        Route::post('/oauth/revoke/', $oauthHttp.'/revoke.php')->name('revoke');
    });

Route::area('backend')
    ->response('html')
    ->theme('backend')
    ->guarded(false)
    ->permit([])
    ->translatable(false)
    ->name('oauth.')
    ->group(function () use ($oauthHttp) {

        Route::get('/oauth/authorize/', $oauthHttp.'/authorize.php')->name('authorize');
        Route::get('/oauth/login/', $oauthHttp.'/login.php')->name('login');
        Route::post('/oauth/login/', $oauthHttp.'/login.php');
        Route::get('/oauth/consent/', $oauthHttp.'/consent.php')->name('consent');
        Route::post('/oauth/consent/', $oauthHttp.'/consent.php');
    });
