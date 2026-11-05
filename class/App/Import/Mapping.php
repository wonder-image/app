<?php

namespace Wonder\App\Import;

use Wonder\App\Dataset\Column;

/**
 * Abbina le intestazioni di un file alle colonne di un tracciato.
 *
 * Classe pura. Il confronto ignora maiuscole, spazi, punteggiatura e accenti:
 * «Codice articolo», «codice_articolo» e «CÒDICE ARTICOLO» sono lo stesso nome.
 */
final class Mapping
{
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        if (class_exists(\Normalizer::class)) {
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = (string) preg_replace('/\p{Mn}+/u', '', $text);
        }

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text);
    }

    /**
     * La colonna di ogni intestazione, o null se non la riconosce nessuno.
     * Vincono, nell'ordine: le intestazioni del tracciato scelto
     * (`$preferred`, chiave => intestazione), quella dell'export, la chiave e
     * gli altri nomi. Una colonna si abbina a un'intestazione sola: la prima.
     *
     * @param list<string> $headers
     * @param list<Column> $columns
     * @param array<string, string> $preferred
     * @return array<int, string|null>
     */
    public static function guess(array $headers, array $columns, array $preferred = []): array
    {
        $names = [];

        foreach ($preferred as $key => $header) {
            $names[] = [self::normalize((string) $header), (string) $key];
        }

        foreach ($columns as $column) {
            $names[] = [self::normalize($column->header), $column->key];
        }

        foreach ($columns as $column) {
            $names[] = [self::normalize($column->key), $column->key];

            foreach ($column->aliases as $alias) {
                $names[] = [self::normalize($alias), $column->key];
            }
        }

        $known = array_flip(array_map(static fn (Column $column): string => $column->key, $columns));
        $mapping = [];
        $taken = [];

        foreach ($headers as $index => $header) {
            $mapping[$index] = null;
            $normal = self::normalize((string) $header);

            if ($normal === '') {
                continue;
            }

            foreach ($names as [$name, $key]) {
                if ($name === $normal && isset($known[$key]) && !isset($taken[$key])) {
                    $mapping[$index] = $key;
                    $taken[$key] = true;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * Le celle di una riga per chiave di colonna; quelle senza colonna si
     * lasciano fuori.
     *
     * @param list<mixed> $cells
     * @param array<int, string|null> $mapping
     * @return array<string, string>
     */
    public static function apply(array $cells, array $mapping): array
    {
        $data = [];

        foreach ($mapping as $index => $key) {
            if ($key === null || $key === '') {
                continue;
            }

            $data[$key] = trim((string) ($cells[$index] ?? ''));
        }

        return $data;
    }

    /**
     * Le chiavi scelte per più di un'intestazione.
     *
     * @param array<int, string|null> $mapping
     * @return list<string>
     */
    public static function duplicates(array $mapping): array
    {
        $counts = array_count_values(array_filter($mapping, static fn ($key): bool => is_string($key) && $key !== ''));

        return array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));
    }

    /**
     * Le chiavi obbligatorie che nessuna intestazione usa.
     *
     * @param array<int, string|null> $mapping
     * @param list<Column> $columns
     * @return list<string>
     */
    public static function missing(array $mapping, array $columns): array
    {
        $used = array_flip(array_filter($mapping, static fn ($key): bool => is_string($key) && $key !== ''));
        $missing = [];

        foreach ($columns as $column) {
            if ($column->required && !isset($used[$column->key])) {
                $missing[] = $column->key;
            }
        }

        return $missing;
    }

    /**
     * Il resoconto di un abbinamento: le intestazioni riconosciute (con la
     * colonna e il campo in cui finiscono), quelle che non si leggono e le
     * colonne obbligatorie che mancano.
     *
     * @param list<string> $headers
     * @param array<int, string|null> $mapping
     * @param list<Column> $columns
     * @return array{
     *     recognized: list<array{index: int, header: string, column: Column}>,
     *     ignored: list<array{index: int, header: string}>,
     *     missing: list<Column>
     * }
     */
    public static function report(array $headers, array $mapping, array $columns): array
    {
        $byKey = [];

        foreach ($columns as $column) {
            $byKey[$column->key] = $column;
        }

        $recognized = [];
        $ignored = [];

        foreach ($headers as $index => $header) {
            $key = $mapping[$index] ?? null;

            if (is_string($key) && isset($byKey[$key])) {
                $recognized[] = ['index' => (int) $index, 'header' => (string) $header, 'column' => $byKey[$key]];
            } else {
                $ignored[] = ['index' => (int) $index, 'header' => (string) $header];
            }
        }

        return [
            'recognized' => $recognized,
            'ignored' => $ignored,
            'missing' => array_map(static fn (string $key): Column => $byKey[$key], self::missing($mapping, $columns)),
        ];
    }
}
