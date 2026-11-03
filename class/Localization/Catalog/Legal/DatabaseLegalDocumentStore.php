<?php

namespace Wonder\Localization\Catalog\Legal;

use mysqli;
use RuntimeException;
use Wonder\Sql\Query;

final class DatabaseLegalDocumentStore implements LegalDocumentStore
{
    private Query $query;

    public function __construct(?mysqli $mysqli = null)
    {
        $this->query = new Query($mysqli);
    }

    public function types(): array
    {
        return legalDocumentTypes();
    }

    public function active(string $type, string $lang): ?array
    {
        return $this->row(['doc_type' => $type, 'language_code' => $lang, 'active' => 'true'], 'published_at DESC, id');
    }

    public function find(string $type, string $version, string $lang): ?array
    {
        return $this->row(['doc_type' => $type, 'version' => $version, 'language_code' => $lang]);
    }

    public function accepted(int $id): bool
    {
        return $this->query->Count('consent_events', ['legal_document_id' => $id]) > 0;
    }

    public function insert(array $row): int
    {
        $result = $this->query->Insert('legal_documents', $row);

        if (!$result->success) {
            throw new RuntimeException('Documento non salvato.');
        }

        return (int) $result->insert_id;
    }

    public function update(int $id, array $values): void
    {
        if (!$this->query->Update('legal_documents', $values, 'id', $id)->success) {
            throw new RuntimeException('Documento non salvato.');
        }
    }

    private function row(array $condition, ?string $order = null): ?array
    {
        $result = $this->query->Select('legal_documents', $condition, 1, $order, $order === null ? null : 'DESC');

        return $result->exists && is_array($result->row) ? $result->row : null;
    }
}
