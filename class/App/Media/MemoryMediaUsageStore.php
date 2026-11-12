<?php

namespace Wonder\App\Media;

/**
 * Registro degli utilizzi in memoria, con le stesse regole di
 * `DatabaseMediaUsageStore`: per i test, senza database.
 */
final class MemoryMediaUsageStore implements MediaUsageStore
{
    /** @var list<array{media: int, table: string, id: string, column: string}> */
    public array $rows = [];

    public function replace(string $table, string $rowId, string $column, array $mediaIds): void
    {
        $this->rows = array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => !($row['table'] === $table && $row['id'] === $rowId && $row['column'] === $column)
        ));

        foreach ($mediaIds as $mediaId) {
            $this->rows[] = ['media' => (int) $mediaId, 'table' => $table, 'id' => $rowId, 'column' => $column];
        }
    }

    public function forget(string $table, string $rowId): void
    {
        $this->rows = array_values(array_filter(
            $this->rows,
            static fn (array $row): bool => !($row['table'] === $table && $row['id'] === $rowId)
        ));
    }

    public function used(int $mediaId): bool
    {
        return $this->owners($mediaId) !== [];
    }

    public function owners(int $mediaId): array
    {
        $owners = [];

        foreach ($this->rows as $row) {
            if ($row['media'] === $mediaId) {
                $owners[] = ['table' => $row['table'], 'id' => $row['id'], 'column' => $row['column']];
            }
        }

        return $owners;
    }
}
