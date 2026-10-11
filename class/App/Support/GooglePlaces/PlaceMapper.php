<?php

namespace Wonder\App\Support\GooglePlaces;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Support\OpeningHours;

/**
 * Converte una risposta Place Details (New) nelle righe locali delle sedi.
 * Classe pura: nessuna query, nessuna chiamata di rete.
 *
 * - Orari: righe come `society_location_hours`. Google conta i giorni da
 *   domenica (0); una chiusura alle 00:00 del giorno dopo diventa `24:00`;
 *   un periodo di più giorni si divide in una riga per giorno; un periodo
 *   senza chiusura è "sempre aperto".
 * - Orari speciali: i `specialDays` di `currentOpeningHours` (circa i
 *   prossimi 7 giorni), con le fasce di quella data; nessuna fascia = chiuso.
 * - Recensioni: al massimo 5, come le restituisce Google.
 */
final class PlaceMapper
{
    /**
     * @return array{
     *     business_status: ?string,
     *     hours: list<array<string, string>>,
     *     hours_types: list<string>,
     *     special_hours: list<array<string, string>>,
     *     has_special_hours: bool,
     *     rating: ?float,
     *     rating_count: ?int,
     *     maps_uri: string,
     *     reviews: list<array<string, mixed>>,
     *     skipped: list<string>
     * }
     */
    public static function map(array $place): array
    {
        $hours = [];
        $types = [];
        $skipped = [];

        if (is_array($place['regularOpeningHours']['periods'] ?? null)) {
            $types[] = OpeningHours::REGULAR;
            array_push($hours, ...self::hoursRows(OpeningHours::REGULAR, $place['regularOpeningHours']['periods']));
        }

        foreach ((array) ($place['regularSecondaryOpeningHours'] ?? []) as $secondary) {
            if (!is_array($secondary) || !is_array($secondary['periods'] ?? null)) {
                continue;
            }

            $type = strtolower(trim((string) ($secondary['secondaryHoursType'] ?? '')));

            if (!in_array($type, OpeningHours::SECONDARY_TYPES, true)) {
                $skipped[] = 'Tipo di orario Google non gestito: '.($type !== '' ? $type : 'vuoto').'.';
                continue;
            }

            if (!in_array($type, $types, true)) {
                $types[] = $type;
            }

            array_push($hours, ...self::hoursRows($type, $secondary['periods']));
        }

        $current = is_array($place['currentOpeningHours'] ?? null) ? $place['currentOpeningHours'] : null;
        $rating = $place['rating'] ?? null;
        $count = $place['userRatingCount'] ?? null;

        return [
            'business_status' => self::businessStatus($place['businessStatus'] ?? null),
            'hours' => self::withPositions($hours),
            'hours_types' => $types,
            'special_hours' => $current !== null ? self::specialRows($current) : [],
            'has_special_hours' => $current !== null,
            'rating' => is_numeric($rating) ? round((float) $rating, 1) : null,
            'rating_count' => is_numeric($count) ? (int) $count : null,
            'maps_uri' => self::uri($place['googleMapsUri'] ?? null),
            'reviews' => self::reviews((array) ($place['reviews'] ?? [])),
            'skipped' => $skipped,
        ];
    }

