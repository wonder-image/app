<?php

namespace Wonder\App\Support;

use mysqli;
use Wonder\Api\Token\ApiTokens;

/**
 * Svuota i bearer in chiaro salvati da `api_activity.token` prima che
 * `Endpoint` registrasse solo l'impronta (`sha256:<16 hex>`).
 *
 * Idempotente: tocca solo le righe che non sono gia vuote o un'impronta.
 * Gira dopo `runTables()`, cosi trova la tabella allineata.
 */
final class ApiActivityTokenMigration
{
    public const TABLE = 'api_activity';

    public static function sql(): string
    {
        return "UPDATE `".self::TABLE."` SET `token` = ''".self::where();
    }

    public static function countSql(): string
    {
        return "SELECT COUNT(*) FROM `".self::TABLE."`".self::where();
    }

    /** @return int righe ripulite */
    public static function run(mysqli $connection): int
    {
        $exists = $connection->query("SHOW TABLES LIKE '".self::TABLE."'");

        if (!is_object($exists) || (int) ($exists->num_rows ?? 0) === 0) {
            return 0;
        }

        $count = $connection->query(self::countSql());
        $rows = is_object($count) ? (int) ($count->fetch_row()[0] ?? 0) : 0;

        if ($rows > 0) {
            $connection->query(self::sql());
        }

        return $rows;
    }

    private static function where(): string
    {
        $prefix = addcslashes(ApiTokens::FINGERPRINT_PREFIX, '%_\\\'');

        return " WHERE `token` <> '' AND `token` NOT LIKE '{$prefix}%'";
    }
}
