<?php

namespace Wonder\Auth\Frontend;

final class SafeRedirect
{
    public static function fromRequest(mixed $value, string $fallback = '/'): string
    {
        $value = trim(str_replace(["\r", "\n"], '', (string) $value));

        if ($value === '' || str_starts_with($value, '//') || str_contains($value, '\\') || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            return $fallback;
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $targetHost = parse_url($value, PHP_URL_HOST);
        $appHost = parse_url($appUrl, PHP_URL_HOST);

        return is_string($targetHost) && is_string($appHost) && strcasecmp($targetHost, $appHost) === 0
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            && strtolower((string) parse_url($value, PHP_URL_SCHEME)) === strtolower((string) parse_url($appUrl, PHP_URL_SCHEME))
            && parse_url($value, PHP_URL_PORT) === parse_url($appUrl, PHP_URL_PORT)
            && parse_url($value, PHP_URL_USER) === null
            ? $value
            : $fallback;
    }
}
