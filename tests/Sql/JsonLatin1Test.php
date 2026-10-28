<?php
/** php tests/Sql/JsonLatin1Test.php — colonne json lette dalla connessione latin1. */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Sql\Query;

// MySQL tiene il json in utf8mb4 e con la connessione latin1 lo ridà in cp1252: «ù» è F9, «€» è 0x80.
check('json in cp1252 riportato in UTF-8', function () {
    $row = Query::jsonColumnsToUtf8(['meta' => "{\"cta_label\": \"Scopri di pi\xf9 \x80\"}"], ['meta']);

    return json_decode($row['meta'], true) === ['cta_label' => 'Scopri di più €'];
});

check('json già UTF-8, NULL e numeri restano com\'erano', function () {
    $row = ['meta' => '{"a":"più"}', 'vuoto' => null, 'n' => 3];

    return Query::jsonColumnsToUtf8($row, ['meta', 'vuoto', 'n']) === $row;
});

check('le colonne non json non si toccano', function () {
    $row = ['meta' => '{}', 'name' => "pi\xf9"];

    return Query::jsonColumnsToUtf8($row, ['meta']) === $row;
});

summary();
