<?php
/** php tests/App/ResourceSchema/InputRequiredTest.php — required(false) toglie l'attributo, il resto resta. */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\FormField;

echo "Input::required()\n";

check('required() aggiunge, required(false) toglie solo quello', function () {
    $field = FormField::key('street')->text()->attribute('data-required-note="x"')->required()->attribute('readonly');
    $on = (string) $field->get('attribute');
    $off = (string) $field->required(false)->get('attribute');
    return preg_match('/(^|\s)required(\s|$)/', $on) === 1
        && preg_match('/(^|\s)required(\s|$)/', $off) === 0
        && str_contains($off, 'data-required-note="x"') && str_contains($off, 'readonly');
});

check('required(false) su un campo libero non cambia niente', function () {
    return (string) FormField::key('city')->text()->required(false)->get('attribute') === '';
});

summary();
