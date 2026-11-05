<?php

namespace Wonder\App\Support;

use mysqli;

/**
 * Porta l'ID di Google Tag Manager dalla vecchia tabella `analytics`
 * (pagina Analitica, dismessa) alla riga `security` (Credenziali).
 *
 * Idempotente: scrive solo se in Credenziali l'ID è ancora vuoto.
 * La tabella `analytics` resta nel database. Gira dopo `runTables()`.
 */
final class AnalyticsToCredentialsMigration
{
    /** @return int 1 se ha copiato l'ID, altrimenti 0 */
    public static function run(mysqli $connection): int
    {
        if (!self::tableExists($connection, 'analytics')
            || !self::tableExists($connection, 'security')
            || !self::columnExists($connection, 'security', 'g_tag_manager_id')
            || !self::columnExists($connection, 'analytics', 'tag_manager')
        ) {
            return 0;
        }

        $source = self::row($connection, "SELECT * FROM `analytics` WHERE `id` = 1 LIMIT 1");
        $id = trim((string) ($source['tag_manager'] ?? ''));

        if ($id === '') {
            return 0;
        }

        $target = self::row($connection, "SELECT `g_tag_manager_id` FROM `security` WHERE `id` = 1 LIMIT 1");

        if ($target === null || trim((string) ($target['g_tag_manager_id'] ?? '')) !== '') {
            return 0;
        }

        $active = self::active($source['active_tag_manager'] ?? '');

        $statement = $connection->prepare(
            "UPDATE `security` SET `g_tag_manager_id` = ?, `g_tag_manager_active` = ? WHERE `id` = 1"
        );

        if ($statement === false) {
            return 0;
        }

        $statement->bind_param('ss', $id, $active);
        $statement->execute();
        $statement->close();

        return 1;
    }

    /** Vuoto valeva acceso nella vecchia pagina. */
    public static function active(mixed $value): string
    {
        return trim((string) ($value ?? '')) === 'false' ? 'false' : 'true';
    }

    private static function tableExists(mysqli $connection, string $table): bool
    {
        $result = $connection->query("SHOW TABLES LIKE '".$connection->real_escape_string($table)."'");

        return is_object($result) && (int) ($result->num_rows ?? 0) > 0;
    }

    private static function columnExists(mysqli $connection, string $table, string $column): bool
    {
        $result = $connection->query(
            "SHOW COLUMNS FROM `".$table."` LIKE '".$connection->real_escape_string($column)."'"
        );

        return is_object($result) && (int) ($result->num_rows ?? 0) > 0;
    }

    /** @return array<string, mixed>|null */
    private static function row(mysqli $connection, string $sql): ?array
    {
        $result = $connection->query($sql);
        $row = is_object($result) ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }
}
