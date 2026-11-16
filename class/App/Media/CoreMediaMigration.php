<?php

namespace Wonder\App\Media;

use mysqli;
use Wonder\App\Models\Communications\Popup;
use Wonder\App\Models\Media\Logo;

/**
 * I file dei Model del core (loghi, popup) passano dalle loro cartelle
 * sotto `assets/upload` ai media della libreria. I file vengono copiati,
 * non spostati: gli indirizzi vecchi continuano a rispondere.
 *
 * Idempotente: lavora solo i campi con l'id ancora vuoto. Le colonne con
 * i nomi dei file restano, deprecate. Gira in `forge update` dopo
 * `runTables()`. I moduli migrano i loro Model con
 * {@see LegacyMediaMigration} dalle loro migrazioni.
 */
final class CoreMediaMigration
{
    /** I Model del core con {@see HasMedia}. */
    public const MODELS = [Logo::class, Popup::class];

    /** @return array<string, mixed> vuoto se non c'era niente da fare */
    public static function run(mysqli $connection, ?string $uploadRoot = null): array
    {
        return LegacyMediaMigration::run($connection, self::definitions(), $uploadRoot);
    }

    /** @return list<array<string, mixed>> */
    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::MODELS as $model) {
            array_push($definitions, ...$model::legacyMediaDefinitions());
        }

        return $definitions;
    }
}
