<?php

namespace Wonder\App\Support;

/**
 * Regole della sede predefinita: sempre una sola, la prima sede lo diventa,
 * non si può eliminare.
 */
final class SocietyLocationDefaults
{
    /** Valore di `is_default` da salvare: senza un'altra predefinita la sede lo diventa. */
    public static function flagOnSave(mixed $requested, bool $otherDefaultExists): string
    {
        return !$otherDefaultExists || (string) $requested === 'true' ? 'true' : 'false';
    }

    public static function canDelete(array $row): bool
    {
        return ($row['is_default'] ?? '') !== 'true';
    }
}
