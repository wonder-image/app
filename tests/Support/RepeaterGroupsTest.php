<?php
/** php tests/Support/RepeaterGroupsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\Support\RepeaterGroups;

$colonne = [
    RepeaterColumn::key('id')->hidden(),
    RepeaterColumn::key('name')->text()->label('Versione'),
    RepeaterColumn::key('product_variant_id')
        ->select(['10' => 'Blu', '11' => 'Rosso'])
        ->label('Colore'),
];

$righe = [
    'row_1' => ['name' => 'Blu / S', 'product_variant_id' => '10'],
    'row_2' => ['name' => 'Blu / M', 'product_variant_id' => '10'],
    'row_3' => ['name' => 'Rosso / S', 'product_variant_id' => '11'],
    'row_4' => ['name' => 'Senza colore'],
];

check('ogni riga porta valore ed etichetta della colonna', function () use ($colonne, $righe) {
    $gruppi = RepeaterGroups::of($colonne, $righe, ['product_variant_id']);

    return $gruppi['row_1']['product_variant_id'] === ['value' => '10', 'label' => 'Blu']
        && $gruppi['row_3']['product_variant_id'] === ['value' => '11', 'label' => 'Rosso'];
});

check('una riga senza valore resta senza etichetta', function () use ($colonne, $righe) {
    $gruppi = RepeaterGroups::of($colonne, $righe, ['product_variant_id']);

    return $gruppi['row_4']['product_variant_id'] === ['value' => '', 'label' => ''];
});

check('un valore fuori dalle opzioni resta se stesso', function () use ($colonne) {
    $gruppi = RepeaterGroups::of($colonne, ['row_1' => ['product_variant_id' => '99']], ['product_variant_id']);

    return $gruppi['row_1']['product_variant_id'] === ['value' => '99', 'label' => '99'];
});

check('una colonna di testo si etichetta da sé', function () use ($colonne, $righe) {
    $gruppi = RepeaterGroups::of($colonne, $righe, ['name']);

    return $gruppi['row_1']['name'] === ['value' => 'Blu / S', 'label' => 'Blu / S'];
});

check('una colonna che non esiste si ignora', function () use ($colonne, $righe) {
    return RepeaterGroups::of($colonne, $righe, ['colore']) === [];
});

check('le colonne in forma di array funzionano uguale', function () use ($righe) {
    $colonne = [[
        'name' => 'product_variant_id',
        'helper' => 'select',
        'label' => 'Colore',
        'options' => ['10' => 'Blu', '11' => 'Rosso'],
    ]];

    $gruppi = RepeaterGroups::of($colonne, $righe, ['product_variant_id']);

    return $gruppi['row_2']['product_variant_id']['label'] === 'Blu';
});

check('il selettore legge nome ed etichetta della colonna', function () use ($colonne) {
    $colonna = RepeaterGroups::columnByKey($colonne, 'product_variant_id');

    return $colonna !== null
        && RepeaterGroups::nameOf($colonna) === 'product_variant_id'
        && RepeaterGroups::labelOfColumn($colonna) === 'Colore'
        && RepeaterGroups::columnByKey($colonne, 'colore') === null;
});

check('più colonne insieme danno più chiavi per riga', function () use ($colonne, $righe) {
    $gruppi = RepeaterGroups::of($colonne, $righe, ['product_variant_id', 'name']);

    return array_keys($gruppi['row_1']) === ['product_variant_id', 'name'];
});

summary();
