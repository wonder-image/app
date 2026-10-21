<?php

namespace Wonder\App;

/** Redazione dei dati personali e dei segreti prima della scrittura nei log. */
final class LogRedactor
{
    public const MASK = '***';

    /** Parametri della query string oscurati nei log. */
    public const SENSITIVE_PARAMS = [
        'token', 't', 'key', 'password', 'email', '_csrf',
        'code', 'state', 'access_token', 'refresh_token', 'pwd', 'reset', 'signature', 'hash',
    ];

    /** Oscurato anche ogni parametro il cui nome contiene una di queste parole. */
    public const SENSITIVE_FRAGMENTS = ['token', 'secret', 'password'];

    /** URI con i parametri sensibili oscurati (nome confrontato senza maiuscole). */
    public static function uri(string $uri): string
    {
        $pos = strpos($uri, '?');
        if ($pos === false) {
            return $uri;
        }

        $path = substr($uri, 0, $pos);
        $query = substr($uri, $pos + 1);
        $fragment = '';
        if (($hash = strpos($query, '#')) !== false) {
            $fragment = substr($query, $hash);
            $query = substr($query, 0, $hash);
        }

        $parts = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            [$name] = explode('=', $pair, 2);
            // `email[]` o `token[x]`: conta il nome prima delle quadre.
            $base = strtolower(preg_replace('/\[.*$/', '', rawurldecode($name)) ?? '');
            $parts[] = self::isSensitive($base) ? $name.'='.self::MASK : $pair;
        }

        return $path.($parts === [] ? '' : '?'.implode('&', $parts)).$fragment;
    }

    private static function isSensitive(string $name): bool
    {
        if (in_array($name, self::SENSITIVE_PARAMS, true)) {
            return true;
        }

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($name, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /** Testo libero (es. errore SMTP) con le email mascherate come in identifier(). */
    public static function text(string $text): string
    {
        return preg_replace_callback(
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/',
            static fn (array $m): string => self::identifier($m[0]),
            $text
        ) ?? $text;
    }

    /** `mario.rossi@example.com` → `m***@example.com`; altro testo → prima lettera + `***`. */
    public static function identifier(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $at = strrpos($value, '@');
        if ($at !== false && $at > 0) {
            return mb_substr($value, 0, 1).self::MASK.substr($value, $at);
        }

        return mb_substr($value, 0, 1).self::MASK;
    }
}
