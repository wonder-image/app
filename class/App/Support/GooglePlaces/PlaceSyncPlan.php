<?php

namespace Wonder\App\Support\GooglePlaces;

use DateTimeImmutable;
use DateTimeInterface;
use Wonder\App\Support\OpeningHours;

/**
 * Che cosa scrivere in una sede dopo una lettura da Google. Classe pura.
 *
 * Con `$apply = false` restituisce solo le differenze. Con `$apply = true`
 * sostituisce, negli orari della sede:
 * - i tipi di orario che Google ha restituito e che sono diversi;
 * - gli orari speciali di Google da oggi in avanti. Una riga non di Google
 *   sulla stessa data ha la precedenza e blocca quella di Google.
 * Lo stato dell'attività non si applica mai: resta una differenza.
 *
 * Una sede che eredita gli orari dalla sede principale riceve orari propri:
 * quelli ereditati con i tipi di Google sostituiti, più gli orari speciali.
 */
final class PlaceSyncPlan
{
    private const HOUR_FIELDS = ['hours_type', 'open_day', 'open_time', 'close_day', 'close_time'];

    private const SPECIAL_FIELDS = ['start_date', 'end_date', 'closed', 'open_time', 'close_time', 'note', 'source'];

    /**
     * @param array{hours?: array, special_hours?: array, inherited?: bool, business_status?: ?string} $location orari effettivi della sede
     * @return array{hours: ?list<array<string, string>>, special_hours: ?list<array<string, string>>, differences: list<array{kind: string, message: string}>}
     */
    public static function make(array $location, array $google, DateTimeInterface $today, bool $apply): array
    {
        $hours = (array) ($location['hours'] ?? []);
        $special = (array) ($location['special_hours'] ?? []);
        $newHours = null;
        $newSpecial = null;

        if ($apply) {
            $changed = PlaceDiff::changedHoursTypes($hours, $google);

            if ($changed !== []) {
                $newHours = self::replaceTypes($hours, $google, $changed);
            }

            if (($google['has_special_hours'] ?? false) === true) {
                $rows = self::mergeSpecial($special, (array) ($google['special_hours'] ?? []), $today);

                if (!PlaceDiff::sameSpecialRows($rows, $special)) {
                    $newSpecial = $rows;
                }
            }

            // Gli orari speciali propri valgono solo insieme agli orari propri.
            if (($location['inherited'] ?? false) === true && ($newHours !== null || $newSpecial !== null)) {
                $newHours ??= self::withPositions(array_map(self::hourRow(...), array_values(array_filter($hours, 'is_array'))));
                $newSpecial ??= array_map(self::specialRow(...), array_values(array_filter($special, 'is_array')));
            }
        }

        return [
            'hours' => $newHours,
            'special_hours' => $newSpecial,
            'differences' => PlaceDiff::compare([
                'hours' => $newHours ?? $hours,
                'special_hours' => $newSpecial ?? $special,
                'business_status' => $location['business_status'] ?? null,
            ], $google, $today),
        ];
    }

    /** @param list<string> $types */
    private static function replaceTypes(array $hours, array $google, array $types): array
    {
        $rows = [];

        foreach ($hours as $row) {
            if (is_array($row) && !in_array(self::type($row), $types, true)) {
                $rows[] = self::hourRow($row);
            }
        }

        foreach ((array) ($google['hours'] ?? []) as $row) {
            if (is_array($row) && in_array(self::type($row), $types, true)) {
                $rows[] = self::hourRow($row);
            }
        }

        return self::withPositions($rows);
    }

    private static function mergeSpecial(array $special, array $googleSpecial, DateTimeInterface $today): array
    {
        $from = DateTimeImmutable::createFromInterface($today)->format('Y-m-d');
        $kept = [];

        foreach ($special as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start = OpeningHours::date($row['start_date'] ?? '');
            $end = OpeningHours::date($row['end_date'] ?? '');
            $isGoogle = trim((string) ($row['source'] ?? '')) === 'google';

            // Le righe di Google già passate restano; da oggi le riscrive Google.
            if ($isGoogle && ($end !== '' ? $end : $start) >= $from) {
                continue;
            }

            $kept[] = self::specialRow($row);
        }

        $rows = $kept;

        foreach ($googleSpecial as $row) {
            if (is_array($row) && !self::covers($kept, OpeningHours::date($row['start_date'] ?? ''))) {
                $rows[] = self::specialRow($row + ['source' => 'google']);
            }
        }

        return $rows;
    }

    private static function covers(array $rows, string $day): bool
    {
        if ($day === '') {
            return true;
        }

        foreach ($rows as $row) {
            $start = OpeningHours::date($row['start_date'] ?? '');
            $end = OpeningHours::date($row['end_date'] ?? '');

            if ($start !== '' && $day >= $start && $day <= ($end !== '' ? $end : $start)) {
                return true;
            }
        }

        return false;
    }

    private static function type(array $row): string
    {
        return trim((string) ($row['hours_type'] ?? '')) ?: OpeningHours::REGULAR;
    }

    private static function hourRow(array $row): array
    {
        $clean = [];

        foreach (self::HOUR_FIELDS as $field) {
            $clean[$field] = trim((string) ($row[$field] ?? ''));
        }

        $clean['hours_type'] = self::type($row);

        return $clean;
    }

    private static function specialRow(array $row): array
    {
        $clean = [];

        foreach (self::SPECIAL_FIELDS as $field) {
            $clean[$field] = trim((string) ($row[$field] ?? ''));
        }

        $clean['source'] = $clean['source'] === 'google' ? 'google' : 'manual';
        $clean['closed'] = in_array(strtolower(trim((string) ($row['closed'] ?? 'true'))), ['true', '1'], true) ? 'true' : 'false';

        return $clean;
    }

    private static function withPositions(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index]['position'] = (string) ($index + 1);
        }

        return $rows;
    }
}
