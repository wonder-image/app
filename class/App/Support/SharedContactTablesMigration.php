<?php

namespace Wonder\App\Support;

use mysqli;

/**
 * Promuove al core la tabella dei riferimenti esterni del gestionale.
 *
 * Le tabelle dei contatti (`gst_contacts`, `gst_contact_addresses`) le migra il
 * modulo `wonder-image/customer-account`, che ne è il proprietario.
 */
final class SharedContactTablesMigration
{
    public const TABLES = [
        'gst_external_references' => 'external_references',
    ];

    public static function plan(array $existing): array
    {
        return LegacyTableRenames::plan(self::TABLES, $existing, 'riferimenti esterni');
    }

    public static function run(mysqli $connection): array
    {
        return LegacyTableRenames::run($connection, self::TABLES, 'riferimenti esterni');
    }
}
