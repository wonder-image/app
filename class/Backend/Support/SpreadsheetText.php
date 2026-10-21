<?php

namespace Wonder\Backend\Support;

/**
 * Celle di un export come testo che un foglio di calcolo non esegue: un
 * apice davanti a `=`, `+`, `-`, `@`, tabulazione e ritorno a capo, così
 * `=HYPERLINK(...)` scritto in un modulo pubblico resta una scritta.
 */
final class SpreadsheetText
{
    public static function cell(mixed $value): string
    {
        $value = $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : (string) (is_bool($value) ? (int) $value : $value);

        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Default dei CSV: apice solo davanti alle celle che sembrano formule.
     * Numeri come `-5` o `+39` e segni isolati restano come sono; ogni
     * altro valore esce invariato.
     */
    public static function formulaSafe(mixed $value): mixed
    {
        if (is_string($value) && strlen($value) > 1 && !is_numeric($value)
            && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $matrix
     * @return array<int|string, mixed>
     */
    public static function formulaSafeMatrix(array $matrix): array
    {
        return array_map(
            static fn ($row) => is_array($row) ? array_map([self::class, 'formulaSafe'], $row) : self::formulaSafe($row),
            $matrix
        );
    }

    /**
     * @param array<int, array<int, mixed>> $matrix
     * @return array<int, array<int, string>>
     */
    public static function matrix(array $matrix): array
    {
        return array_map(
            static fn ($row): array => array_map([self::class, 'cell'], is_array($row) ? array_values($row) : [$row]),
            $matrix
        );
    }
}
