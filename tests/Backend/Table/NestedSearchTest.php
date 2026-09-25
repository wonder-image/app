<?php
/** php tests/Backend/Table/NestedSearchTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Table\SSP;

// movimento -> versione (gst_product) -> articolo (gst_product_model)
$model = ['table' => 'gst_product_model', 'local_key' => 'product_model_id', 'foreign_key' => 'id', 'columns' => ['name']];
$version = ['table' => 'gst_product', 'local_key' => 'product_id', 'foreign_key' => 'id', 'columns' => ['sku', 'ean', 'name'], 'relations' => [$model]];

check("la ricerca scende dalla versione all'articolo", function () use ($version) {
    return SSP::buildSearchWhere('maglia', ['code', $version])
        === "((CONCAT_WS(' ', `code`) LIKE '%maglia%') OR (`product_id` IN (SELECT `id` FROM `gst_product` WHERE (CONCAT_WS(' ', `sku`, `ean`, `name`) LIKE '%maglia%' OR (`product_model_id` IN (SELECT `id` FROM `gst_product_model` WHERE CONCAT_WS(' ', `name`) LIKE '%maglia%'))))))";
});

check('una relazione con sole relations annidate', function () use ($model) {
    $version = ['table' => 'gst_product', 'local_key' => 'product_id', 'foreign_key' => 'id', 'relations' => [$model]];

    return SSP::buildSearchWhere('maglia', [$version])
        === "(`product_id` IN (SELECT `id` FROM `gst_product` WHERE (`product_model_id` IN (SELECT `id` FROM `gst_product_model` WHERE CONCAT_WS(' ', `name`) LIKE '%maglia%'))))";
});

check('un figlio inutilizzabile non cambia la condizione', function () {
    $version = ['table' => 'gst_product', 'local_key' => 'product_id', 'foreign_key' => 'id', 'columns' => ['sku'], 'relations' => [['table' => 'x'], 'y']];

    return SSP::buildSearchWhere('maglia', [$version])
        === "(`product_id` IN (SELECT `id` FROM `gst_product` WHERE CONCAT_WS(' ', `sku`) LIKE '%maglia%'))";
});

check('senza colonne e senza figli utilizzabili la relazione non conta', function () {
    $version = ['table' => 'gst_product', 'local_key' => 'product_id', 'foreign_key' => 'id', 'relations' => [['table' => 'x']]];

    return SSP::buildSearchWhere('maglia', [$version]) === '';
});

check('identificatori escapati anche nei livelli annidati', function () {
    $evil = ['table' => 'm` WHERE 1=1-- ', 'local_key' => 'k`', 'foreign_key' => 'id`', 'columns' => ['n`']];
    $version = ['table' => 'gst_product', 'local_key' => 'product_id', 'foreign_key' => 'id', 'relations' => [$evil]];

    return SSP::buildSearchWhere('x', [$version])
        === "(`product_id` IN (SELECT `id` FROM `gst_product` WHERE (`k``` IN (SELECT `id``` FROM `m`` WHERE 1=1-- ` WHERE CONCAT_WS(' ', `n```) LIKE '%x%'))))";
});

check('più parole: la condizione annidata si ripete per ogni parola, unite con AND', function () use ($version) {
    $one = SSP::buildSearchWhere('maglia', [$version]);
    $two = SSP::buildSearchWhere('maglia rossa', [$version]);

    return substr_count($two, '`gst_product_model`') === 2
        && $two === $one.' AND '.str_replace('maglia', 'rossa', $one);
});

summary();
