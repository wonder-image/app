<?php
/** php tests/App/SocietyLocationModelsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Models\Config\Society;
use Wonder\App\Models\Config\SocietyAddress;
use Wonder\App\Models\Config\SocietyLegalAddress;
use Wonder\App\Models\Config\SocietyLocation;
use Wonder\App\Models\Config\SocietyLocationHour;
use Wonder\App\Models\Config\SocietyLocationSpecialHour;
use Wonder\App\Models\Config\SocietySocial;
use Wonder\App\Models\Config\SocietyTimetable;

check('sedi sincronizzate con id stabili, modificabili ovunque', function () {
    $schema = SocietyLocation::syncSchema();

    return SocietyLocation::$table === 'society_locations'
        && $schema !== null && $schema->keepIds && !$schema->localOnly && !$schema->singleton;
});

check('colonne della sede per gruppo', function () {
    $columns = array_keys(SocietyLocation::getColumns());
    $expected = [
        'slug', 'label', 'is_default', 'visible', 'position', 'business_status', 'opening_date',
        'country', 'province', 'city', 'cap', 'street', 'number', 'more', 'gmaps', 'google_place_id', 'google_synced_at',
        'email', 'pec', 'tel', 'cel',
        'name', 'legal_name', 'pi', 'cf', 'sdi', 'rea', 'share_capital',
        'legal_country', 'legal_province', 'legal_city', 'legal_cap', 'legal_street', 'legal_number', 'legal_more', 'legal_gmaps',
        'site', 'instagram', 'facebook', 'tiktok', 'linkedin', 'whatsapp', 'youtube',
    ];
    $missing = array_values(array_diff($expected, $columns));

    if ($missing !== []) {
        echo '    mancano: '.implode(', ', $missing)."\n";
    }

    return $missing === [];
});

check('slug unico solo tra le sedi non eliminate (nessun indice UNIQUE)', function () {
    $columns = SocietyLocation::getColumns();
    $fields = SocietyLocation::dataFields();

    // Una sede eliminata resta nella tabella: con l'indice, ricreare "MC Nembro" darebbe
    // "Duplicate entry" perché create_link() ignora le righe eliminate.
    return !array_key_exists('unique', $columns['slug'] ?? [])
        && ($fields['slug']->getSchema('link_unique') ?? false) === true;
});

check('orari e orari speciali non sincronizzati, legati alla sede', function () {
    $hours = SocietyLocationHour::getColumns();
    $special = SocietyLocationSpecialHour::getColumns();

    return SocietyLocationHour::syncSchema() === null
        && SocietyLocationSpecialHour::syncSchema() === null
        && ($hours['society_location_id']['foreign_table'] ?? null) === 'society_locations'
        && ($special['society_location_id']['foreign_table'] ?? null) === 'society_locations'
        && array_diff(['hours_type', 'open_day', 'open_time', 'close_day', 'close_time', 'position'], array_keys($hours)) === []
        && array_diff(['start_date', 'end_date', 'closed', 'open_time', 'close_time', 'note', 'source'], array_keys($special)) === [];
});

check('vecchie tabelle non più sincronizzate', fn () =>
    Society::syncSchema() === null
    && SocietyAddress::syncSchema() === null
    && SocietyLegalAddress::syncSchema() === null
    && SocietySocial::syncSchema() === null
    && SocietyTimetable::syncSchema() === null
);

summary();
