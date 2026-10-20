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
 *
 * Contesto: con `$context` non vuoto l'HMAC copre `<context>\0<payload>`, così
 * un frammento vale solo per la tabella, lo schema e il campo con cui Table lo
 * ha emesso (vedi `context()`). Lo stesso blob con un altro contesto, o senza,
 * non verifica: `ListProvider::fetch()` risponde con l'errore di firma prima
 * di qualsiasi query.
 */
final class ConfigCodec
{
    private const SEPARATOR = '.';

    /**
     * Contesto di firma dei frammenti di `api.backend.list.table`: chiave
     * canonica della tabella (minuscola, come in `Table::$list`), schema
     * (già normalizzato da `ListTableRequest::schema()`) e nome del campo.
     */
    public static function context(string $table, string $schema, string $field): string
    {
        return (string) json_encode([
            'list.table',
            strtolower(trim($table)),
            strtolower(trim($schema)),
            $field,
        ]);
    }

    /**
     * Sign a payload and return the base64 blob to embed in the config.
     */
    public static function encode(string $payload, ?string $key = null, string $context = ''): string
    {
        $key = $key ?? Credentials::appKey();
        $mac = hash_hmac('sha256', self::message($payload, $context), $key);

        return base64_encode($mac . self::SEPARATOR . $payload);
    }

    /**
     * Verify and decode a blob produced by encode(). Returns the original
     * payload when the signature is valid, or null when the value is missing,
     * malformed, or tampered with. A legitimately empty payload verifies and
     * decodes back to '' (never null). `$context` deve essere quello usato da
     * encode().
     */
    public static function decode(string $encoded, ?string $key = null, string $context = ''): ?string
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
        $expected = hash_hmac('sha256', self::message($payload, $context), $key);

        if (!hash_equals($expected, $mac)) {
            return null;
        }

        return $payload;
    }

    private static function message(string $payload, string $context): string
    {
        return $context === '' ? $payload : $context."\0".$payload;
    }
}
