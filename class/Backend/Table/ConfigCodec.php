<?php

namespace Wonder\Backend\Table;

use Wonder\App\Credentials;

/**
 * Signs the server-generated DataTables config values that are round-tripped
 * through the untrusted client (query, query_filter, query_custom,
 * search_columns).
 *
 * These values are raw SQL fragments / identifier blobs built server-side and
 * echoed back by the browser on every AJAX request. Base64 alone is not a
 * security boundary: an authenticated backend user can tamper with them to
 * inject SQL. Each value therefore carries an HMAC (keyed with APP_KEY)
 * embedded inside the same base64 string the client already forwards, so no
 * client-side change is required.
 *
 * Wire format (before base64): "<hmac-sha256-hex>.<payload>".
 */
final class ConfigCodec
{
    private const SEPARATOR = '.';

    /**
     * Sign a payload and return the base64 blob to embed in the config.
     */
    public static function encode(string $payload, ?string $key = null): string
    {
        $key = $key ?? Credentials::appKey();
        $mac = hash_hmac('sha256', $payload, $key);

        return base64_encode($mac . self::SEPARATOR . $payload);
    }

    /**
     * Verify and decode a blob produced by encode(). Returns the original
     * payload when the signature is valid, or null when the value is missing,
     * malformed, or tampered with. A legitimately empty payload verifies and
     * decodes back to '' (never null).
     */
    public static function decode(string $encoded, ?string $key = null): ?string
    {
        if ($encoded === '') {
            return null;
        }

        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            return null;
        }

        $separatorPos = strpos($raw, self::SEPARATOR);
        if ($separatorPos === false) {
            return null;
        }

        $mac     = substr($raw, 0, $separatorPos);
        $payload = substr($raw, $separatorPos + strlen(self::SEPARATOR));

        $key      = $key ?? Credentials::appKey();
        $expected = hash_hmac('sha256', $payload, $key);

        if (!hash_equals($expected, $mac)) {
            return null;
        }

        return $payload;
    }
}
