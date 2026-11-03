<?php

namespace Wonder\Localization\Content;

/**
 * Accesso a `translations`, `translation_memory` e `translation_terms`.
 * Le righe di `translations` hanno almeno `id`, `value`, `status` e
 * `source_hash`; un termine ha `id`, `term`, `mode`, `status` e `occurrences`.
 */
interface TranslationStore
{
    /**
     * Traduzioni di un batch di righe in una lingua, in una sola query.
     *
     * @param list<int> $rowIds
     * @return array<int, array<string, array<string, mixed>>> row_id => campo => riga
     */
    public function translations(string $table, array $rowIds, string $lang): array;

    /**
     * Inserisce o aggiorna la traduzione sulla chiave unica
     * (tabella, riga, campo, lingua).
     *
     * @param array{value?: string, status?: string, source_hash?: ?string, engine?: string} $values
     */
    public function save(string $table, int $rowId, string $field, string $lang, array $values): void;

    /**
     * Righe della tabella sorgente da tradurre o da segnare `review`: senza
     * traduzione, oppure con `source_hash` diverso dall'hash del valore
     * corrente. Le righe già in `review` restano fuori.
     *
     * @return list<array{id: int, value: string, status: ?string, source_hash: ?string}>
     */
    public function pending(string $table, string $field, string $lang, int $limit, bool $softDelete): array;

    public function memory(string $hash): ?string;

    public function remember(string $hash, string $from, string $to, string $source, string $value, string $engine): void;

    /** @return list<array<string, mixed>> termini, filtrati per stato se dato */
    public function terms(?string $status = null): array;

    /**
     * Inserisce o aggiorna un termine (unico per `term`), restituisce l'id.
     *
     * @param array{mode?: string, status?: string, occurrences?: int} $values
     */
    public function saveTerm(string $term, array $values): int;

    /**
     * Traduzioni di un campo il cui testo sorgente contiene `$term`.
     *
     * @return list<array{id: int, row_id: int, language_code: string, status: string}>
     */
    public function usages(string $table, string $field, string $term, bool $softDelete): array;

    /**
     * Rimette in coda le traduzioni `automatic` fra gli id dati (azzera
     * `source_hash`); restituisce quante sono cambiate.
     *
     * @param list<int> $ids
     */
    public function requeue(array $ids): int;
}
