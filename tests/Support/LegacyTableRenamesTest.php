<?php
/**
 * LegacyTableRenames::plan(): rinomina solo le tabelle vecchie presenti, salta
 * quelle già migrate e si ferma, senza piano parziale, se esistono entrambi i nomi.
 *
 *   php tests/Support/LegacyTableRenamesTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Support\LegacyTableRenames;

echo "LegacyTableRenames\n";

$map = ['gst_contacts' => 'contacts', 'gst_contact_addresses' => 'contact_addresses'];

check('rinomina le tabelle vecchie presenti', function () use ($map) {
    return LegacyTableRenames::plan($map, ['gst_contacts', 'gst_contact_addresses', 'users']) === $map;
});

check('salta le tabelle già migrate o assenti', function () use ($map) {
    return LegacyTableRenames::plan($map, ['contacts', 'contact_addresses']) === []
        && LegacyTableRenames::plan($map, []) === []
        && LegacyTableRenames::plan($map, ['gst_contacts', 'contact_addresses']) === ['gst_contacts' => 'contacts'];
});

check('con entrambi i nomi si ferma senza piano', function () use ($map) {
    try {
        LegacyTableRenames::plan($map, ['gst_contact_addresses', 'gst_contacts', 'contacts'], 'tabelle contatti');
    } catch (RuntimeException $e) {
        return str_contains($e->getMessage(), 'gst_contacts') && str_contains($e->getMessage(), 'tabelle contatti');
    }
    return false;
});

summary();
