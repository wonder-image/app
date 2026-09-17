<?php

namespace Wonder\App\Support;

/**
 * Converte i vecchi dati aziendali a riga unica (`society`, `society_address`,
 * `society_legal_address`, `society_social`, `society_timetable`) nella sede
 * predefinita e nei suoi orari regolari. Classe pura.
 */
final class LegacySocietyMapper
{
    private const SKIPPED_COLUMNS = ['id', 'deleted', 'creation', 'last_modified', 'timetable'];

    /**
     * @param list<string> $columns colonne di `society_locations`
     * @return array<string, mixed>
     */
    public static function location(array $society, array $address, array $legalAddress, array $social, array $columns): array
    {
        $location = [
            'slug' => 'sede-principale',
            'label' => 'Sede principale',
            'is_default' => 'true',
            'visible' => 'true',
            'position' => 1,
            'business_status' => 'operational',
        ];

        foreach ([$society, $address, $legalAddress, $social] as $row) {
            foreach ($row as $column => $value) {
                if (!is_string($column) || in_array($column, self::SKIPPED_COLUMNS, true) || !in_array($column, $columns, true)) {
                    continue;
                }

                $location[$column] = $value;
            }
        }

        return $location;
    }

    /**
     * Orari da `society_timetable`; se non ci sono righe valide, dal JSON
     * `society_address.timetable`. `00:00` di chiusura diventa `24:00`; una
     * chiusura prima dell'apertura passa al giorno dopo.
     *
     * @return list<array<string, mixed>>
     */
    public static function hours(array $timetableRows, ?string $timetableJson): array
    {
        $ranges = [];

        foreach ($timetableRows as $row) {
            if (is_array($row) && ($row['deleted'] ?? 'false') !== 'true') {
                $ranges[] = [(string) ($row['day'] ?? ''), (string) ($row['from_time'] ?? ''), (string) ($row['to_time'] ?? '')];
            }
        }

        if ($ranges === []) {
            $decoded = json_decode((string) $timetableJson, true);

            foreach (is_array($decoded) ? $decoded : [] as $day => $items) {
                foreach (is_array($items) ? $items : [] as $item) {
                    if (is_array($item)) {
                        $ranges[] = [(string) $day, (string) ($item['from'] ?? ''), (string) ($item['to'] ?? '')];
                    }
                }
            }
        }

        $hours = [];

        foreach ($ranges as [$day, $from, $to]) {
            $open = OpeningHours::time($from);
            $close = OpeningHours::time($to);

            if (!in_array($day, OpeningHours::DAYS, true) || $open === '' || $close === '') {
                continue;
            }

            $closeDay = $day;

            if ($close === '00:00') {
                $close = '24:00';
            } elseif ($close <= $open) {
                $closeDay = OpeningHours::nextDay($day);
            }

            $hours[] = [
                'hours_type' => OpeningHours::REGULAR,
                'open_day' => $day,
                'open_time' => $open,
                'close_day' => $closeDay,
                'close_time' => $close,
                'position' => count($hours),
            ];
        }

        return $hours;
    }
}
