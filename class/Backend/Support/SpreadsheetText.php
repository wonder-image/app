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
