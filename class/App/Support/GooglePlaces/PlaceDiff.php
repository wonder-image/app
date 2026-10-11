<?php

namespace Wonder\App\Support\GooglePlaces;

use DateTimeImmutable;
use DateTimeInterface;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Support\OpeningHours;

/**
 * Differenze tra i dati di Google (`PlaceMapper::map()`) e quelli di una
 * sede. Classe pura. Confronta:
 * - gli orari giorno per giorno, solo per i tipi che Google ha restituito;
 * - gli orari speciali nei 7 giorni da `$today`, solo nelle date speciali
 *   per Google o per il sito;
 * - lo stato dell'attività.
 */
final class PlaceDiff
{
    private const DAY_LABELS = ['Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mer', 'Thu' => 'Gio', 'Fri' => 'Ven', 'Sat' => 'Sab', 'Sun' => 'Dom'];

    /** Un lunedì qualsiasi: i giorni della settimana di riferimento per `periodsFor()`. */
    private const REFERENCE_MONDAY = '2024-01-01';

    public const WINDOW_DAYS = 7;

    /**
     * @param array{hours?: array, special_hours?: array, business_status?: ?string} $local orari effettivi della sede
     * @return list<array{kind: string, message: string}>
     */
    public static function compare(array $local, array $google, DateTimeInterface $today): array
    {
        $differences = [];
        $localHours = (array) ($local['hours'] ?? []);

        foreach ((array) ($google['hours_types'] ?? []) as $type) {
            foreach (self::DAY_LABELS as $day => $label) {
                $remote = self::describeDay((array) $google['hours'], $type, $day);
                $current = self::describeDay($localHours, $type, $day);

                if ($remote !== $current) {
                    $differences[] = [
                        'kind' => 'hours:'.$type,
                        'message' => OpeningHours::typeLabel($type).", {$label}: su Google {$remote}, sul sito {$current}.",
                    ];
                }
            }
        }

        if (($google['has_special_hours'] ?? false) === true) {
            foreach (self::specialDifferences($local, $google, $today) as $difference) {
                $differences[] = $difference;
            }
        }

        $status = $google['business_status'] ?? null;
        $localStatus = trim((string) ($local['business_status'] ?? ''));

        if ($status !== null && $localStatus !== $status) {
            $labels = SocietyLocation::businessStatusLabels();
            $differences[] = [
                'kind' => 'status',
                'message' => 'Stato dell\'attività: su Google «'.($labels[$status] ?? $status).'», sul sito «'.($labels[$localStatus] ?? ($localStatus !== '' ? $localStatus : 'non indicato')).'».',
            ];
        }

        return $differences;
    }

    /**
     * Tipi di orario restituiti da Google diversi da quelli della sede.
     *
     * @return list<string>
     */
    public static function changedHoursTypes(array $localHours, array $google): array
    {
        $changed = [];

        foreach ((array) ($google['hours_types'] ?? []) as $type) {
            foreach (array_keys(self::DAY_LABELS) as $day) {
                if (self::describeDay((array) $google['hours'], $type, $day) !== self::describeDay($localHours, $type, $day)) {
                    $changed[] = $type;
                    break;
                }
            }
        }

        return $changed;
    }

    /** Stesse righe di orari speciali, a meno di id, posizione e ordine. */
    public static function sameSpecialRows(array $a, array $b): bool
    {
        return self::specialKeys($a) === self::specialKeys($b);
    }

    /** Fasce di un giorno della settimana in forma leggibile (`09:00–13:00, 15:00–19:00`, `chiuso`). */
    public static function describeDay(array $hours, string $type, string $day): string
    {
        $offset = array_search($day, OpeningHours::DAYS, true);

        if ($offset === false) {
            return '';
        }

        $date = (new DateTimeImmutable(self::REFERENCE_MONDAY))->modify('+'.$offset.' days');

        return self::describePeriods(OpeningHours::periodsFor($hours, [], $date, $type));
    }

    /** @param list<array{open: string, close: string, overnight: bool}> $periods */
    public static function describePeriods(array $periods): string
    {
        if ($periods === []) {
            return 'chiuso';
        }

        if (count($periods) === 1 && $periods[0]['open'] === '00:00' && $periods[0]['close'] === '24:00') {
            return 'aperto 24 ore';
        }

        return implode(', ', array_map(
            static fn (array $period): string => $period['open'].'–'.$period['close'].($period['overnight'] ? ' (giorno dopo)' : ''),
            $periods
        ));
    }

    /** @return list<array{kind: string, message: string}> */
    private static function specialDifferences(array $local, array $google, DateTimeInterface $today): array
    {
        $localHours = (array) ($local['hours'] ?? []);
        $localSpecial = (array) ($local['special_hours'] ?? []);
        $googleSpecial = (array) ($google['special_hours'] ?? []);
        // Senza orari regolari da Google il confronto riguarda solo le date speciali.
        $googleHours = in_array(OpeningHours::REGULAR, (array) ($google['hours_types'] ?? []), true)
            ? (array) $google['hours']
            : $localHours;
        $start = DateTimeImmutable::createFromInterface($today)->setTime(0, 0);
        $differences = [];

        for ($offset = 0; $offset < self::WINDOW_DAYS; $offset++) {
            $date = $start->modify('+'.$offset.' days');
            $day = $date->format('Y-m-d');

            if (!self::coversDate($googleSpecial, $day) && !self::coversDate($localSpecial, $day)) {
                continue;
            }

            $remote = self::describePeriods(OpeningHours::periodsFor($googleHours, $googleSpecial, $date));
            $current = self::describePeriods(OpeningHours::periodsFor($localHours, $localSpecial, $date));

            if ($remote !== $current) {
                $differences[] = [
                    'kind' => 'special',
                    'message' => $date->format('d/m/Y').' ('.self::DAY_LABELS[$date->format('D')].'): su Google '.$remote.', sul sito '.$current.'.',
                ];
            }
        }

        return $differences;
    }

    private static function coversDate(array $specialHours, string $day): bool
    {
        foreach ($specialHours as $row) {
            if (!is_array($row)) {
                continue;
            }

            $start = OpeningHours::date($row['start_date'] ?? '');
            $end = OpeningHours::date($row['end_date'] ?? '');
            $closed = in_array(strtolower(trim((string) ($row['closed'] ?? 'true'))), ['true', '1'], true);

            if ($start !== '' && ($closed ? $day >= $start && $day <= ($end !== '' ? $end : $start) : $day === $start)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function specialKeys(array $rows): array
    {
        $keys = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $closed = in_array(strtolower(trim((string) ($row['closed'] ?? 'true'))), ['true', '1'], true);
            $keys[] = implode('|', [
                OpeningHours::date($row['start_date'] ?? ''),
                OpeningHours::date($row['end_date'] ?? ''),
                $closed ? 'true' : 'false',
                $closed ? '' : OpeningHours::time($row['open_time'] ?? ''),
                $closed ? '' : OpeningHours::time($row['close_time'] ?? ''),
            ]);
        }

        sort($keys);

        return $keys;
    }
}
