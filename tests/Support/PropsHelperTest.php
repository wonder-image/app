<?php // tests/Support/PropsHelperTest.php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/helper.php';

check('defaults applied, data wins', function () {
    $out = props(['a' => 1], ['a' => 0, 'b' => 2]);
    return $out === ['a' => 1, 'b' => 2];
});

check('required present passes', function () {
    $out = props(['x' => 'v'], [], ['x']);
    return $out === ['x' => 'v'];
});

check('required missing throws', function () {
    try {
        props([], [], ['x']);
        return false;
    } catch (\InvalidArgumentException $e) {
        return str_contains($e->getMessage(), 'x');
    }
});

summary();