    /** @return list<array<string, string>> */
    public static function hoursRows(string $type, array $periods): array
    {
        $rows = [];

        foreach ($periods as $period) {
            $open = is_array($period['open'] ?? null) ? $period['open'] : null;
            $close = is_array($period['close'] ?? null) ? $period['close'] : null;
            $openDay = self::dayIndex($open['day'] ?? null);
            $openTime = self::pointTime($open);

            if ($openDay === null || $openTime === '') {
                continue;
            }

            if ($close === null) {
                return [self::hourRow($type, 0, '00:00', null, '')];
            }

            $closeDay = self::dayIndex($close['day'] ?? null);
            $closeTime = self::pointTime($close);

            if ($closeDay === null || $closeTime === '') {
                continue;
            }

            $span = ($closeDay - $openDay + 7) % 7;

            if ($span === 0 && $closeTime <= $openTime) {
                $span = 7;
            }

            if ($span === 0) {
                $rows[] = self::hourRow($type, $openDay, $openTime, $openDay, $closeTime);
            } elseif ($span === 1 && $closeTime === '00:00') {
                $rows[] = self::hourRow($type, $openDay, $openTime, $openDay, '24:00');
            } elseif ($span === 1) {
                $rows[] = self::hourRow($type, $openDay, $openTime, $closeDay, $closeTime);
            } else {
                $rows[] = self::hourRow($type, $openDay, $openTime, $openDay, '24:00');

                for ($offset = 1; $offset < $span; $offset++) {
                    $day = ($openDay + $offset) % 7;
                    $rows[] = self::hourRow($type, $day, '00:00', $day, '24:00');
                }

                if ($closeTime !== '00:00') {
                    $rows[] = self::hourRow($type, $closeDay, '00:00', $closeDay, $closeTime);
                }
            }
        }

        return $rows;
    }

    /** @return list<array<string, string>> */
    public static function specialRows(array $current): array
    {
        $periodsByDate = [];

        foreach ((array) ($current['periods'] ?? []) as $period) {
            $date = self::pointDate($period['open'] ?? null);

            if ($date !== '') {
                $periodsByDate[$date][] = $period;
            }
        }

        $rows = [];

        foreach ((array) ($current['specialDays'] ?? []) as $specialDay) {
            $date = self::dateString($specialDay['date'] ?? null);

            if ($date === '' || isset($rows[$date])) {
                continue;
            }

            $rows[$date] = [];

            foreach ($periodsByDate[$date] ?? [] as $period) {
                $row = self::specialPeriodRow($date, $period);

                if ($row !== null) {
                    $rows[$date][] = $row;
                }
            }

            if ($rows[$date] === []) {
                $rows[$date][] = self::specialRow($date, '', 'true', '', '');
            }
        }

        ksort($rows);

        return array_merge(...array_values($rows));
    }

    /** @return list<array<string, mixed>> */
    public static function reviews(array $reviews): array
    {
        $rows = [];

        foreach ($reviews as $review) {
            $name = is_array($review) ? trim((string) ($review['name'] ?? '')) : '';

            if ($name === '' || strlen($name) > 255) {
                continue;
            }

            $author = is_array($review['authorAttribution'] ?? null) ? $review['authorAttribution'] : [];
            $rating = $review['rating'] ?? null;

            $rows[] = [
                'name' => $name,
                'rating' => is_numeric($rating) ? max(1, min(5, (int) round((float) $rating))) : null,
                'text' => self::localizedText($review['text'] ?? null),
                'language' => self::localizedLanguage($review['text'] ?? null),
                'original_text' => self::localizedText($review['originalText'] ?? null),
                'original_language' => self::localizedLanguage($review['originalText'] ?? null),
                'author_name' => trim((string) ($author['displayName'] ?? '')),
                'author_uri' => self::uri($author['uri'] ?? null),
                'author_photo_uri' => self::uri($author['photoUri'] ?? null),
                'publish_time' => self::dateTime($review['publishTime'] ?? null),
                'relative_time' => trim((string) ($review['relativePublishTimeDescription'] ?? '')),
                'maps_uri' => self::uri($review['googleMapsUri'] ?? null),
            ];
        }

        return $rows;
    }

    public static function businessStatus(mixed $value): ?string
    {
        $status = strtolower(trim((string) $value));

        return in_array($status, SocietyLocation::BUSINESS_STATUSES, true) ? $status : null;
    }

