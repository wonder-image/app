<?php

namespace Wonder\App\Support;

use Wonder\App\ResourceSchema\Input;

/**
 * Valore ed etichetta di ogni riga di un repeater, per ogni colonna su cui si
 * può raggruppare.
 *
 * Sta qui e non nel renderer perché è l'unica parte del raggruppamento che si
 * può provare senza un browser: il renderer stampa quello che questa classe
 * decide, e il JS legge senza sapere niente di opzioni ed etichette.
 *
 * Le colonne arrivano in due forme, `Input` o array descrittore, come le
 * accetta il renderer: chi chiama non deve normalizzarle prima.
 */
final class RepeaterGroups
{
    /**
     * @param array<int, mixed> $columns colonne del repeater
     * @param array<string, mixed> $rows righe, per chiave di riga
     * @param list<string> $groupBy chiavi delle colonne raggruppabili
     * @return array<string, array<string, array{value: string, label: string}>>
     */
    public static function of(array $columns, array $rows, array $groupBy): array
    {
        $groups = [];

        foreach ($groupBy as $key) {
            $key = trim((string) $key);
            $column = $key === '' ? null : self::columnByKey($columns, $key);

            if ($column === null) {
                continue;
            }

            foreach ($rows as $rowKey => $row) {
                $value = is_array($row) ? ($row[$key] ?? '') : '';
                $value = is_scalar($value) ? (string) $value : '';

                $groups[(string) $rowKey][$key] = [
                    'value' => $value,
                    'label' => self::labelOf($column, $value),
                ];
            }
        }

        return $groups;
    }

    /** L'etichetta di un valore: l'opzione se c'è, altrimenti il valore stesso. */
    public static function labelOf(mixed $column, mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        if ($value === '') {
            return '';
        }

        foreach (self::optionsOf($column) as $optionValue => $label) {
            if ((string) $optionValue === $value) {
                return is_scalar($label) ? (string) $label : $value;
            }
        }

        return $value;
    }

    /** La colonna con quella chiave, o `null`. */
    public static function columnByKey(array $columns, string $key): mixed
    {
        foreach ($columns as $column) {
            if (self::nameOf($column) === $key) {
                return $column;
            }
        }

        return null;
    }

    public static function nameOf(mixed $column): string
    {
        if ($column instanceof Input) {
            return trim($column->name);
        }

        return is_array($column) ? trim((string) ($column['name'] ?? '')) : '';
    }

    /** L'etichetta della colonna: è quella che legge il selettore. */
    public static function labelOfColumn(mixed $column): string
    {
        if ($column instanceof Input) {
            return trim((string) $column->get('label'));
        }

        return is_array($column) ? trim((string) ($column['label'] ?? '')) : '';
    }

    /** @return array<array-key, mixed> */
    private static function optionsOf(mixed $column): array
    {
        $options = $column instanceof Input
            ? $column->get('options')
            : (is_array($column) ? ($column['options'] ?? []) : []);

        return is_array($options) ? $options : [];
    }
}
