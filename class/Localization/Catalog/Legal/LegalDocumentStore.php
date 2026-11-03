<?php

namespace Wonder\Localization\Catalog\Legal;

/**
 * Accesso ai documenti di `legal_documents` per la sorgente `legal`. Le righe
 * hanno le colonne della tabella; `content_snapshot` è la stringa JSON dei
 * blocchi dell'editor.
 */
interface LegalDocumentStore
{
    /** @return array<string, string> tipo => etichetta */
    public function types(): array;

    /** Documento attivo più recente di un tipo in una lingua. */
    public function active(string $type, string $lang): ?array;

    public function find(string $type, string $version, string $lang): ?array;

    /** Il documento è già stato accettato (lo referenzia un `consent_events`). */
    public function accepted(int $id): bool;

    public function insert(array $row): int;

    public function update(int $id, array $values): void;
}