    private static function specialPeriodRow(string $date, array $period): ?array
    {
        $open = self::pointTime($period['open'] ?? null);

        if ($open === '') {
            return null;
        }

        if (!is_array($period['close'] ?? null)) {
            return self::specialRow($date, '', 'false', '00:00', '24:00');
        }

        $close = self::pointTime($period['close']);
        $closeDate = self::pointDate($period['close']);
        $nextDay = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');

        if ($close === '') {
            return null;
        }

        if ($closeDate === '' || $closeDate === $date) {
            return $close > $open ? self::specialRow($date, '', 'false', $open, $close) : null;
        }

        if ($closeDate === $nextDay && $close !== '00:00') {
            return self::specialRow($date, $close <= $open ? $nextDay : '', 'false', $open, $close);
        }

        // Fino alla mezzanotte, o oltre il giorno dopo: un orario speciale finisce in giornata.
        return self::specialRow($date, '', 'false', $open, '24:00');
    }

    private static function specialRow(string $date, string $endDate, string $closed, string $open, string $close): array
    {
        return [
            'start_date' => $date,
            'end_date' => $endDate,
            'closed' => $closed,
            'open_time' => $open,
            'close_time' => $close,
            'note' => '',
            'source' => 'google',
        ];
    }

    private static function hourRow(string $type, int $openDay, string $openTime, ?int $closeDay, string $closeTime): array
    {
        return [
            'hours_type' => $type,
            'open_day' => OpeningHours::DAYS[$openDay],
            'open_time' => $openTime,
            'close_day' => $closeDay === null ? '' : OpeningHours::DAYS[$closeDay],
            'close_time' => $closeTime,
        ];
    }

    private static function withPositions(array $rows): array
    {
        foreach ($rows as $index => $row) {
            $rows[$index]['position'] = (string) ($index + 1);
        }

        return $rows;
    }

    /** Indice in `OpeningHours::DAYS` (lunedì = 0) dal giorno Google (domenica = 0). */
    private static function dayIndex(mixed $day): ?int
    {
        if (!is_int($day) && !(is_string($day) && ctype_digit($day))) {
            return null;
        }

        $day = (int) $day;

        return $day >= 0 && $day <= 6 ? ($day + 6) % 7 : null;
    }

    private static function pointTime(mixed $point): string
    {
        if (!is_array($point)) {
            return '';
        }

        $hour = $point['hour'] ?? 0;
        $minute = $point['minute'] ?? 0;

        if (!is_numeric($hour) || !is_numeric($minute) || (int) $hour < 0 || (int) $hour > 23 || (int) $minute < 0 || (int) $minute > 59) {
            return '';
        }

        return sprintf('%02d:%02d', (int) $hour, (int) $minute);
    }

    private static function pointDate(mixed $point): string
    {
        return is_array($point) ? self::dateString($point['date'] ?? null) : '';
    }

    private static function dateString(mixed $date): string
    {
        if (!is_array($date) || !is_numeric($date['year'] ?? null) || !is_numeric($date['month'] ?? null) || !is_numeric($date['day'] ?? null)) {
            return '';
        }

        $year = (int) $date['year'];
        $month = (int) $date['month'];
        $day = (int) $date['day'];

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : '';
    }

    private static function localizedText(mixed $value): string
    {
        return is_array($value) ? trim((string) ($value['text'] ?? '')) : '';
    }

    private static function localizedLanguage(mixed $value): string
    {
        $language = is_array($value) ? trim((string) ($value['languageCode'] ?? '')) : '';

        return preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $language) === 1 ? $language : '';
    }

    /** Solo URL https: finiscono in `href` e `src` delle pagine. */
    private static function uri(mixed $value): string
    {
        $uri = trim((string) $value);

        return str_starts_with($uri, 'https://') && filter_var($uri, FILTER_VALIDATE_URL) !== false ? $uri : '';
    }

    /** RFC 3339 di Google in `Y-m-d H:i:s` nel fuso orario del sito. */
    private static function dateTime(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        try {
            return (new DateTimeImmutable($value))
                ->setTimezone(new DateTimeZone(date_default_timezone_get()))
                ->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return '';
        }
    }
}
