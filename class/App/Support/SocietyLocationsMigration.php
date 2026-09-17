<?php

namespace Wonder\App\Support;

use RuntimeException;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\Sql\Transaction;

/**
 * Crea la sede predefinita (`id = 1`) dai vecchi dati aziendali, una sola
 * volta: solo se `society_locations` non ha righe, nemmeno cancellate.
 */
final class SocietyLocationsMigration
{
    public static function runIfNeeded(): bool
    {
        if (!sqlTableExists(SocietyLocation::$table) || sqlSelect(SocietyLocation::$table, null, 1)->exists) {
            return false;
        }

        $location = self::legacyLocation();
        $hours = self::legacyHours();

        Transaction::run(static function () use ($location, $hours): void {
            if (empty(sqlInsert(SocietyLocation::$table, array_merge($location, ['id' => 1]))->success)) {
                throw new RuntimeException('Migrazione dati aziendali: sede predefinita non creata.');
            }

            foreach ($hours as $row) {
                if (empty(sqlInsert(SocietyLocationHour::$table, array_merge($row, ['society_location_id' => 1]))->success)) {
                    throw new RuntimeException('Migrazione dati aziendali: orari non copiati.');
                }
            }
        });

        return true;
    }

    /** Sede predefinita dalle vecchie tabelle (senza `id`). */
    public static function legacyLocation(): array
    {
        return LegacySocietyMapper::location(
            self::legacyRow('society'),
            self::legacyRow('society_address'),
            self::legacyRow('society_legal_address'),
            self::legacyRow('society_social'),
            array_keys(SocietyLocation::getColumns())
        );
    }

    /** Orari regolari dalle vecchie tabelle (senza `society_location_id`). */
    public static function legacyHours(): array
    {
        $timetable = sqlTableExists('society_timetable')
            ? (array) sqlSelect('society_timetable', ['society_address_id' => 1, 'deleted' => 'false'], null, 'position', 'ASC')->row
            : [];

        return LegacySocietyMapper::hours($timetable, (string) (self::legacyRow('society_address')['timetable'] ?? ''));
    }

    private static function legacyRow(string $table): array
    {
        if (!sqlTableExists($table)) {
            return [];
        }

        $row = sqlSelect($table, ['id' => 1], 1)->row;

        return is_array($row) ? $row : [];
    }
}
