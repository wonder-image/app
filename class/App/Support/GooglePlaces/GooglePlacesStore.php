<?php

namespace Wonder\App\Support\GooglePlaces;

/**
 * Dove il task `wonder.google-places` legge le sedi e scrive orari, copia
 * di Google e recensioni. Sostituibile nei test o da un sito.
 */
interface GooglePlacesStore
{
    /**
     * Sedi non cancellate, con gli orari effettivi (anche ereditati).
     *
     * @return list<array{id: int, label: string, place_id: string, business_status: ?string, hours: array, special_hours: array, inherited: bool}>
     */
    public function locations(): array;

    /**
     * Id delle sedi che hanno ancora una copia di Google o recensioni.
     *
     * @return list<int>
     */
    public function storedLocationIds(): array;

    /** Sostituisce gli orari della sede (`null` = non toccare). */
    public function replaceHours(int $locationId, ?array $hours, ?array $specialHours): void;

    /** @param array<string, mixed> $snapshot */
    public function saveSnapshot(int $locationId, array $snapshot): void;

    /** Sostituisce le recensioni della sede con quelle lette ora. */
    public function saveReviews(int $locationId, array $reviews): void;

    public function saveError(int $locationId, string $error): void;

    /** Elimina copia di Google e recensioni della sede. */
    public function clear(int $locationId): void;
}
