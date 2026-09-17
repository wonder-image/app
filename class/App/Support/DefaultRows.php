<?php

namespace Wonder\App\Support;

use RuntimeException;
use Wonder\App\Model;

/**
 * Inserimento idempotente delle righe precaricate dei moduli.
 *
 * Regola unica: una riga si inserisce solo se il suo valore chiave non
 * esiste, contando anche le righe cancellate; le righe esistenti non si
 * modificano mai.
 */
final class DefaultRows
{
    private int $total = 0;

    /**
     * @param class-string<Model> $modelClass
     * @param list<array<string, mixed>> $rows
     */
    public function ensure(string $modelClass, string $keyColumn, array $rows): int
    {
        self::assertModel($modelClass);
        self::assertColumn($keyColumn);

        $table = $modelClass::$table;
        $existing = $modelClass::query()->Select($table, null, null, null, null, $keyColumn)->row;
        $existingKeys = array_column(is_array($existing) ? $existing : [], $keyColumn);
        $inserted = 0;

        foreach (self::missingRows($rows, $keyColumn, $existingKeys) as $row) {
            $result = $modelClass::query()->Insert($table, $modelClass::prepare($row));

            if (!empty($result->success)) {
                $inserted++;
            }
        }

        $this->total += $inserted;

        return $inserted;
    }

    /**
     * Crea la riga `id = 1` di un Model a riga unica se manca.
     *
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $values
     */
    public function ensureSingleton(string $modelClass, array $values): int
    {
        self::assertModel($modelClass);

        $table = $modelClass::$table;

        if ($modelClass::query()->Select($table, ['id' => 1], 1)->exists ?? false) {
            return 0;
        }

        $result = $modelClass::query()->Insert($table, array_merge(['id' => 1], $modelClass::prepare($values)));
        $inserted = !empty($result->success) ? 1 : 0;
        $this->total += $inserted;

        return $inserted;
    }

    public function total(): int
    {
        return $this->total;
    }

    /**
     * Righe il cui valore chiave non è tra quelli esistenti (classe pura).
     *
     * @param array<int, mixed> $rows
     * @param array<int, mixed> $existingKeys
     * @return list<array<string, mixed>>
     */
    public static function missingRows(array $rows, string $keyColumn, array $existingKeys): array
    {
        $known = [];

        foreach ($existingKeys as $key) {
            if (is_scalar($key) && (string) $key !== '') {
                $known[(string) $key] = true;
            }
        }

        $missing = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = $row[$keyColumn] ?? null;

            if (!is_scalar($key) || (string) $key === '') {
                throw new RuntimeException("Riga precaricata senza {$keyColumn}.");
            }

            if (isset($known[(string) $key])) {
                continue;
            }

            $known[(string) $key] = true;
            $missing[] = $row;
        }

        return $missing;
    }

    private static function assertModel(string $modelClass): void
    {
        if (!class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException("{$modelClass} deve estendere ".Model::class.'.');
        }
    }

    private static function assertColumn(string $column): void
    {
        if (preg_match('/^[a-z0-9_]+$/', $column) !== 1) {
            throw new RuntimeException("Colonna chiave non valida: {$column}");
        }
    }
}
