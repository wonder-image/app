<?php

namespace Wonder\App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;

/**
 * Sedi della società già completate dall'eredità, lette una volta per
 * richiesta. Prima della migrazione legge i vecchi dati aziendali come
 * un'unica sede.
 */
final class SocietyLocations
{
    /** @var array{default_id: int, locations: array<int, object>}|null */
    private static ?array $cache = null;

    public static function default(): object
    {
        $data = self::data();

        return $data['locations'][$data['default_id']] ?? self::emptyLocation();
    }

    public static function find(int|string $idOrSlug): ?object
    {
        $key = trim((string) $idOrSlug);
        $locations = self::data()['locations'];

        if ($key === '') {
            return null;
        }

        if (ctype_digit($key)) {
            return $locations[(int) $key] ?? null;
        }

        foreach ($locations as $location) {
            if ((string) ($location->slug ?? '') === $key) {
                return $location;
            }
        }

        return null;
    }

    /** @return list<object> */
    public static function all(bool $onlyVisible = true): array
    {
        return array_values(array_filter(
            self::data()['locations'],
            static fn (object $location): bool => !$onlyVisible || (string) ($location->visible ?? 'true') === 'true'
        ));
    }

    /** Fasce effettive di una data, nel fuso orario del sito. */
    public static function hoursFor(object $location, DateTimeInterface $date): array
    {
        return OpeningHours::periodsFor(
            (array) ($location->hours ?? []),
            (array) ($location->specialHours ?? []),
            self::inSiteTimezone($date)
        );
    }

    public static function isOpen(object $location, ?DateTimeInterface $at = null): bool
    {
        return OpeningHours::isOpenAt(
            (array) ($location->hours ?? []),
            (array) ($location->specialHours ?? []),
            self::inSiteTimezone($at ?? new DateTimeImmutable('now'))
        );
    }

    public static function reset(): void
    {
        self::$cache = null;
    }

    /**
     * @return array{default_id: int, locations: array<int, object>}
     */
    public static function assemble(array $locationRows, array $hourRows, array $specialRows): array
    {
        $rows = array_values(array_filter(
            $locationRows,
            static fn (mixed $row): bool => is_array($row) && ($row['deleted'] ?? 'false') !== 'true'
        ));

        usort($rows, static fn (array $a, array $b): int => [(int) ($a['position'] ?? 0), (int) ($a['id'] ?? 0)]
            <=> [(int) ($b['position'] ?? 0), (int) ($b['id'] ?? 0)]);

        $defaultRow = null;

        foreach ($rows as $row) {
            if (($row['is_default'] ?? '') === 'true') {
                $defaultRow = $row;
                break;
            }
        }

        $defaultRow ??= $rows[0] ?? null;
        $defaultId = (int) ($defaultRow['id'] ?? 0);
        $hoursByLocation = self::groupByLocation($hourRows);
        $specialByLocation = self::groupByLocation($specialRows);
        $locations = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $isDefault = $id === $defaultId;
            $resolved = SocietyLocationResolver::resolve($row, $isDefault ? null : $defaultRow);
            $hours = SocietyLocationResolver::hours(
                $hoursByLocation[$id] ?? [],
                $specialByLocation[$id] ?? [],
                $hoursByLocation[$defaultId] ?? [],
                $specialByLocation[$defaultId] ?? [],
                $isDefault
            );

            $resolved['is_default'] = $isDefault ? 'true' : 'false';
            $location = (object) $resolved;
            $location->hours = $hours['hours'];
            $location->specialHours = $hours['special_hours'];
            $location->hoursInherited = $hours['inherited'];
            $locations[$id] = $location;
        }

        return ['default_id' => $defaultId, 'locations' => $locations];
    }

    private static function data(): array
    {
        return self::$cache ??= self::load();
    }

    private static function load(): array
    {
        if (!function_exists('sqlTableExists')) {
            return self::assemble([], [], []);
        }

        if (sqlTableExists(SocietyLocation::$table)) {
            return self::assemble(
                self::rows(SocietyLocation::class),
                sqlTableExists(SocietyLocationHour::$table) ? self::rows(SocietyLocationHour::class) : [],
                sqlTableExists(SocietyLocationSpecialHour::$table) ? self::rows(SocietyLocationSpecialHour::class) : []
            );
        }

        if (!sqlTableExists('society')) {
            return self::assemble([], [], []);
        }

        $hours = array_map(
            static fn (array $row): array => $row + ['society_location_id' => 1],
            SocietyLocationsMigration::legacyHours()
        );

        return self::assemble([['id' => 1] + SocietyLocationsMigration::legacyLocation()], $hours, []);
    }

    /**
     * Righe non cancellate lette dal Model, che toglie l'escape di scrittura
     * (es. `McDonald\'s` → `McDonald's`).
     *
     * @param class-string<\Wonder\App\Model> $modelClass
     */
    private static function rows(string $modelClass): array
    {
        $rows = $modelClass::find(['deleted' => 'false']);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    private static function groupByLocation(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            if (is_array($row) && ($row['deleted'] ?? 'false') !== 'true') {
                $grouped[(int) ($row['society_location_id'] ?? 0)][] = $row;
            }
        }

        foreach ($grouped as $id => $list) {
            usort($list, static fn (array $a, array $b): int => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));
            $grouped[$id] = $list;
        }

        return $grouped;
    }

    private static function inSiteTimezone(DateTimeInterface $date): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    private static function emptyLocation(): object
    {
        return (object) [
            'id' => 0,
            'slug' => '',
            'label' => '',
            'is_default' => 'true',
            'visible' => 'true',
            'inherited_fields' => [],
            'hours' => [],
            'specialHours' => [],
            'hoursInherited' => false,
        ];
    }
}
