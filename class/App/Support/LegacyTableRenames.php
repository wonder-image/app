<?php

namespace Wonder\App\Support;

use mysqli;
use RuntimeException;

/**
 * Rinomina idempotente di tabelle legacy (`vecchia => nuova`), con ID, dati e
 * chiavi esterne intatti. Una tabella vecchia assente si salta; se esistono sia
 * la vecchia sia la nuova si ferma senza toccare nulla: niente drop, niente merge.
 */
final class LegacyTableRenames
{
    /** @param array<string, string> $map @param list<string> $existing @return array<string, string> */
    public static function plan(array $map, array $existing, string $subject = 'tabelle'): array
    {
        $plan = [];
        foreach ($map as $old => $new) {
            if (!in_array($old, $existing, true)) {
                continue;
            }
            if (in_array($new, $existing, true)) {
                throw new RuntimeException("Migrazione {$subject} interrotta: esistono sia {$old} sia {$new}. Riconciliare le tabelle prima dell'update; nessun dato è stato cancellato.");
            }
            $plan[$old] = $new;
        }
        return $plan;
    }

    /** Tutte le rinomine in un solo `RENAME TABLE`, atomico. @param array<string, string> $map @return array<string, string> */
    public static function run(mysqli $connection, array $map, string $subject = 'tabelle'): array
    {
        $result = $connection->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        if ($result === false) {
            throw new RuntimeException("Impossibile verificare le {$subject}.");
        }
        $existing = array_column($result->fetch_all(MYSQLI_ASSOC), 'TABLE_NAME');
        $result->free();
        $plan = self::plan($map, $existing, $subject);
        if ($plan !== []) {
            $renames = [];
            foreach ($plan as $old => $new) {
                $renames[] = "`{$old}` TO `{$new}`";
            }
            if ($connection->query('RENAME TABLE '.implode(', ', $renames)) === false) {
                throw new RuntimeException("Rinomina delle {$subject} non riuscita.");
            }
            // Il memo di sqlTableExists() vedrebbe ancora la vecchia e non la nuova.
            RequestMemo::forget('@sql.table_exists');
            foreach ($plan as $old => $new) {
                RequestMemo::forget($old);
                RequestMemo::forget($new);
            }
        }
        return $plan;
    }
}
