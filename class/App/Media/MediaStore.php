<?php

namespace Wonder\App\Media;

/**
 * Dove vivono i media (`media` con `DatabaseMediaStore`). Lo usano il
 * campo media dei form (`MediaInputs`), la lettura per id (`MediaFile`) e
 * la ricerca del selettore (`MediaLibrary::search()`). Iniettabile perché i
 * test non tocchino database e file (`MemoryMediaStore`).
 *
 * Una riga ha `id`, `type`, `slug`, `name`, `alt` e `file` (il nome del
 * file salvato, senza cartella). I media eliminati non esistono.
 */
interface MediaStore
{
    /**
     * @param list<int> $ids
     * @return array<int, array{id: int, type: string, slug: string, name: string, alt: string, file: string}>
     *         le righe trovate, per id, con una sola lettura
     */
    public function rows(array $ids): array;

    /** Vero se un media non eliminato ha già questo slug. */
    public function slugTaken(string $slug): bool;

    /**
     * Crea un media di `$type` dal file caricato, con la stessa strada
     * della sua sezione in libreria (controlli, nome del file, svg
     * ripulito, misure in coda).
     *
     * @param array{name: string, slug: string, alt: string} $values
     * @param array<string, mixed> $upload una voce di `$_FILES` con un file
     * @return array{id: int, error: string} `id` 0 e il motivo se non riesce
     */
    public function create(string $type, array $values, array $upload): array;

    /**
     * Sostituisce il file del media: id, slug, nome del file e testo
     * alternativo restano.
     *
     * @param array<string, mixed> $upload una voce di `$_FILES` con un file
     * @return string il motivo se non riesce, altrimenti stringa vuota
     */
    public function replaceFile(int $id, array $upload): string;

    public function setAlt(int $id, string $alt): void;

    /**
     * I media di `$type` che contengono `$term` in nome, slug o testo
     * alternativo, dal più recente.
     *
     * @return array{rows: list<array{id: int, type: string, slug: string, name: string, alt: string, file: string}>, total: int}
     */
    public function search(string $type, string $term, int $limit, int $offset): array;
}
