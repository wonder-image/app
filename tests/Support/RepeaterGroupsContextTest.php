<?php
/** php tests/Support/RepeaterGroupsContextTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputRepeater;
use Wonder\App\ResourceSchema\RepeaterColumn;

$campo = fn () => FormField::key('products')->repeater([
    RepeaterColumn::key('name')->text()->label('Versione'),
    RepeaterColumn::key('price')->number()->label('Prezzo'),
]);

check('senza setter il context non parla di gruppi', function () use ($campo) {
    $context = (array) ($campo()->get('context') ?? []);

    return !isset($context['group_by'])
        && !isset($context['group_command'])
        && !isset($context['group_collapsed']);
});

check('le colonne raggruppabili finiscono nel context', function () use ($campo) {
    $context = (array) ($campo()->repeaterGroupBy('product_variant_id', 'name')->get('context') ?? []);

    return $context['group_by'] === ['product_variant_id', 'name'];
});

check('le chiavi vuote e i doppioni non entrano', function () use ($campo) {
    $context = (array) ($campo()->repeaterGroupBy('product_variant_id', '', '  ', 'product_variant_id')->get('context') ?? []);

    return $context['group_by'] === ['product_variant_id'];
});

check('il comando porta colonna ed etichetta', function () use ($campo) {
    $context = (array) ($campo()->repeaterGroupCommand('price', 'Prezzo del gruppo')->get('context') ?? []);

    return $context['group_command'] === ['column' => 'price', 'label' => 'Prezzo del gruppo'];
});

check('il comando senza etichetta tiene una stringa vuota', function () use ($campo) {
    $context = (array) ($campo()->repeaterGroupCommand('price')->get('context') ?? []);

    return $context['group_command'] === ['column' => 'price', 'label' => ''];
});

check('chiusi alla nascita e parole del conteggio', function () use ($campo) {
    $context = (array) ($campo()
        ->repeaterGroupCollapsed()
        ->repeaterGroupCountLabel('versione', 'versioni')
        ->get('context') ?? []);

    return $context['group_collapsed'] === true
        && $context['group_count_label'] === ['singular' => 'versione', 'plural' => 'versioni'];
});

check('i setter si concatenano e tornano il repeater', function () use ($campo) {
    $input = $campo()->repeaterGroupBy('name')->repeaterGroupCollapsed(false);

    return $input instanceof InputRepeater
        && ((array) $input->get('context'))['group_collapsed'] === false;
});

summary();
