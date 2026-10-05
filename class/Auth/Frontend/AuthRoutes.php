<?php

namespace Wonder\Auth\Frontend;

use Wonder\Http\Route;

/** Opt-in registration: no URLs or permissions are imposed on existing sites. */
final class AuthRoutes
{
    private static array $profiles = [];

    public static function profile(string $key): AuthProfile
    {
        return self::$profiles[$key] ?? throw new \RuntimeException('Auth profile not registered');
    }

    public static function register(AuthProfile $profile): void
    {
        if (isset(self::$profiles[$profile->key()]) && get_class(self::$profiles[$profile->key()]) !== get_class($profile)) {
            throw new \LogicException('Conflicting auth profile key');
        }
        self::$profiles[$profile->key()] = $profile;
        Route::area($profile->area())->response('html')->name($profile->routePrefix().'.')
            ->prefix($profile->pathPrefix())->group(static function () use ($profile): void {
                $handler = dirname(__DIR__, 3).'/app/http/frontend/auth.php';
                foreach ([
                    'login' => '/login/',
                    'signup.request' => '/signup/request/',
                    'signup.completion' => '/signup/completion/',
                    'email.sent' => '/email-verification/send/',
                    'email.verify' => '/email-verification/verify/',
                    'password.recovery' => '/password/recovery/',
                    'password.restore' => '/password/restore/',
                ] as $action => $path) {
                    $meta = ['auth_action' => $action, 'auth_profile_key' => $profile->key()];
                    Route::get($path, $handler, $meta)->name($action);
                    if (!in_array($action, ['email.verify', 'email.sent'], true)) {
                        Route::post($path, $handler, $meta);
                    }
                }
                foreach (['logout' => '/logout/', 'federated' => '/federated/{provider}/'] as $action => $path) {
                    Route::post($path, $handler, ['auth_action' => $action, 'auth_profile_key' => $profile->key()])->name($action);
                }
                if ($profile->impersonationEnabled()) {
                    Route::get('/impersonate/', $handler, ['auth_action' => 'impersonation.start', 'auth_profile_key' => $profile->key()])->name('impersonation.start');
                    Route::post('/impersonate/stop/', $handler, ['auth_action' => 'impersonation.stop', 'auth_profile_key' => $profile->key()])->name('impersonation.stop');
                }
            });
    }
}
