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

        // Dopo i due controlli e prima del gestore, che può chiudere la risposta.
        self::remember($slug, $format, $layoutId);

        if (is_callable($download['handler'] ?? null)) {
            // Il tracciato arriva al gestore solo per i formati che lo prevedono.
            $layouts = self::layouts($download, $slug);
            $offered = $layoutId !== null && $layouts['source'] !== ''
                && ($layouts['formats'] === null || in_array($format, $layouts['formats'], true));

            ($download['handler'])($format, $offered ? $layoutId : null);

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
     * Formato richiesto: parametro di rotta, altrimenti `?format=`.
     *
     * @param array<string, mixed> $routeParameters
     * @param array<string, mixed> $query
     */
    public static function formatFrom(array $routeParameters, array $query): string
    {
        $format = $routeParameters['format'] ?? '';

        if (!is_string($format) || trim($format) === '') {
            $format = $query['format'] ?? '';
        }

        $format = is_string($format) ? strtolower(trim($format)) : '';

        return preg_match('/^[a-z]+$/', $format) === 1 ? $format : '';
    }

    /**
     * Ultima scelta di export di questa Resource nella sessione corrente.
     *
     * @return array{format: string, layout: ?int}|null
     */
    public static function lastChoice(string $slug): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $choice = $_SESSION['wi_resource_export'][$slug] ?? null;

        if (!is_array($choice) || !is_string($choice['format'] ?? null)) {
            return null;
        }

        $layout = $choice['layout'] ?? null;

        return ['format' => $choice['format'], 'layout' => is_int($layout) ? $layout : null];
    }

    public static function remember(string $slug, string $format, ?int $layoutId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (!is_array($_SESSION['wi_resource_export'] ?? null)) {
            $_SESSION['wi_resource_export'] = [];
        }

        $_SESSION['wi_resource_export'][$slug] = ['format' => $format, 'layout' => $layoutId];
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

    /**
     * Di quale fonte sono i tracciati che «Esporta» offre, e per quali
     * formati (null = tutti). Con un `downloadUsing()` senza
     * `downloadLayouts()` la fonte è vuota: niente tracciati.
     *
     * @return array{source: string, formats: list<string>|null}
     */
    public static function layouts(array $download, string $slug): array
    {
        $layouts = is_array($download['layouts'] ?? null) ? $download['layouts'] : [];
        $source = trim((string) ($layouts['source'] ?? ''));

        if ($source === '' && !is_callable($download['handler'] ?? null)) {
            $source = $slug;
        }

        return [
            'source' => $source,
            'formats' => is_array($layouts['formats'] ?? null) ? array_values(array_map('strval', $layouts['formats'])) : null,
        ];
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
