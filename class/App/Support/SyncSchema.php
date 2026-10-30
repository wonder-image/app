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
 * Opzioni (nuove istanze immutabili, componibili):
 * - `->exclude([...])` — colonne escluse dall'export e dall'import;
 * - `->keepWhenEmpty([...])` — colonne che l'import scrive solo se il file ha
 *   un valore: vuote nel file, non svuotano una riga esistente;
 * - `->keepIds()` — export con `id` e `deleted`; import che inserisce o
 *   aggiorna per `id` senza svuotare la tabella e segna `deleted = 'true'`
 *   le righe assenti dal file (per tabelle referenziate da chiavi esterne);
 * - `->localOnly()` — la tabella si modifica solo con `APP_ENV=local`;
 *   altrove le Resource dei suoi Model sono in sola lettura.
 */
final class SyncSchema
{
    public readonly bool $singleton;

    /** @var string[] Colonne escluse dall'export (oltre a quelle di sistema). */
    public readonly array $excludeColumns;

    /** @var string[] Colonne che un valore vuoto nel file non sovrascrive. */
    public readonly array $keepWhenEmptyColumns;

    public readonly bool $keepIds;

    public readonly bool $localOnly;

    private function __construct(
        bool $singleton,
        array $excludeColumns = [],
        bool $keepIds = false,
        bool $localOnly = false,
        array $keepWhenEmptyColumns = [],
    ) {
        $this->singleton = $singleton;
        $this->excludeColumns = $excludeColumns;
        $this->keepIds = $keepIds;
        $this->localOnly = $localOnly;
        $this->keepWhenEmptyColumns = $keepWhenEmptyColumns;
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
        return new self($this->singleton, $columns, $this->keepIds, $this->localOnly, $this->keepWhenEmptyColumns);
    }

    /**
     * L'import non svuota queste colonne su una riga esistente se il file le
     * ha vuote (`null` o stringa vuota).
     *
     * @param string[] $columns
     */
    public function keepWhenEmpty(array $columns): self
    {
        return new self($this->singleton, $this->excludeColumns, $this->keepIds, $this->localOnly, $columns);
    }

    /**
     * Mantieni gli `id` tra gli ambienti (solo tabelle multi-row).
     */
    public function keepIds(): self
    {
        return new self($this->singleton, $this->excludeColumns, true, $this->localOnly, $this->keepWhenEmptyColumns);
    }

    /**
     * Tabella modificabile solo con `APP_ENV=local`.
     */
    public function localOnly(): self
    {
        return new self($this->singleton, $this->excludeColumns, $this->keepIds, true, $this->keepWhenEmptyColumns);
    }
}
