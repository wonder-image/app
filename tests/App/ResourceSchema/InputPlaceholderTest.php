<?php
/** php tests/App/ResourceSchema/InputPlaceholderTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\FormField;

check('placeholder nello schema e negli attributi dell\'elemento', function () {
    $field = FormField::key('email')->text()->placeholder('info@esempio.it');
    $element = $field->compile();

    return $field->get('placeholder') === 'info@esempio.it'
        && $element !== null
        && $element->getAttr('placeholder') === 'info@esempio.it';
});

check('senza placeholder l\'attributo resta vuoto', function () {
    $element = FormField::key('email')->text()->compile();

    return $element !== null && (string) $element->getAttr('placeholder') === '';
});

summary();
