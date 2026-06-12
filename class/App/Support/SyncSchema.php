<?php

namespace Wonder\App\Support;

/**
 * Descrive il comportamento di sincronizzazione di una tabella.
 *
 * Ogni Model che vuole partecipare al sistema di export/import via
 * `forge export` / `forge import` deve restituire un'istanza di
 * `SyncSchema` dal metodo `syncSchema()`.
 *
 * Due modalita:
 * - `SyncSchema::singleton()` — la tabella ha una sola riga (id=1)
 * - `SyncSchema::multiRow()` — la tabella ha righe multiple
 *
 * Opzionalmente si possono escludere colonne specifiche dall'export
 * con `->exclude(['colonna1', 'colonna2'])`.
 */
final class SyncSchema
{
    public readonly bool $singleton;

    /** @var string[] Colonne escluse dall'export (oltre a quelle di sistema). */
    public readonly array $excludeColumns;

    private function __construct(bool $singleton, array $excludeColumns = [])
    {
        $this->singleton = $singleton;
        $this->excludeColumns = $excludeColumns;
    }

    /**
     * Tabella singleton: esporta solo la riga con id=1.
     */
    public static function singleton(): self
    {
        return new self(singleton: true);
    }

    /**
     * Tabella multi-row: esporta tutte le righe.
     */
    public static function multiRow(): self
    {
        return new self(singleton: false);
    }

    /**
     * Escludi colonne specifiche dall'export (oltre alle colonne di sistema
     * `id`, `last_modified`, `creation`, `deleted`).
     *
     * @param string[] $columns
     */
    public function exclude(array $columns): self
    {
        return new self($this->singleton, $columns);
    }
}
