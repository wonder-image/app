<?php

namespace Wonder\App\Support;

use DateTimeImmutable;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;

/**
 * Normalizza e valida le righe dei repeater di "Orari e chiusure".
 * Classe pura: le righe senza orari (né date, per gli orari speciali) sono
 * considerate vuote e saltate.
 */
final class OpeningHoursInput
{
    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>}
     */
    public static function hours(array $rows): array
    {
        $types = array_merge([OpeningHours::REGULAR], OpeningHours::SECONDARY_TYPES);
        $valid = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $line = $index + 1;
            $openRaw = trim((string) ($row['open_time'] ?? ''));
            $closeRaw = trim((string) ($row['close_time'] ?? ''));

            if ($openRaw === '' && $closeRaw === '') {
                continue;
            }

            $type = trim((string) ($row['hours_type'] ?? '')) ?: OpeningHours::REGULAR;
            $openDay = trim((string) ($row['open_day'] ?? ''));
            $closeDay = trim((string) ($row['close_day'] ?? ''));
            $open = OpeningHours::time($openRaw);
            $close = OpeningHours::time($closeRaw);

            if (!in_array($type, $types, true) || !in_array($openDay, OpeningHours::DAYS, true)) {
                $errors[] = "Orari, riga {$line}: tipo o giorno non valido.";
                continue;
            }

            if ($open === '' || ($closeRaw !== '' && $close === '') || ($closeDay !== '' && !in_array($closeDay, OpeningHours::DAYS, true))) {
                $errors[] = "Orari, riga {$line}: orario non valido.";
                continue;
            }

            if ($close === '') {
                $closeDay = '';
            } else {
                $closeDay = $closeDay !== '' ? $closeDay : $openDay;

                if ($closeDay === $openDay && $close === '00:00') {
                    $close = '24:00';
                } elseif ($closeDay === $openDay && $close <= $open) {
                    $errors[] = "Orari, riga {$line}: la chiusura deve essere dopo l'apertura (per chiudere dopo la mezzanotte scegli il giorno dopo).";
                    continue;
                } elseif ($closeDay !== $openDay && $closeDay !== OpeningHours::nextDay($openDay)) {
                    $errors[] = "Orari, riga {$line}: la chiusura può essere al massimo il giorno dopo.";
                    continue;
                }
            }

            $valid[] = self::withId($row, [
                'hours_type' => $type,
                'open_day' => $openDay,
                'open_time' => $open,
                'close_day' => $closeDay,
                'close_time' => $close,
            ]);
        }

        return ['rows' => $valid, 'errors' => $errors];
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>}
     */
    public static function specialHours(array $rows): array
    {
        $valid = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $line = $index + 1;
            $startRaw = trim((string) ($row['start_date'] ?? ''));
            $endRaw = trim((string) ($row['end_date'] ?? ''));
            $openRaw = trim((string) ($row['open_time'] ?? ''));
            $closeRaw = trim((string) ($row['close_time'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));

            if ($startRaw === '' && $endRaw === '' && $openRaw === '' && $closeRaw === '' && $note === '') {
                continue;
            }

            $start = self::date($startRaw);
            $end = $endRaw === '' ? '' : self::date($endRaw);
            $closed = (string) ($row['closed'] ?? 'true') !== 'false';

            if ($start === '' || ($endRaw !== '' && $end === '')) {
                $errors[] = "Orari speciali, riga {$line}: data non valida.";
                continue;
            }

            if ($end !== '' && $end < $start) {
                $errors[] = "Orari speciali, riga {$line}: la data di fine è prima di quella di inizio.";
                continue;
            }

            $open = '';
            $close = '';

            if (!$closed) {
                $open = OpeningHours::time($openRaw);
                $close = OpeningHours::time($closeRaw);
                $nextDay = (new DateTimeImmutable($start))->modify('+1 day')->format('Y-m-d');

                if ($open === '' || $close === '') {
                    $errors[] = "Orari speciali, riga {$line}: per un'apertura servono gli orari.";
                    continue;
                }

                if ($end !== '' && $end !== $start && $end !== $nextDay) {
                    $errors[] = "Orari speciali, riga {$line}: un'apertura straordinaria dura al massimo fino al giorno dopo.";
                    continue;
                }

                if ($close === '00:00') {
                    $close = '24:00';
                }

                $end = $close !== '24:00' && $close <= $open ? $nextDay : '';
            }

            $source = (string) ($row['source'] ?? '');

            $valid[] = self::withId($row, [
                'start_date' => $start,
                'end_date' => $end,
                'closed' => $closed ? 'true' : 'false',
                'open_time' => $open,
                'close_time' => $close,
                'note' => $note,
                'source' => in_array($source, SocietyLocationSpecialHour::SOURCES, true) ? $source : 'manual',
            ]);
        }

        return ['rows' => $valid, 'errors' => $errors];
    }

    /**
     * Righe pronte per il form: il campo orario del browser non accetta `24:00`,
     * quindi la mezzanotte a fine giornata si mostra come `00:00`
     * (al salvataggio `hours()` e `specialHours()` la riportano a `24:00`).
     */
    public static function forForm(array $rows): array
    {
        foreach ($rows as $key => $row) {
            if (is_array($row) && OpeningHours::time($row['close_time'] ?? '') === '24:00') {
                $rows[$key]['close_time'] = '00:00';
            }
        }

        return $rows;
    }

    /** `Y-m-d` da `d/m/Y`, `Y-m-d` o `Y-m-d H:i:s`; stringa vuota se non valida. */
    public static function date(string $value): string
    {
        $value = trim($value);

        foreach (['d/m/Y', 'Y-m-d', 'Y-m-d H:i:s'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);

            if ($date instanceof DateTimeImmutable && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return '';
    }

    private static function withId(array $row, array $values): array
    {
        $id = trim((string) ($row['id'] ?? ''));

        return $id !== '' ? ['id' => $row['id']] + $values : $values;
    }
}
