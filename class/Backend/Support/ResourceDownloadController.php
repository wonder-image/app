<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\Dataset\Column;
use Wonder\App\Dataset\ResourceSource;
use Wonder\App\Import\Profile;
use Wonder\App\ResourceRegistry;

/**
 * Esporta i record di una Resource nei formati di `download()`: colonne e
 * righe vengono da `ResourceSource` (condizione e ordine di `querySchema()`,
 * visibilità per riga). Con `$layoutId` le colonne, l'ordine e le
 * intestazioni sono quelli del tracciato salvato in «Tracciati file».
 */
final class ResourceDownloadController
{
    private function __construct(
        private readonly ResourceSource $source,
    ) {
    }

    public static function fromSlug(string $slug): self
    {
        return new self(new ResourceSource(ResourceRegistry::resolve(trim($slug))));
    }

    public function source(): ResourceSource
    {
        return $this->source;
    }

    public function handle(string $format, ?int $layoutId = null): void
    {
        $format = strtolower(trim($format));
        $download = $this->source->download();
        $slug = $this->source->id();

        if (empty($download['enabled'])) {
            throw new RuntimeException("Export non abilitato per {$slug}");
        }

        if (!array_key_exists($format, (array) ($download['formats'] ?? []))) {
            throw new RuntimeException("Formato export non supportato: {$format}");
        }

        if (is_callable($download['handler'] ?? null)) {
            ($download['handler'])($format);

            return;
        }

        $this->dispatch(
            $format,
            $this->matrix($layoutId),
            $this->resolveFilename($download),
            !empty($download['text']),
            (bool) ($download['formula_safe'] ?? true),
        );
    }

    /**
     * Intestazione e righe del file. Un tracciato di un'altra fonte, o
     * senza colonne valide, lascia quelle del codice.
     *
     * @return list<list<mixed>>
     */
    public function matrix(?int $layoutId = null): array
    {
        $columns = ($layoutId !== null && $layoutId > 0 ? Profile::for($this->source, $layoutId) : null)
            ?? $this->source->columns();

        $matrix = [array_map(static fn (Column $column): string => $column->header, $columns)];

        foreach ($this->source->rows() as $row) {
            $row = (array) $row;
            $matrix[] = array_map(static fn (Column $column): mixed => $column->export($row), $columns);
        }

        return $matrix;
    }

    private function resolveFilename(array $download): string
    {
        $configured = is_string($download['filename'] ?? null) ? trim($download['filename']) : '';

        return $configured !== '' ? $configured : $this->source->id().'-'.date('Y-m-d');
    }

    private function dispatch(string $format, array $matrix, string $filename, bool $asText, bool $formulaSafe = true): void
    {
        match ($format) {
            'csv' => arrayToCsv($asText ? SpreadsheetText::matrix($matrix) : $matrix, $filename, $formulaSafe),
            'xlsx', 'xls' => arrayToXlsx($matrix, $filename, $asText),
            default => throw new RuntimeException("Dispatch export non implementato: {$format}"),
        };
    }
}
