<?php

namespace Wonder\App\Support\GooglePlaces;

/**
 * Lettura della copia di Google salvata dal task `wonder.google-places`.
 * Classe pura: lavora su righe già lette dal database.
 *
 * Il `Query` salva i caratteri oltre U+00FF come entità `&#N;`: qui tornano
 * caratteri, così l'output si escapa una volta sola con `e()`.
 */
final class GooglePlaceData
{
    /**
     * Copia di Google di una sede; `null` se manca o appartiene a un altro
     * `place_id` (la sede l'ha cambiato o tolto dopo l'ultima lettura).
     *
     * @return array{place_id: string, business_status: string, rating: ?float, rating_count: ?int, maps_uri: string, hours: list<array>, special_hours: list<array>, differences: list<array{kind: string, message: string}>, synced_at: string, error: string, error_at: string}|null
     */
    public static function snapshot(?array $row, string $placeId): ?array
    {
        $placeId = trim($placeId);

        if ($row === null || $placeId === '' || trim((string) ($row['place_id'] ?? '')) !== $placeId) {
            return null;
        }

        return [
            'place_id' => $placeId,
            'business_status' => trim((string) ($row['business_status'] ?? '')),
            'rating' => is_numeric($row['rating'] ?? null) ? (float) $row['rating'] : null,
            'rating_count' => is_numeric($row['rating_count'] ?? null) ? (int) $row['rating_count'] : null,
            'maps_uri' => self::text($row['maps_uri'] ?? ''),
            'hours' => self::jsonList($row['hours'] ?? null),
            'special_hours' => self::jsonList($row['special_hours'] ?? null),
            'differences' => array_values(array_filter(
                self::jsonList($row['differences'] ?? null),
                static fn (array $difference): bool => trim((string) ($difference['message'] ?? '')) !== ''
            )),
            'synced_at' => trim((string) ($row['synced_at'] ?? '')),
            'error' => self::text($row['error'] ?? ''),
            'error_at' => trim((string) ($row['error_at'] ?? '')),
        ];
    }

    /**
     * Recensioni di una sede, dalla più recente.
     *
     * @return list<array{name: string, rating: ?int, text: string, language: string, original_text: string, original_language: string, author_name: string, author_uri: string, author_photo_uri: string, publish_time: string, relative_time: string, maps_uri: string}>
     */
    public static function reviews(array $rows): array
    {
        $reviews = [];

        foreach ($rows as $row) {
            if (!is_array($row) || ($row['deleted'] ?? 'false') === 'true') {
                continue;
            }

            $review = ['rating' => is_numeric($row['rating'] ?? null) ? (int) $row['rating'] : null];

            foreach (['name', 'text', 'language', 'original_text', 'original_language', 'author_name', 'author_uri', 'author_photo_uri', 'publish_time', 'relative_time', 'maps_uri'] as $field) {
                $review[$field] = self::text($row[$field] ?? '');
            }

            $reviews[] = $review;
        }

        usort($reviews, static fn (array $a, array $b): int => strcmp($b['publish_time'], $a['publish_time']));

        return $reviews;
    }

    /** Toglie le entità numeriche oltre U+00FF scritte dal `Query`. */
    public static function text(mixed $value): string
    {
        $text = trim((string) $value);

        if (!str_contains($text, '&#')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/&#(\d{3,7});/',
            static function (array $matches): string {
                $codePoint = (int) $matches[1];

                return $codePoint > 0xFF && $codePoint <= 0x10FFFF && ($codePoint < 0xD800 || $codePoint > 0xDFFF)
                    ? mb_chr($codePoint, 'UTF-8')
                    : $matches[0];
            },
            $text
        );
    }

    /** @return list<array> */
    private static function jsonList(mixed $value): array
    {
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];

        foreach ($decoded as $row) {
            if (is_array($row)) {
                $rows[] = array_map(static fn (mixed $item): mixed => is_string($item) ? self::text($item) : $item, $row);
            }
        }

        return $rows;
    }
}
