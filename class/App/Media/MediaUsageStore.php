<?php

namespace Wonder\App\Media;

/**
 * Dove vive il registro degli utilizzi dei media (`media_usages` con
 * `DatabaseMediaUsageStore`). Iniettabile perché i test di `MediaUsages`
 * non tocchino il database (`MemoryMediaUsageStore`).
 */
interface MediaUsageStore
{
    /**
     * Sostituisce i media usati da quella colonna di quel record.
     *
     * @param list<int> $mediaIds già ripuliti: interi positivi senza doppioni
     */
    public function replace(string $table, string $rowId, string $column, array $mediaIds): void;

    /** Toglie tutti gli utilizzi di un record. */
    public function forget(string $table, string $rowId): void;

    public function used(int $mediaId): bool;

    /**
     * @return list<array{table: string, id: string, column: string}> i record
     *         che usano il media, nell'ordine in cui sono stati registrati
     */
    public function owners(int $mediaId): array;
}
