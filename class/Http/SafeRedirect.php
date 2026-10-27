<?php

namespace Wonder\Http;

/**
 * Destinazioni di redirect lette dalla richiesta (`?redirect=`, `continue`):
 * passano solo i path assoluti dello stesso sito (`/...`) e gli URL con lo
 * stesso host di `APP_URL` (con o senza `www.`), stessa porta, senza
 * credenziali e con schema `http`/`https` uguale a quello di `APP_URL` o
 * promosso a `https`. Tutto il resto torna al fallback: host esterni,
 * `//host`, `/\host`, schemi come `javascript:` e caratteri di controllo
 * (CR/LF compresi: niente header injection).
 */
class SafeRedirect
{
    public static function fromRequest(mixed $value, string $fallback = '/'): string
    {
        if (!is_scalar($value)) {
            return $fallback;
        }

        // Solo gli spazi: un CR/LF o un byte nullo in coda resta e fa scartare il valore.
        $value = trim((string) $value, " ");

        return self::isSafe($value) ? $value : $fallback;
    }

    /** Il `?redirect=` storico del backend: base64 dell'URL di ritorno. */
    public static function fromBase64(mixed $value, string $fallback = ''): string
    {
        if (!is_string($value) || $value === '') {
            return $fallback;
        }

        $decoded = base64_decode($value, true);

        return is_string($decoded) ? self::fromRequest($decoded, $fallback) : $fallback;
    }

    /**
     * Il ritorno da portare nei form di login e registrazione: `continue`, altrimenti il `?redirect=` (base64)
     * con cui il pannello manda al login. Senza, dopo il login si finiva sulla home dell'area.
     */
    public static function continueFromRequest(): string
    {
        $continue = $_POST['continue'] ?? $_GET['continue'] ?? '';
        if (is_scalar($continue) && (string) $continue !== '') {
            return (string) $continue;
        }

        return self::fromBase64($_GET['redirect'] ?? '', '');
    }

    public static function isSafe(string $value): bool
    {
        if ($value === '' || str_starts_with($value, '//') || str_contains($value, '\\') || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            return false;
        }

        if (str_starts_with($value, '/')) {
            return true;
        }

        $target = parse_url($value);
        $app = parse_url(rtrim((string) ($_ENV['APP_URL'] ?? ''), '/'));

        if (!is_array($target) || !is_array($app) || !isset($target['host'], $target['scheme'], $app['host'], $app['scheme'])) {
            return false;
        }

        $scheme = strtolower($target['scheme']);
        $appScheme = strtolower($app['scheme']);

        return in_array($scheme, ['http', 'https'], true)
            && ($scheme === $appScheme || ($scheme === 'https' && $appScheme === 'http'))
            && strcasecmp(self::bareHost($target['host']), self::bareHost($app['host'])) === 0
            && ($target['port'] ?? null) === ($app['port'] ?? null)
            && !isset($target['user'])
            && !isset($target['pass']);
    }

    /** `www.example.com` e `example.com` sono lo stesso sito: `ListProvider::redirect()` aggiunge sempre `www.`. */
    private static function bareHost(string $host): string
    {
        return (string) preg_replace('/^www\./i', '', $host);
    }
}
