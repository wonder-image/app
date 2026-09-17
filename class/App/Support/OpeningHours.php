<?php

namespace Wonder\App\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Orari di una sede sul modello di Google (`regularHours`, `specialHours`).
 *
 * Classe pura: lavora su righe già lette dal database.
 * - Orari: `hours_type`, `open_day`, `open_time`, `close_day`, `close_time`.
 * - Orari speciali: `start_date`, `end_date`, `closed`, `open_time`, `close_time`.
 *
 * `24:00` è la mezzanotte a fine giornata; una riga senza chiusura indica
 * "sempre aperto"; gli orari speciali valgono solo per il tipo `regular`.
 */
final class OpeningHours
{
    public const REGULAR = 'regular';

    public const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /** Tipi di orari secondari di Google, in minuscolo. */
    public const SECONDARY_TYPES = [
        'drive_through',
        'happy_hour',
        'delivery',
        'takeout',
        'kitchen',
        'breakfast',
        'lunch',
        'dinner',
        'brunch',
        'pickup',
        'access',
        'senior_hours',
        'online_service_hours',
    ];

    /**
     * Fasce effettive di una data; lista vuota = chiuso.
     *
     * @return list<array{open: string, close: string, overnight: bool}>
     */
    public static function periodsFor(
        array $hours,
        array $specialHours,
        DateTimeInterface $date,
        string $type = self::REGULAR
    ): array {
        if ($type === self::REGULAR) {
            $special = self::specialPeriodsFor($specialHours, $date->format('Y-m-d'));

            if ($special !== null) {
                return $special;
            }
        }

        $rows = self::rowsOfType($hours, $type);

        if (self::alwaysOpen($rows)) {
            return [['open' => '00:00', 'close' => '24:00', 'overnight' => false]];
        }

        $weekday = $date->format('D');
        $periods = [];

        foreach ($rows as $row) {
            if ((string) ($row['open_day'] ?? '') !== $weekday) {
                continue;
            }

            $open = self::time($row['open_time'] ?? '');
            $close = self::time($row['close_time'] ?? '');
            $closeDay = trim((string) ($row['close_day'] ?? ''));

            if ($open === '' || $close === '') {
                continue;
            }

            $periods[] = [
                'open' => $open,
                'close' => $close,
                'overnight' => $closeDay !== '' && $closeDay !== $weekday,
            ];
        }

        return self::sortPeriods($periods);
    }

    public static function isOpenAt(
        array $hours,
        array $specialHours,
        DateTimeInterface $at,
        string $type = self::REGULAR
    ): bool {
        $time = $at->format('H:i');

        foreach (self::periodsFor($hours, $specialHours, $at, $type) as $period) {
            $afterOpen = $time >= $period['open'];

            if ($period['overnight'] ? $afterOpen : ($afterOpen && $time < $period['close'])) {
                return true;
            }
        }

        $yesterday = DateTimeImmutable::createFromInterface($at)->sub(new DateInterval('P1D'));

        foreach (self::periodsFor($hours, $specialHours, $yesterday, $type) as $period) {
            if ($period['overnight'] && $time < $period['close']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Orari per giorno nel formato di `prettyTimeTable()`.
     *
     * @return array<string, list<array{from: string, to: string}>>
     */
    public static function timetable(array $hours, string $type = self::REGULAR): array
    {
        $rows = self::rowsOfType($hours, $type);
        $timetable = [];

        foreach (self::DAYS as $day) {
            if (self::alwaysOpen($rows)) {
                $timetable[$day] = [['from' => '00:00', 'to' => '24:00']];
                continue;
            }

            $ranges = [];

            foreach ($rows as $row) {
                $open = self::time($row['open_time'] ?? '');
                $close = self::time($row['close_time'] ?? '');

                if ((string) ($row['open_day'] ?? '') === $day && $open !== '' && $close !== '') {
                    $ranges[] = ['from' => $open, 'to' => $close];
                }
            }

            if ($ranges !== []) {
                usort($ranges, static fn (array $a, array $b): int => strcmp($a['from'], $b['from']));
                $timetable[$day] = $ranges;
            }
        }

        return $timetable;
    }

    /**
     * Orari speciali che finiscono da `$from` in avanti, ordinati per data.
     *
     * @return list<array<string, mixed>>
     */
    public static function upcomingSpecial(array $specialHours, DateTimeInterface $from): array
    {
        $today = $from->format('Y-m-d');
        $rows = [];

        foreach ($specialHours as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start = self::date($row['start_date'] ?? '');
            $end = self::date($row['end_date'] ?? '');

            if ($start === '' || ($end !== '' ? $end : $start) < $today) {
                continue;
            }

            $row['start_date'] = $start;
            $row['end_date'] = $end;
            $row['closed'] = self::isClosed($row);
            $rows[] = $row;
        }

        usort($rows, static fn (array $a, array $b): int => [$a['start_date'], (string) ($a['open_time'] ?? '')]
            <=> [$b['start_date'], (string) ($b['open_time'] ?? '')]);

        return $rows;
    }

    /** `HH:MM` da `H:i`, `H:i:s` o `24:00`; stringa vuota se non valido. */
    public static function time(mixed $value): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim((string) $value), $matches) !== 1) {
            return '';
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($minutes > 59 || $hours > 24 || ($hours === 24 && $minutes !== 0)) {
            return '';
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /** `Y-m-d` da `Y-m-d` o `Y-m-d H:i:s`; stringa vuota se non valido. */
    public static function date(mixed $value): string
    {
        $value = substr(trim((string) $value), 0, 10);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && $value !== '0000-00-00' ? $value : '';
    }

    public static function nextDay(string $day): string
    {
        $index = array_search($day, self::DAYS, true);

        return $index === false ? '' : self::DAYS[($index + 1) % 7];
    }

    /** @return list<array{open: string, close: string, overnight: bool}>|null `null` se nessun orario speciale vale per la data */
    private static function specialPeriodsFor(array $specialHours, string $day): ?array
    {
        $periods = [];
        $found = false;

        foreach ($specialHours as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start = self::date($row['start_date'] ?? '');
            $end = self::date($row['end_date'] ?? '');

            if ($start === '') {
                continue;
            }

            if (self::isClosed($row)) {
                if ($day >= $start && $day <= ($end !== '' ? $end : $start)) {
                    return [];
                }

                continue;
            }

            $open = self::time($row['open_time'] ?? '');
            $close = self::time($row['close_time'] ?? '');

            if ($start !== $day || $open === '' || $close === '') {
                continue;
            }

            $found = true;
            $periods[] = [
                'open' => $open,
                'close' => $close,
                'overnight' => $close !== '24:00' && $close <= $open,
            ];
        }

        return $found ? self::sortPeriods($periods) : null;
    }

    private static function isClosed(array $row): bool
    {
        return in_array(strtolower(trim((string) ($row['closed'] ?? 'true'))), ['true', '1'], true);
    }

    private static function rowsOfType(array $hours, string $type): array
    {
        return array_values(array_filter(
            $hours,
            static fn (mixed $row): bool => is_array($row)
                && ((trim((string) ($row['hours_type'] ?? '')) ?: self::REGULAR) === $type)
        ));
    }

    private static function alwaysOpen(array $rows): bool
    {
        foreach ($rows as $row) {
            if (self::time($row['open_time'] ?? '') !== '' && trim((string) ($row['close_time'] ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    private static function sortPeriods(array $periods): array
    {
        usort($periods, static fn (array $a, array $b): int => strcmp($a['open'], $b['open']));

        return $periods;
    }
}
