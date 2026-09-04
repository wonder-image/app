<?php

namespace Wonder\App\Diagnostics;

use Wonder\App\Debug;

/**
 * Osservabilità dev-only del consumo di memoria per request frontend.
 * Inerte in produzione (gate Debug::enabled()).
 * Vedi docs/app/concetti/performance-memoria.md.
 */
final class MemoryProfiler
{
    /** @var list<array{model: string, rows: int}> */
    private static array $queryNotes = [];

    private static bool $registered = false;

    /** @var (callable(string): void)|null Sink iniettabile; null => error_log. */
    private static $sink = null;

    /** Aggancia report() allo shutdown, una sola volta, se debug attivo. */
    public static function register(): void
    {
        if (self::$registered || !Debug::enabled()) {
            return;
        }

        self::$registered = true;

        register_shutdown_function([self::class, 'report']);
    }

    /** Annota un fetch pesante (>= soglia righe). No-op se debug spento. */
    public static function noteQuery(string $model, int $rows): void
    {
        if (!Debug::enabled() || $rows < self::rowsThreshold()) {
            return;
        }

        self::$queryNotes[] = ['model' => $model, 'rows' => $rows];
    }

    /** Emette una riga di report memoria. No-op se debug spento. */
    public static function report(): void
    {
        if (!Debug::enabled()) {
            return;
        }

        $line = self::formatReport(
            (float) memory_get_peak_usage(true),
            (string) ($_SERVER['REQUEST_URI'] ?? ''),
            self::heaviest(),
            self::memThresholdMb()
        );

        $sink = self::$sink ?? static fn (string $l): mixed => error_log($l);

        $sink($line);
    }

    /**
     * Formattazione pura (testabile senza stato globale).
     *
     * @param array{model: string, rows: int}|null $heaviest
     */
    public static function formatReport(
        float $peakBytes,
        string $uri,
        ?array $heaviest,
        float $thresholdMb
    ): string {
        $peakMb = $peakBytes / 1048576;
        $level = $peakMb >= $thresholdMb ? 'WARN' : 'INFO';
        $heaviestLabel = $heaviest === null
            ? '-'
            : sprintf('%s::all():%d', $heaviest['model'], $heaviest['rows']);

        return sprintf(
            '[MEM][%s] %s peak=%.1fMB heaviest=%s',
            $level,
            $uri === '' ? '-' : $uri,
            $peakMb,
            $heaviestLabel
        );
    }

    /** Inietta un sink di cattura nei test; null ripristina error_log. */
    public static function setSink(?callable $sink): void
    {
        self::$sink = $sink;
    }

    /** Azzera lo stato (uso nei test). */
    public static function reset(): void
    {
        self::$queryNotes = [];
        self::$registered = false;
        self::$sink = null;
    }

    /** @return array{model: string, rows: int}|null */
    private static function heaviest(): ?array
    {
        $heaviest = null;

        foreach (self::$queryNotes as $note) {
            if ($heaviest === null || $note['rows'] > $heaviest['rows']) {
                $heaviest = $note;
            }
        }

        return $heaviest;
    }

    private static function rowsThreshold(): int
    {
        $raw = $_ENV['MEMORY_PROFILE_ROWS_THRESHOLD']
            ?? ($_SERVER['MEMORY_PROFILE_ROWS_THRESHOLD'] ?? null);
        $value = (int) $raw;

        return $value > 0 ? $value : 500;
    }

    private static function memThresholdMb(): float
    {
        $raw = $_ENV['MEMORY_PROFILE_THRESHOLD_MB']
            ?? ($_SERVER['MEMORY_PROFILE_THRESHOLD_MB'] ?? null);
        $value = (float) $raw;

        return $value > 0 ? $value : 128.0;
    }
}
