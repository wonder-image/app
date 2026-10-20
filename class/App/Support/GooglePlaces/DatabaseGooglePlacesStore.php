<?php

namespace Wonder\App\Support\GooglePlaces;

use Wonder\App\Model;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationGoogle;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Models\Config\SocietyLocationReview;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;
use Wonder\App\Support\SocietyLocations;
use Wonder\Sql\MysqliTransactionConnection;
use Wonder\Sql\Transaction;

/**
 * Store su database del task `wonder.google-places`.
 *
 * Gli orari sostituiti si cancellano in modo logico (`deleted = true`), come
 * fa il form; copia di Google e recensioni si cancellano davvero, perché
 * sono contenuti di Google da non conservare oltre il necessario.
 */
class DatabaseGooglePlacesStore implements GooglePlacesStore
{
    public function locations(): array
    {
        $rows = $this->rows(SocietyLocation::class);
        $assembled = SocietyLocations::assemble(
            $rows,
            $this->rows(SocietyLocationHour::class),
            $this->rows(SocietyLocationSpecialHour::class)
        );
        $locations = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $location = $assembled['locations'][$id] ?? null;

            if ($location === null) {
                continue;
            }

            // Il place_id della sede, mai quello ereditato dalla sede principale.
            $locations[] = [
                'id' => $id,
                'label' => trim((string) ($row['label'] ?? '')) ?: 'Sede '.$id,
                'place_id' => trim((string) ($row['google_place_id'] ?? '')),
                'sync_hours' => ($row['google_sync_hours'] ?? 'false') === 'true',
                'business_status' => trim((string) ($row['business_status'] ?? '')) ?: null,
                'hours' => (array) $location->hours,
                'special_hours' => (array) $location->specialHours,
                'inherited' => (bool) $location->hoursInherited,
            ];
        }

