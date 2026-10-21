<?php

namespace Wonder\App;

/** Redazione dei dati personali e dei segreti prima della scrittura nei log. */
final class LogRedactor
{
    public const MASK = '***';

    /** Parametri della query string oscurati nei log. */
    public const SENSITIVE_PARAMS = ['token', 't', 'key', 'password', 'email', '_csrf'];

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
            $parts[] = in_array($base, self::SENSITIVE_PARAMS, true) ? $name.'='.self::MASK : $pair;
        }

        return $path.($parts === [] ? '' : '?'.implode('&', $parts)).$fragment;
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
