<?php
/** php tests/Sql/ConditionsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Backend\Table\Table;
use Wonder\Sql\Query;

$subquery = "deleted = 'false' AND product_model_id NOT IN (SELECT b.id FROM gst_product_models b WHERE b.type = 'bundle')";

check('Conditions(false): il WHERE di una sottoquery resta intatto', function () use ($subquery) {
    return Query::Conditions($subquery, false) === " $subquery";
});

check('Conditions(false): il WHERE iniziale sparisce', function () {
    return Query::Conditions("WHERE deleted = 'false'", false) === " deleted = 'false'";
});

check('Conditions(false): WHERE iniziale minuscolo o con spazi davanti', function () {
    return Query::Conditions("where deleted = 'false'", false) === " deleted = 'false'"
        && Query::Conditions("  \n WhErE deleted = 'false'", false) === " deleted = 'false'";
});

check('Conditions(false): solo il WHERE iniziale, non quelli della sottoquery', function () use ($subquery) {
    return Query::Conditions("WHERE $subquery", false) === " $subquery";
});

check('Conditions(false): parole che contengono "where" non vengono toccate', function () {
    return Query::Conditions("`somewhere` = 'x'", false) === " `somewhere` = 'x'"
        && Query::Conditions("WHEREABOUTS = 1", false) === " WHEREABOUTS = 1";
});

check('Conditions(true): aggiunge WHERE anche se la sottoquery ne contiene uno', function () use ($subquery) {
    return Query::Conditions($subquery) === " WHERE $subquery";
});

check('Conditions(true): un WHERE iniziale non viene raddoppiato', function () {
    return Query::Conditions("where deleted = 'false'") === " WHERE deleted = 'false'";
});

check('Conditions: stringa vuota resta vuota', function () {
    return Query::Conditions('   ', false) === '' && Query::Conditions('') === '';
});

check('Table::query(): la condizione con sottoquery arriva intatta alla lista', function () use ($subquery) {
    $table = (new ReflectionClass(Table::class))->newInstanceWithoutConstructor();
    $table->query($subquery);
    $custom = (new ReflectionProperty(Table::class, 'queryCustom'))->getValue($table);
    return $custom === " $subquery";
});

summary();