        return $locations;
    }

    public function storedLocationIds(): array
    {
        $ids = [];

        foreach ([SocietyLocationGoogle::class, SocietyLocationReview::class] as $model) {
            $rows = $model::query()->Select($model::$table, null, null, null, null, 'society_location_id')->row;

            foreach (is_array($rows) ? $rows : [] as $row) {
                $ids[(int) ($row['society_location_id'] ?? 0)] = true;
            }
        }

        unset($ids[0]);

        return array_keys($ids);
    }

    public function replaceHours(int $locationId, ?array $hours, ?array $specialHours): void
    {
        if ($hours === null && $specialHours === null) {
            return;
        }

        Transaction::runOn(new MysqliTransactionConnection(Model::connection()), function () use ($locationId, $hours, $specialHours): void {
            if ($hours !== null) {
                $this->softDelete(SocietyLocationHour::class, $locationId);

                foreach (array_values($hours) as $index => $row) {
                    SocietyLocationHour::query()->Insert(SocietyLocationHour::$table, [
                        'society_location_id' => $locationId,
                        'hours_type' => (string) ($row['hours_type'] ?? ''),
                        'open_day' => (string) ($row['open_day'] ?? ''),
                        'open_time' => (string) ($row['open_time'] ?? ''),
                        'close_day' => (string) ($row['close_day'] ?? ''),
                        'close_time' => (string) ($row['close_time'] ?? ''),
                        'position' => (int) ($row['position'] ?? $index + 1),
                    ]);
                }
            }

            if ($specialHours !== null) {
                $this->softDelete(SocietyLocationSpecialHour::class, $locationId);

                foreach ($specialHours as $row) {
                    $note = (string) ($row['note'] ?? '');

                    SocietyLocationSpecialHour::query()->Insert(SocietyLocationSpecialHour::$table, [
                        'society_location_id' => $locationId,
                        'start_date' => (string) ($row['start_date'] ?? ''),
                        'end_date' => (string) ($row['end_date'] ?? ''),
                        'closed' => (string) ($row['closed'] ?? 'true'),
                        'open_time' => (string) ($row['open_time'] ?? ''),
                        'close_time' => (string) ($row['close_time'] ?? ''),
                        // Come il form: la nota si rilegge con sanitizeEcho().
                        'note' => function_exists('sanitize') ? sanitize($note) : $note,
                        'source' => (string) ($row['source'] ?? 'manual'),
                    ]);
                }
            }
        });
    }

    public function saveSnapshot(int $locationId, array $snapshot): void
    {
        $values = [
            'place_id' => (string) ($snapshot['place_id'] ?? ''),
            'business_status' => (string) ($snapshot['business_status'] ?? ''),
            'rating' => $snapshot['rating'] ?? null,
            'rating_count' => $snapshot['rating_count'] ?? null,
            'maps_uri' => (string) ($snapshot['maps_uri'] ?? ''),
            'hours' => $this->json($snapshot['hours'] ?? []),
            'special_hours' => $this->json($snapshot['special_hours'] ?? []),
            'differences' => $this->json($snapshot['differences'] ?? []),
            'synced_at' => (string) ($snapshot['synced_at'] ?? date('Y-m-d H:i:s')),
            'error' => null,
            'error_at' => null,
        ];

        $this->upsertSnapshot($locationId, $values);
    }

    public function saveReviews(int $locationId, array $reviews): void
    {
        $query = SocietyLocationReview::query();
        $existing = $query->Select(SocietyLocationReview::$table, ['society_location_id' => $locationId], null, null, null, ['id', 'name'])->row;
        $idsByName = [];

        foreach (is_array($existing) ? $existing : [] as $row) {
            $idsByName[(string) ($row['name'] ?? '')][] = (int) ($row['id'] ?? 0);
        }

        Transaction::runOn(new MysqliTransactionConnection(Model::connection()), function () use ($query, $locationId, $reviews, &$idsByName): void {
            foreach ($reviews as $review) {
                $name = (string) ($review['name'] ?? '');
                $values = [
                    'society_location_id' => $locationId,
                    'name' => $name,
                    'rating' => $review['rating'] ?? null,
                    'text' => (string) ($review['text'] ?? ''),
                    'language' => (string) ($review['language'] ?? ''),
                    'original_text' => (string) ($review['original_text'] ?? ''),
                    'original_language' => (string) ($review['original_language'] ?? ''),
                    'author_name' => (string) ($review['author_name'] ?? ''),
                    'author_uri' => (string) ($review['author_uri'] ?? ''),
                    'author_photo_uri' => (string) ($review['author_photo_uri'] ?? ''),
                    'publish_time' => (string) ($review['publish_time'] ?? ''),
                    'relative_time' => (string) ($review['relative_time'] ?? ''),
                    'maps_uri' => (string) ($review['maps_uri'] ?? ''),
                ];
                $id = isset($idsByName[$name]) ? array_shift($idsByName[$name]) : null;

                if ($id !== null) {
                    $query->Update(SocietyLocationReview::$table, $values, 'id', $id);
                } else {
                    $query->Insert(SocietyLocationReview::$table, $values);
                }
            }

            $stale = array_merge(...array_values($idsByName));

            if ($stale !== []) {
                $query->Delete(SocietyLocationReview::$table, ['id' => $stale]);
            }
        });
    }

    public function saveError(int $locationId, string $error): void
    {
        $this->upsertSnapshot($locationId, [
            'error' => mb_substr($error, 0, 2000),
            'error_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function clear(int $locationId): void
    {
        SocietyLocationGoogle::query()->Delete(SocietyLocationGoogle::$table, ['society_location_id' => $locationId]);
        SocietyLocationReview::query()->Delete(SocietyLocationReview::$table, ['society_location_id' => $locationId]);
    }

    /** @param class-string<Model> $model */
    private function rows(string $model): array
    {
        $rows = $model::find(['deleted' => 'false']);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @param class-string<Model> $model */
    private function softDelete(string $model, int $locationId): void
    {
        $rows = $model::query()->Select($model::$table, ['society_location_id' => $locationId, 'deleted' => 'false'], null, null, null, 'id')->row;

        foreach (is_array($rows) ? $rows : [] as $row) {
            $model::query()->Update($model::$table, ['deleted' => 'true'], 'id', (int) $row['id']);
        }
    }

    private function upsertSnapshot(int $locationId, array $values): void
    {
        $query = SocietyLocationGoogle::query();
        $row = $query->Select(SocietyLocationGoogle::$table, ['society_location_id' => $locationId], 1, null, null, 'id')->row;
        $id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;

        if ($id > 0) {
            $query->Update(SocietyLocationGoogle::$table, $values, 'id', $id);

            return;
        }

        $query->Insert(SocietyLocationGoogle::$table, ['society_location_id' => $locationId] + $values);
    }

    private function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
