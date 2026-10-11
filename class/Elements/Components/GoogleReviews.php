<?php

namespace Wonder\Elements\Components;

use Wonder\Elements\Component;
use Wonder\Elements\Concerns\Renderer;

/**
 * Recensioni Google di una sede, con le attribuzioni richieste dalle norme
 * di Places API: avatar, nome e profilo dell'autore, link alla recensione su
 * Google Maps, avviso sull'ordine e la scritta «Google Maps».
 *
 * Solo dati: le recensioni arrivano già lette (`$location->reviews`, cioè
 * `GooglePlaceData::reviews()`), l'Element non fa query.
 */
class GoogleReviews extends Component
{
    use Renderer;

    public function __construct(array $reviews = [])
    {
        $this->schema('reviews', [])
            ->schema('rating', null)
            ->schema('rating-count', null)
            ->schema('maps-uri', '')
            ->schema('limit', 0)
            ->schema('notice', '')
            ->reviews($reviews);
    }

    public static function make(array $reviews = []): self
    {
        return new self($reviews);
    }

    /** Recensioni e voto medio di una sede di `SocietyLocations` (oggetto o array). */
    public static function fromLocation(object|array $location): self
    {
        $location = (array) $location;
        $google = is_array($location['google'] ?? null) ? $location['google'] : [];

        return self::make(is_array($location['reviews'] ?? null) ? $location['reviews'] : [])
            ->summary($google['rating'] ?? null, $google['rating_count'] ?? null, (string) ($google['maps_uri'] ?? ''));
    }

    /** @param list<array> $reviews righe di `GooglePlaceData::reviews()` */
    public function reviews(array $reviews): self
    {
        $clean = [];

        foreach ($reviews as $review) {
            if (!is_array($review)) {
                continue;
            }

            $row = ['rating' => self::rating($review['rating'] ?? null)];

            foreach (['text', 'language', 'original_text', 'original_language', 'author_name', 'publish_time', 'relative_time'] as $field) {
                $row[$field] = trim((string) ($review[$field] ?? ''));
            }

            foreach (['author_uri', 'author_photo_uri', 'maps_uri'] as $field) {
                $row[$field] = self::https($review[$field] ?? '');
            }

            // Senza un autore la recensione non si può attribuire.
            if ($row['author_name'] !== '' || $row['author_photo_uri'] !== '') {
                $clean[] = $row;
            }
        }

        return $this->schema('reviews', $clean);
    }

    public function summary(mixed $rating, mixed $count = null, string $mapsUri = ''): self
    {
        $rating = is_numeric($rating) && (float) $rating > 0 ? min(5.0, round((float) $rating, 1)) : null;
        $count = is_numeric($count) && (int) $count > 0 ? (int) $count : null;

        return $this->schema('rating', $rating)
            ->schema('rating-count', $count)
            ->schema('maps-uri', self::https($mapsUri));
    }

    /** Quante recensioni mostrare; 0 = tutte. */
    public function limit(int $limit): self
    {
        return $this->schema('limit', max(0, $limit));
    }

    /** Avviso sull'ordine delle recensioni, obbligatorio: vuoto usa quello di default. */
    public function notice(string $notice): self
    {
        return $this->schema('notice', trim($notice));
    }

    private static function rating(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 1 && (int) $value <= 5 ? (int) $value : null;
    }

    private static function https(mixed $value): string
    {
        $value = trim((string) $value);

        return str_starts_with(strtolower($value), 'https://') ? $value : '';
    }
}
