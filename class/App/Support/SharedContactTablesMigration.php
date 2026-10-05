<?php

namespace Wonder\App\Support;

use mysqli;
use RuntimeException;

/** Preserve IDs, data and foreign keys when promoting gestionale tables to core. */
final class SharedContactTablesMigration
{
    private const TABLES = [
        'gst_contacts' => 'contacts',
        'gst_contact_addresses' => 'contact_addresses',
        'gst_external_references' => 'external_references',
    ];

    public static function plan(array $existing): array
    {
        $plan = [];
        foreach (self::TABLES as $old => $new) {
            if (!in_array($old, $existing, true)) {
                continue;
            }
            if (in_array($new, $existing, true)) {
                throw new RuntimeException("Migrazione contatti interrotta: esistono sia {$old} sia {$new}. Riconciliare le tabelle prima dell'update; nessun dato è stato cancellato.");
            }
            $plan[$old] = $new;
        }
        return $plan;
    }

    public static function run(mysqli $connection): array
    {
        $result = $connection->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        if ($result === false) {
            throw new RuntimeException('Impossibile verificare le tabelle dei contatti.');
        }
        $existing = array_column($result->fetch_all(MYSQLI_ASSOC), 'TABLE_NAME');
        $result->free();
        $plan = self::plan($existing);
        if ($plan !== []) {
            $renames = [];
            foreach ($plan as $old => $new) {
                $renames[] = "`{$old}` TO `{$new}`";
            }
            if ($connection->query('RENAME TABLE '.implode(', ', $renames)) === false) {
                throw new RuntimeException('Rinomina delle tabelle contatti non riuscita.');
            }
        }
        return $plan;
    }
}
