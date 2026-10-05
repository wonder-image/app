<?php

namespace Wonder\Http;

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputHidden;

/**
 * Token CSRF di sessione: generazione, emissione e confronto.
 *
 * La verifica non è ancora centrale: `verify()` va chiamato da chi protegge
 * la propria richiesta, `RouteDispatcher` non lo usa.
 */
final class Csrf
{
    public const FIELD = '_csrf';
    public const HEADER = 'X-WI-CSRF';

    // Nome storico di `AuthSession`: resta questo perché i form già aperti
    // e chi usa `AuthSession` continuino a vedere lo stesso token.
    private const KEY = 'wonder_auth_csrf';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::KEY];
    }

    public static function field(string $name = self::FIELD): InputHidden
    {
        return FormField::key($name)->hidden()->value(self::token());
    }

    /**
     * Una stringa è il token da confrontare; un array è il corpo della
     * richiesta; senza argomento legge `$_POST` e l'header `X-WI-CSRF`.
     */
    public static function verify(string|array|null $request = null): bool
    {
        if (is_string($request)) {
            $token = $request;
        } else {
            $token = ($request ?? $_POST)[self::FIELD] ?? null;

            if ($request === null && !is_string($token)) {
                $token = $_SERVER['HTTP_'.strtoupper(str_replace('-', '_', self::HEADER))] ?? null;
            }
        }

        $stored = (string) ($_SESSION[self::KEY] ?? '');

        return is_string($token) && $stored !== '' && $token !== '' && hash_equals($stored, $token);
    }

    /**
     * `true` quando il token può essere scritto nell'HTML senza che lo
     * chieda la pagina: serve una sessione, e la risposta non deve essere
     * dichiarata condivisibile (il token finirebbe in una cache comune).
     */
    public static function active(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE && session_cache_limiter() !== 'public';
    }

    /** Campo già renderizzato per un form con quel metodo; vuoto per GET o quando `active()` è falso. */
    public static function fieldFor(string $method = 'post'): string
    {
        if (in_array(strtoupper(trim($method)), ['GET', 'HEAD'], true) || !self::active()) {
            return '';
        }

        return self::field()->render();
    }
}
