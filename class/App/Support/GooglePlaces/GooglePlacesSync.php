<?php

namespace Wonder\App\Support\GooglePlaces;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use Throwable;

/**
 * Allinea le sedi con Google Places: per ogni sede con un `place_id` legge
 * Place Details, salva copia e recensioni e, se la sede ha la sincronizzazione
 * degli orari attiva, applica gli orari (`PlaceSyncPlan`). Senza, gli orari
 * restano quelli manuali e non si salvano differenze. Un errore su una sede
 * non ferma le altre.
 */
final class GooglePlacesSync
{
    /** @var Closure(): PlaceDetailsClient */
    private Closure $client;

    private ?PlaceDetailsClient $resolvedClient = null;

    /**
     * @param PlaceDetailsClient|Closure(): PlaceDetailsClient $client creato solo se c'è una sede da leggere
     */
    public function __construct(PlaceDetailsClient|Closure $client, private readonly GooglePlacesStore $store)
    {
        $this->client = $client instanceof PlaceDetailsClient ? static fn (): PlaceDetailsClient => $client : $client;
    }

    /**
     * @param (Closure(string): void)|null $log
     * @param (Closure(): void)|null $beforeEach controllo prima di ogni sede (es. tempo massimo)
     * @return array{locations: int, synced: int, failed: int, applied: int, differences: int, cleared: int}
     */
    public function run(
        string $language,
        ?Closure $log = null,
        ?Closure $beforeEach = null,
        ?DateTimeInterface $today = null
    ): array {
        $log ??= static function (string $message): void {};
        $today = DateTimeImmutable::createFromInterface($today ?? new DateTimeImmutable('today'));
        $summary = ['locations' => 0, 'synced' => 0, 'failed' => 0, 'applied' => 0, 'differences' => 0, 'cleared' => 0];
        $withPlace = [];

        foreach ($this->store->locations() as $location) {
            if ($location['place_id'] !== '') {
                $withPlace[$location['id']] = $location;
            }
        }

        // Senza place_id copia e recensioni non appartengono più alla sede.
        foreach ($this->store->storedLocationIds() as $id) {
            if (!isset($withPlace[$id])) {
                $this->store->clear($id);
                $summary['cleared']++;
            }
        }

        $summary['locations'] = count($withPlace);

        if ($withPlace === []) {
            $log("Nessuna sede con un place_id Google.\n");

            return $summary;
        }

        // Una chiave mancante è un errore di configurazione, non di una sede.
        $this->resolvedClient ??= ($this->client)();

        foreach ($withPlace as $id => $location) {
            if ($beforeEach !== null) {
                $beforeEach();
            }

            try {
                $result = $this->syncLocation($location, $language, $today);
            } catch (Throwable $exception) {
                $summary['failed']++;
                $log("{$location['label']}: errore, {$exception->getMessage()}\n");

                try {
                    $this->store->saveError($id, $exception->getMessage());
                } catch (Throwable) {
                    // L'errore resta nel log dell'esecuzione.
                }

                continue;
            }

            $summary['synced']++;
            $summary['differences'] += count($result['differences']);
            $summary['applied'] += $result['applied'] ? 1 : 0;

            foreach ($result['skipped'] as $message) {
                $log("{$location['label']}: {$message}\n");
            }

            $log(sprintf(
                "%s: %s, %d recensioni, %d differenze%s.\n",
                $location['label'],
                $result['applied'] ? 'orari aggiornati' : 'letta',
                $result['reviews'],
                count($result['differences']),
                $result['differences'] !== [] ? ' ('.implode(' ', array_column($result['differences'], 'message')).')' : ''
            ));
        }

        if ($summary['synced'] === 0) {
            throw new RuntimeException('Nessuna sede allineata con Google: '.$summary['failed'].' errori.');
        }

        return $summary;
    }

    /** @return array{differences: list<array{kind: string, message: string}>, applied: bool, reviews: int, skipped: list<string>} */
    private function syncLocation(array $location, string $language, DateTimeImmutable $today): array
    {
        $syncHours = (bool) ($location['sync_hours'] ?? false);
        $google = PlaceMapper::map($this->resolvedClient->details($location['place_id'], $language));
        $plan = PlaceSyncPlan::make($location, $google, $today, $syncHours);

        if (!$syncHours) {
            $plan['differences'] = [];
        }

        $applied = $plan['hours'] !== null || $plan['special_hours'] !== null;

        if ($applied) {
            $this->store->replaceHours($location['id'], $plan['hours'], $plan['special_hours']);
        }

        $this->store->saveSnapshot($location['id'], [
            'place_id' => $location['place_id'],
            'business_status' => $google['business_status'],
            'rating' => $google['rating'],
            'rating_count' => $google['rating_count'],
            'maps_uri' => $google['maps_uri'],
            'hours' => $google['hours'],
            'special_hours' => $google['special_hours'],
            'differences' => $plan['differences'],
            'synced_at' => date('Y-m-d H:i:s'),
        ]);
        $this->store->saveReviews($location['id'], $google['reviews']);

        return [
            'differences' => $plan['differences'],
            'applied' => $applied,
            'reviews' => count($google['reviews']),
            'skipped' => $google['skipped'],
        ];
    }
}
