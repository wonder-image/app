<?php

namespace Wonder\App\Support;

/**
 * Completa una sede con i dati della sede predefinita.
 *
 * - Nome dell'attività: sempre quello della predefinita, unico per la società.
 * - Contatti e link: campo per campo.
 * - Dati legali, indirizzo (con Place ID), sede legale: per gruppo
 *   intero, solo se il gruppo della sede è tutto vuoto, così non si mescolano
 *   dati di sedi diverse.
 * - Orari: quelli della predefinita, con i suoi orari speciali, solo se la sede
 *   non ha orari propri.
 */
final class SocietyLocationResolver
{
    /** Dati della società, uguali per tutte le sedi: si compilano nella predefinita. */
    public const SOCIETY_FIELDS = ['name'];

    public const CONTACT_FIELDS = ['email', 'pec', 'tel', 'cel'];

    public const LINK_FIELDS = ['site', 'instagram', 'facebook', 'tiktok', 'linkedin', 'whatsapp', 'youtube'];

    public const GROUPS = [
        'legal' => ['legal_name', 'pi', 'cf', 'sdi', 'rea', 'share_capital'],
        'address' => ['country', 'province', 'city', 'cap', 'street', 'number', 'more', 'gmaps', 'google_place_id', 'google_synced_at'],
        'legal_address' => ['legal_country', 'legal_province', 'legal_city', 'legal_cap', 'legal_street', 'legal_number', 'legal_more', 'legal_gmaps'],
    ];

    /** Campi che da soli non rendono compilato un gruppo: valori di default del form e date tecniche. */
    private const NOT_SIGNIFICANT = ['country', 'legal_country', 'google_synced_at'];

    public static function resolve(array $location, ?array $default): array
    {
        $location['inherited_fields'] = [];

        if ($default === null || self::isSameLocation($location, $default)) {
            return $location;
        }

        $inherited = [];

        foreach (self::SOCIETY_FIELDS as $field) {
            $location[$field] = $default[$field] ?? null;
            $inherited[] = $field;
        }

        foreach (array_merge(self::CONTACT_FIELDS, self::LINK_FIELDS) as $field) {
            if (self::isEmpty($location[$field] ?? null) && !self::isEmpty($default[$field] ?? null)) {
                $location[$field] = $default[$field];
                $inherited[] = $field;
            }
        }

        foreach (self::GROUPS as $fields) {
            if (!self::isGroupEmpty($location, $fields) || self::isGroupEmpty($default, $fields)) {
                continue;
            }

            foreach ($fields as $field) {
                $location[$field] = $default[$field] ?? null;
                $inherited[] = $field;
            }
        }

        $location['inherited_fields'] = $inherited;

        return $location;
    }

    /**
     * Valori presi dalla predefinita e non vuoti, da mostrare come suggerimento.
     *
     * @return array<string, string>
     */
    public static function inheritedValues(array $location, ?array $default): array
    {
        $resolved = self::resolve($location, $default);
        $values = [];

        foreach ($resolved['inherited_fields'] as $field) {
            if (!self::isEmpty($resolved[$field] ?? null)) {
                $values[$field] = (string) $resolved[$field];
            }
        }

        return $values;
    }

    /**
     * @return array{hours: array, special_hours: array, inherited: bool}
     */
    public static function hours(
        array $ownHours,
        array $ownSpecialHours,
        array $defaultHours,
        array $defaultSpecialHours,
        bool $isDefault
    ): array {
        if ($isDefault || $ownHours !== []) {
            return ['hours' => $ownHours, 'special_hours' => $ownSpecialHours, 'inherited' => false];
        }

        return [
            'hours' => $defaultHours,
            'special_hours' => $defaultSpecialHours,
            'inherited' => $defaultHours !== [] || $defaultSpecialHours !== [],
        ];
    }

    public static function isEmpty(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private static function isGroupEmpty(array $row, array $fields): bool
    {
        foreach ($fields as $field) {
            if (!in_array($field, self::NOT_SIGNIFICANT, true) && !self::isEmpty($row[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private static function isSameLocation(array $location, array $default): bool
    {
        $id = trim((string) ($location['id'] ?? ''));

        return $id !== '' && $id === trim((string) ($default['id'] ?? ''));
    }
}
