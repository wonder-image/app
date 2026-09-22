<?php
/** php tests/Support/RepeaterRelationConditionTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\Support\Repeater;

check('senza condizione si guardano tutte le righe del padre', function () {
    $relation = RepeaterRelation::make('gst_product_images', 'product_model_id');

    return Repeater::relationCondition($relation, 7) === [
        'product_model_id' => 7,
        'deleted' => 'false',
    ];
});

check('la fetta entra nella condizione', function () {
    $relation = RepeaterRelation::make('gst_product_images', 'product_model_id')
        ->condition(['product_variant_id' => 12]);

    return Repeater::relationCondition($relation, 7) === [
        'product_variant_id' => 12,
        'product_model_id' => 7,
        'deleted' => 'false',
    ];
});

check('il padre vince sulla fetta che provasse a riscriverlo', function () {
    $relation = RepeaterRelation::make('gst_product_images', 'product_model_id')
        ->condition(['product_model_id' => 99, 'product_variant_id' => 12]);

    return Repeater::relationCondition($relation, 7)['product_model_id'] === 7;
});

check('senza soft delete la condizione non lo nomina', function () {
    $relation = RepeaterRelation::make('righe', 'padre_id')
        ->softDelete(false)
        ->condition(['reparto' => 'cucina']);

    return Repeater::relationCondition($relation, 3) === [
        'reparto' => 'cucina',
        'padre_id' => 3,
    ];
});

check('la condizione si dichiara e si rilegge', function () {
    $relation = RepeaterRelation::make('righe', 'padre_id');

    return $relation->condition === []
        && $relation->condition(['x' => 1])->condition === ['x' => 1];
});

summary();
