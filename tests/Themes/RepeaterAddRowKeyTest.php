<?php
/** php tests/Themes/RepeaterAddRowKeyTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Il JS del repeater si prova leggendo quello che stampa: senza un browser è
 * l'unico modo, ed è la convenzione della suite (vedi RepeaterRowTest).
 */
$script = static function (): string {
    $field = new class {
        public array $schema = [];
    };

    $field->schema = [
        'id' => 'products',
        'name' => 'products',
        'label' => 'Quello che si vende',
        'value' => ['row_1' => ['name' => 'Blu / S']],
        'columns' => [RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(3)],
        'context' => ['nested' => true],
    ];

    $html = (new Repeater)->render($field);

    return substr($html, (int) strpos($html, '<script'));
};

check('chi aggiunge una riga può darle la chiave', function () use ($script) {
    return str_contains($script(), 'function (containerId, templateId, rowKey)');
});

check('senza chiave il repeater se la inventa come prima', function () use ($script) {
    return str_contains($script(), "rowKey = 'row_' + Date.now()");
});

check('la chiave si ripulisce: finisce dentro i name dei campi', function () use ($script) {
    return str_contains($script(), ".replace(/[^A-Za-z0-9_-]/g, '')");
});

check('due righe con la stessa chiave non nascono', function () use ($script) {
    return str_contains($script(), '.wi-repeater-row[data-wi-row-key="\' + rowKey + \'"]');
});

check('la riga aggiunta torna a chi l\'ha chiesta, per riempirla', function () use ($script) {
    return str_contains($script(), 'return row;');
});

check('l\'ultima riga si elimina davvero quando non se ne possono aggiungere', function () use ($script) {
    return str_contains($script(), "container.dataset.wiAddButton !== 'false'");
});

check('la memoria del raggruppamento non si scrive quando la colonna è decisa', function () use ($script) {
    return str_contains($script(), "if (container.dataset.wiGroupFixed !== 'true') {");
});

check('il raggruppamento si avvia anche senza la tendina', function () use ($script) {
    $js = $script();

    return str_contains($js, 'function (rowsId, templateId, selectId, memoryKey, fixedColumn)')
        && str_contains($js, "if (!container || (!select && fixedColumn === '')) return;");
});

// Le pillole hanno l'id fatto dal `name`: nel template porta `__ROW_KEY__`.
// L'input è nascosto e l'etichetta è l'unica cosa da cliccare: se l'id
// restasse quello del template, il clic sulla seconda riga aggiunta
// spunterebbe la prima.
check('la chiave della riga va anche negli id e nei for, come nei name', function () use ($script) {
    $field = new class {
        public array $schema = [];
    };

    $field->schema = [
        'id' => 'products',
        'name' => 'products',
        'label' => 'Quello che si vende',
        'value' => [],
        'columns' => [RepeaterColumn::key('preferred')->radio(['1' => 'Preferito'])->pills()->label('')->columnSpan(2)],
        'context' => ['nested' => true],
    ];

    $html = (new Repeater)->render($field);
    $js = $script();

    return str_contains($html, 'id="radio-products[__ROW_KEY__][preferred]-1"')
        && str_contains($html, 'for="radio-products[__ROW_KEY__][preferred]-1"')
        && str_contains($js, "fragment.querySelectorAll('[id*=\"__ROW_KEY__\"]')")
        && str_contains($js, "element.id = element.id.replaceAll('__ROW_KEY__', rowKey);")
        && str_contains($js, "fragment.querySelectorAll('label[for*=\"__ROW_KEY__\"]')")
        && str_contains($js, "label.htmlFor = label.htmlFor.replaceAll('__ROW_KEY__', rowKey);");
});

summary();
