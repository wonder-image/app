<?php

namespace Wonder\App\Support;

/**
 * Piano dell'import di una tabella con `SyncSchema::keepIds()`.
 *
 * Classe pura: dato il contenuto del file e gli `id` presenti nel database
 * calcola cosa inserire, cosa aggiornare e cosa segnare come cancellato.
 * `TableSync` esegue il piano; nessuna riga viene mai eliminata.
 */
final class SyncImportPlan
{
    /**
     * @param list<array<string, mixed>> $inserts righe da inserire, con `id`
     * @param array<int, array<string, mixed>> $updates valori per `id`, senza `id`
     * @param list<int> $softDeletes `id` da segnare con `deleted = 'true'`
     */
    private function __construct(
        public readonly array $inserts,
        public readonly array $updates,
        public readonly array $softDeletes,
        public readonly int $skipped,
    ) {
    }

    /**
     * @param array<int, mixed> $fileRows righe del file già ripulite
     * @param array<int, int|string> $existingIds `id` presenti nel database
     */
    public static function make(array $fileRows, array $existingIds): self
    {
        $existing = [];

        foreach ($existingIds as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $existing[$id] = true;
            }
        }

        $inserts = [];
        $updates = [];
        $seen = [];
        $skipped = 0;

        foreach ($fileRows as $row) {
            $id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;

            if ($id <= 0 || isset($seen[$id])) {
                $skipped++;
                continue;
            }

            $seen[$id] = true;

            if (isset($existing[$id])) {
                $values = $row;
                unset($values['id']);
                $updates[$id] = $values;
                continue;
            }

            $inserts[] = $row;
        }

        $softDeletes = [];

        foreach (array_keys($existing) as $id) {
            if (!isset($seen[$id])) {
                $softDeletes[] = $id;
            }
        }

        return new self($inserts, $updates, $softDeletes, $skipped);
    }
}
