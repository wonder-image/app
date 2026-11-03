<?php

namespace Wonder\App\Import;

/**
 * Una modifica fatta da una riga, con quanto serve per annullarla.
 *
 * `action`: `created` (la riga nuova si cancella), `updated` (si rimettono
 * `oldValues`), `stock` (si fa il movimento opposto a `stockMovementId`).
 */
final class Change
{
    /** @param array<string, mixed> $oldValues */
    public function __construct(
        public readonly string $target,
        public readonly int $recordId,
        public readonly string $action,
        public readonly array $oldValues = [],
        public readonly int $stockMovementId = 0,
    ) {
    }

    /** @param array<string, mixed> $row riga di `ImportChange` */
    public static function fromRow(array $row): self
    {
        $old = json_decode((string) ($row['old_values'] ?? ''), true);

        return new self(
            (string) ($row['target'] ?? ''),
            (int) ($row['record_id'] ?? 0),
            (string) ($row['action'] ?? ''),
            is_array($old) ? $old : [],
            (int) ($row['stock_movement_id'] ?? 0),
        );
    }
}
