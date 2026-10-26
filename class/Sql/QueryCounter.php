<?php

namespace Wonder\Sql;

use Wonder\App\Debug;

/**
 * Contatore delle query eseguite da `Query` nella richiesta, raggruppate per
 * testo normalizzato (letterali e numeri sostituiti da `?`).
 * Solo in Debug: fuori da Debug `note()` è un check booleano memoizzato.
 * Vedi docs/performance.md.
 */
final class QueryCounter
{
    /** @var array<string, int> testo normalizzato => esecuzioni */
    private static array $groups = [];

    private static int $total = 0;

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

    /** Conta una query. No-op se debug spento. */
    public static function note(string $sql): void
    {
        if (!Debug::enabled()) {
            return;
        }

        self::$total++;

        $key = self::normalize($sql);
        self::$groups[$key] = (self::$groups[$key] ?? 0) + 1;
    }

    public static function total(): int
    {
        return self::$total;
    }

    /**
     * Gruppi in ordine di esecuzioni decrescenti.
     *
     * @return array<string, int>
     */
    public static function groups(?int $limit = null): array
    {
        $groups = self::$groups;
        arsort($groups);

        return $limit === null ? $groups : array_slice($groups, 0, $limit, true);
    }

    /** Emette il riepilogo della richiesta. No-op se debug spento. */
    public static function report(): void
    {
        if (!Debug::enabled()) {
            return;
        }

        $line = self::formatReport(
            self::$total,
            (string) ($_SERVER['REQUEST_URI'] ?? ''),
            self::groups(self::topLimit())
        );

        $sink = self::$sink ?? static fn (string $l): mixed => error_log($l);

        $sink($line);
    }

    /**
     * Formattazione pura (testabile senza stato globale).
     *
     * @param array<string, int> $top
     */
    public static function formatReport(int $total, string $uri, array $top): string
    {
        $parts = [];

        foreach ($top as $sql => $count) {
            $parts[] = $count.'x '.$sql;
        }

        return sprintf(
            '[SQL] %s queries=%d top=%s',
            $uri === '' ? '-' : $uri,
            $total,
            $parts === [] ? '-' : implode(' | ', $parts)
        );
    }

    /** Toglie i valori dal testo: stessa forma di query, stesso gruppo. */
    public static function normalize(string $sql): string
    {
        // Letterali fra apici singoli/doppi (con escape) => ?
        $sql = (string) preg_replace("/'(?:[^'\\\\]|\\\\.|'')*'/s", '?', $sql);
        $sql = (string) preg_replace('/"(?:[^"\\\\]|\\\\.|"")*"/s', '?', $sql);
        // Numeri isolati (non dentro identificatori come `t1`) => ?
        $sql = (string) preg_replace('/(?<![\w`])-?\d+(?:\.\d+)?(?![\w`])/', '?', $sql);
        // Liste IN (?, ?, ?) => IN (?)
        $sql = (string) preg_replace('/\(\s*\?(?:\s*,\s*\?)+\s*\)/', '(?)', $sql);

        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }

    /** Inietta un sink di cattura nei test; null ripristina error_log. */
    public static function setSink(?callable $sink): void
    {
        self::$sink = $sink;
    }

    /** Azzera lo stato (uso nei test). */
    public static function reset(): void
    {
        self::$groups = [];
        self::$total = 0;
        self::$registered = false;
        self::$sink = null;
    }

    private static function topLimit(): int
    {
        $value = (int) ($_ENV['QUERY_COUNTER_TOP'] ?? ($_SERVER['QUERY_COUNTER_TOP'] ?? 0));

        return $value > 0 ? $value : 10;
    }
}
