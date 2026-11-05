<?php

namespace Wonder\App\Dataset;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Il tipo di una colonna: come il valore esce in un file (`format()`) e come
 * si rilegge (`parse()`). Rileggere ciò che è uscito ridà lo stesso valore.
 */
enum Type: string
{
    case Text = 'text';
    case Number = 'number';
    case Money = 'money';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case List = 'list';

    /** Separatore dei valori di una lista. */
    public const LIST_SEPARATOR = '|';

    public function format(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return match ($this) {
            self::Text => is_scalar($value) ? (string) $value : '',
            self::Number => ($n = self::number($value)) === null ? '' : self::plain($n),
            self::Money => ($n = self::number($value)) === null ? '' : number_format($n, 2, '.', ''),
            self::Boolean => ($b = self::boolean($value)) === null ? '' : ($b ? 'true' : 'false'),
            self::Date => self::date($value)?->format('Y-m-d') ?? '',
            self::DateTime => self::date($value)?->format('Y-m-d H:i:s') ?? '',
            self::List => implode(self::LIST_SEPARATOR, self::items($value)),
        };
    }

    public function parse(string $value): mixed
    {
        $value = trim($value);

        if ($value === '') {
            return $this === self::List ? [] : null;
        }

        return match ($this) {
            self::Text => $value,
            self::Number => self::number($value),
            self::Money => ($n = self::number($value)) === null ? null : round($n, 2),
            self::Boolean => self::boolean($value),
            self::Date => self::date($value)?->format('Y-m-d'),
            self::DateTime => self::date($value)?->format('Y-m-d H:i:s'),
            self::List => self::items($value),
        };
    }

    /** Numero da `12.5`, `12,5`, `1.234,56` o `1,234.56`; null se non lo è. */
    private static function number(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $value = str_replace([' ', "\u{00A0}", "'"], '', trim($value));
        $comma = strrpos($value, ',');
        $dot = strrpos($value, '.');

        if ($comma !== false && ($dot === false || $comma > $dot)) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif ($comma !== false) {
            $value = str_replace(',', '', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return str_contains($value, '.') || stripos($value, 'e') !== false ? (float) $value : (int) $value;
    }

    /** Il numero senza zeri inutili e senza notazione esponenziale. */
    private static function plain(int|float $number): string
    {
        if (is_int($number)) {
            return (string) $number;
        }

        $text = rtrim(rtrim(sprintf('%.10F', $number), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }

    private static function boolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value != 0;
        }

        return match (strtolower(trim((string) $value))) {
            'true', '1', 'si', 'sì', 'yes', 'y', 'x', 'vero' => true,
            'false', '0', 'no', 'n', 'falso' => false,
            default => null,
        };
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (!is_string($value) || trim($value) === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        $value = trim($value);

        // Data italiana (gg/mm/aaaa, anche con l'ora): prima di strtotime, che la legge all'americana.
        if (preg_match('~^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$~', $value, $m)) {
            if (!checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                return null;
            }

            return (new DateTimeImmutable())->setDate((int) $m[3], (int) $m[2], (int) $m[1])
                ->setTime((int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0));
        }

        try {
            return preg_match('~^\d{4}-\d{2}-\d{2}~', $value) ? new DateTimeImmutable($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    private static function items(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = str_starts_with(trim($value), '[') ? json_decode($value, true) : null;
            $value = is_array($decoded) ? $decoded : explode(self::LIST_SEPARATOR, $value);
        }

        if (!is_array($value)) {
            return is_scalar($value) ? self::items((string) $value) : [];
        }

        $items = [];

        foreach ($value as $item) {
            $item = is_scalar($item) ? trim((string) $item) : '';

            if ($item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }
}
