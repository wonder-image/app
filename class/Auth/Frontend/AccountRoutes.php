<?php

namespace Wonder\Auth\Frontend;

use Wonder\Http\Route;

/** Pannello account del core, a richiesta: il sito o un modulo lo registra, i moduli lo estendono. */
final class AccountRoutes
{
    private static ?AccountPanel $panel = null;
    private static ?AuthProfile $auth = null;
    /** @var array<class-string, AccountExtension> */
    private static array $extensions = [];

    public static function register(AccountPanel $panel, ?AuthProfile $auth = null): void
    {
        if (self::$panel !== null) {
            if (get_class(self::$panel) !== get_class($panel)) {
                throw new \LogicException('Conflicting account panel');
            }
            // Ripetuto nello stesso caricamento: le route ci sono già. Se invece Route::load ha
            // ricaricato le route (stessa richiesta, secondo caricamento), si registrano di nuovo.
            if (Route::resolvePath('account.index') !== '') {
                return;
            }
        }
        self::$panel = $panel;
        self::$auth = $auth;
        $handler = self::handler();

        Route::area('frontend')->response('html')->name('account.')->prefix('/account')
            ->group(static function () use ($handler): void {
                Route::get('/email/conferma/', $handler, ['account_action' => 'email.confirm'])->name('email.confirm');
            });

        self::group(static function () use ($panel, $handler): void {
            Route::get('/', $handler, ['account_action' => 'index'])->name('index');
            $page = static function (string $path, string $action) use ($handler): void {
                Route::get($path, $handler, ['account_action' => $action])->name($action);
                Route::post($path, $handler, ['account_action' => $action]);
            };
            if ($panel->enabled('personal')) {
                $page('/dati-personali/', 'personal');
            }
            if ($panel->enabled('addresses')) {
                Route::get('/indirizzi/', $handler, ['account_action' => 'addresses'])->name('addresses');
                $page('/indirizzi/nuovo/', 'addresses.create');
                Route::get('/indirizzi/{id}/', $handler, ['account_action' => 'addresses.edit'])->name('addresses.edit')->where('id', '[0-9]+');
                Route::post('/indirizzi/{id}/', $handler, ['account_action' => 'addresses.edit'])->where('id', '[0-9]+');
                Route::post('/indirizzi/{id}/elimina/', $handler, ['account_action' => 'addresses.delete'])->name('addresses.delete')->where('id', '[0-9]+');
            }
            if ($panel->enabled('billing')) {
                $page('/fatturazione/', 'billing');
            }
        });

        foreach (self::$extensions as $extension) {
            $extension->routes();
        }
    }

    public static function extend(AccountExtension $extension): void
    {
        if (isset(self::$extensions[get_class($extension)])) {
            return;
        }
        self::$extensions[get_class($extension)] = $extension;
        if (self::$panel !== null) {
            $extension->routes();
        }
    }

    /** Route private nel gruppo del pannello: stesso prefisso, nomi `account.*`, login e permessi. */
    public static function group(callable $routes): void
    {
        Route::area('frontend')->response('html')->name('account.')->prefix('/account')
            ->guarded()->permit(self::panel()->authorities())->group($routes);
    }

    public static function panel(): AccountPanel { return self::$panel ?? new AccountPanel(); }
    public static function auth(): AuthProfile { return self::$auth ?? new AuthProfile(); }
    /** @return list<AccountExtension> */
    public static function extensions(): array { return array_values(self::$extensions); }
    public static function handler(): string { return dirname(__DIR__, 3).'/app/http/frontend/account.php'; }

    public static function reset(): void
    {
        self::$panel = null;
        self::$auth = null;
        self::$extensions = [];
    }
}
