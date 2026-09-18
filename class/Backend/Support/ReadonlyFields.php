<?php

namespace Wonder\Backend\Support;

/**
 * Campi che restano modificabili quando una pagina è in sola lettura
 * ({@see \Wonder\App\Resource::editableWhenReadonly()}): es. orari e chiusure
 * della sede, che si cambiano anche in produzione mentre il resto della scheda
 * arriva dal deploy.
 */
final class ReadonlyFields
{
    /** @return list<string> */
    public static function normalize(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            if (!is_string($field)) {
                continue;
            }

            $name = trim($field);

            if ($name !== '' && !in_array($name, $normalized, true)) {
                $normalized[] = $name;
            }
        }

        return $normalized;
    }

    public static function allowsUpdate(bool $readonly, array $editable): bool
    {
        return !$readonly || self::normalize($editable) !== [];
    }

    public static function shouldDisable(string $field, bool $readonly, array $editable): bool
    {
        return $readonly && !in_array(trim($field), self::normalize($editable), true);
    }

    /** @return array<string, mixed> */
    public static function filter(array $values, array $editable): array
    {
        return array_intersect_key($values, array_flip(self::normalize($editable)));
    }
}
