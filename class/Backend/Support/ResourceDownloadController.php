<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;

/**
 * Esporta i record di una Resource secondo i formati e le colonne
 * dichiarati in `tableLayoutSchema()->download(...)->downloadColumns(...)`.
 *
 * Il vecchio `sqlExport()` legacy esportava tutte le colonne della
 * tabella senza filtri. Qui invece:
 *  - rispettiamo il `condition` di `querySchema()` (es. soft-delete);
 *  - rispettiamo l'ordine di default;
 *  - includiamo SOLO le colonne dichiarate, in ordine;
 *  - applichiamo i `callable($row): mixed` per le colonne computate;
 *  - usiamo le label custom o, in fallback, `Resource::getLabel()`.
 */
final class ResourceDownloadController
{
    private array $tableLayoutSchema;
    private array $querySchema;
    private string $modelClass;
    private string $slug;

    private function __construct(
        private readonly string $resourceClass,
    ) {
        if (!is_subclass_of($this->resourceClass, Resource::class)) {
            throw new RuntimeException("{$this->resourceClass} deve estendere ".Resource::class);
        }

        $this->tableLayoutSchema = $this->resourceClass::tableLayoutSchema()->all();
        $this->querySchema = $this->resourceClass::querySchema();
        $this->modelClass = $this->resourceClass::modelClass();
        $this->slug = $this->resourceClass::slug();
    }

    public static function fromSlug(string $slug): self
    {
        return new self(ResourceRegistry::resolve(trim($slug)));
    }

    public function handle(string $format): void
    {
        $format = strtolower(trim($format));
        $download = (array) ($this->tableLayoutSchema['download'] ?? []);

        if (empty($download['enabled'])) {
            throw new RuntimeException("Export non abilitato per {$this->slug}");
        }

        $formats = (array) ($download['formats'] ?? []);

        if (!array_key_exists($format, $formats)) {
            throw new RuntimeException("Formato export non supportato: {$format}");
        }

        $columns = $this->resolveColumns((array) ($download['columns'] ?? []));
        $rows = $this->loadRows();

        $matrix = [$this->buildHeader($columns)];

        foreach ($rows as $row) {
            $matrix[] = $this->buildRow($columns, $row);
        }

        $filename = $this->resolveFilename($download);

        $this->dispatch($format, $matrix, $filename);
    }

    /**
     * Normalizza le colonne in array di entry con `field`, `label`
     * risolta, `value` (stringa nome campo o callable). Se l'utente non
     * ha settato `downloadColumns()`, espone tutte le colonne dichiarate
     * in `tableSchema()` (escluse quelle di tipo `button`), allineato col
     * comportamento "esporta tutto" del legacy.
     *
     * @param array<int, array<string, mixed>> $configured
     * @return array<int, array{field: string, label: string, value: string|callable|null}>
     */
    private function resolveColumns(array $configured): array
    {
        if ($configured === []) {
            $configured = $this->fallbackColumnsFromTableSchema();
        }

        $resolved = [];

        foreach ($configured as $entry) {
            $field = trim((string) ($entry['field'] ?? ''));
            $value = $entry['value'] ?? null;
            $label = isset($entry['label']) && is_string($entry['label']) && trim($entry['label']) !== ''
                ? trim($entry['label'])
                : null;

            if ($field === '' && !is_callable($value)) {
                continue;
            }

            if ($label === null) {
                $label = $field !== ''
                    ? (string) ($this->resourceClass::getLabel($field) ?: $this->titleize($field))
                    : '';
            }

            $resolved[] = [
                'field' => $field,
                'label' => $label,
                'value' => $value,
            ];
        }

        return $resolved;
    }

    /**
     * @return array<int, array{field: string, label: ?string, value: ?string}>
     */
    private function fallbackColumnsFromTableSchema(): array
    {
        $columns = [];

        foreach ($this->resourceClass::tableSchema() as $column) {
            if (!is_object($column) || !property_exists($column, 'name')) {
                continue;
            }

            $arr = method_exists($column, 'toArray') ? (array) $column->toArray() : [];

            if (($arr['type'] ?? null) === 'button') {
                continue;
            }

            $columns[] = ['field' => (string) $column->name, 'label' => null, 'value' => null];
        }

        return $columns;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadRows(): array
    {
        $condition = $this->querySchema['condition'] ?? null;
        $orderColumn = (string) ($this->querySchema['order']['column'] ?? 'creation');
        $orderDirection = (string) ($this->querySchema['order']['direction'] ?? 'DESC');

        $rows = ($this->modelClass)::find($condition, null, $orderColumn, $orderDirection);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int, array{field: string, label: string, value: mixed}> $columns
     */
    private function buildHeader(array $columns): array
    {
        return array_map(static fn (array $column): string => (string) $column['label'], $columns);
    }

    /**
     * @param array<int, array{field: string, label: string, value: mixed}> $columns
     * @param array<string, mixed> $row
     */
    private function buildRow(array $columns, array $row): array
    {
        $cells = [];

        foreach ($columns as $column) {
            $value = $column['value'];

            if (is_callable($value)) {
                $cells[] = $value($row);
                continue;
            }

            $field = is_string($value) && $value !== '' ? $value : $column['field'];
            $cells[] = $row[$field] ?? '';
        }

        return $cells;
    }

    private function resolveFilename(array $download): string
    {
        $configured = isset($download['filename']) && is_string($download['filename'])
            ? trim($download['filename'])
            : '';

        if ($configured !== '') {
            return $configured;
        }

        return $this->slug.'-'.date('Y-m-d');
    }

    private function dispatch(string $format, array $matrix, string $filename): void
    {
        match ($format) {
            'csv' => arrayToCsv($matrix, $filename),
            'xlsx', 'xls' => arrayToXlsx($matrix, $filename),
            default => throw new RuntimeException("Dispatch export non implementato: {$format}"),
        };
    }

    private function titleize(string $field): string
    {
        $clean = trim(str_replace(['_', '-'], ' ', $field));

        return $clean === '' ? '' : (function_exists('mb_convert_case')
            ? mb_convert_case($clean, MB_CASE_TITLE, 'UTF-8')
            : ucwords($clean));
    }
}
