<?php
/** php tests/Themes/RepeaterGroupRenderTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

$colonne = static fn (): array => [
    RepeaterColumn::key('name')->text()->label('Versione')->columnSpan(3),
    RepeaterColumn::key('price')->number()->label('Prezzo')->columnSpan(2),
    RepeaterColumn::key('product_variant_id')
        ->select(['10' => 'Blu', '11' => 'Rosso'])
        ->label('Colore')
        ->columnSpan(2),
];

$righe = [
    'row_1' => ['name' => 'Blu / S', 'price' => '19.90', 'product_variant_id' => '10'],
    'row_2' => ['name' => 'Rosso / S', 'price' => '19.90', 'product_variant_id' => '11'],
];

/** @param array<string, mixed> $context */
$render = static function (array $value, array $context) use ($colonne): string {
    $field = new class($value, $context, $colonne()) {
        public array $schema;

        public function __construct(array $value, array $context, array $columns)
        {
            $this->schema = [
                'id' => 'products',
                'name' => 'products',
                'label' => 'Quello che si vende',
                'value' => $value,
                'columns' => $columns,
                'context' => array_merge(['nested' => true, 'add_label' => 'Aggiungi versione'], $context),
            ];
        }
    };

    return (new Repeater)->render($field);
};

$conGruppi = static fn (array $righe) => [
    'group_by' => ['product_variant_id'],
    'group_command' => ['column' => 'price', 'label' => 'Prezzo del gruppo'],
    'group_count_label' => ['singular' => 'versione', 'plural' => 'versioni'],
];

// Il blocco <script> è condiviso da tutti i repeater e contiene le funzioni
// del raggruppamento: quello che non deve cambiare è il markup.
$markup = static fn (string $html): string => substr($html, 0, (int) strpos($html, '<script'));

check('senza raggruppamento il markup non cambia', function () use ($render, $markup, $righe) {
    $html = $markup($render($righe, []));

    return !str_contains($html, 'wi-repeater-groupbar')
        && !str_contains($html, 'data-wi-group-')
        && !str_contains($html, 'group-template')
        && !str_contains($html, 'wiRepeaterGroupInit');
});

check('le righe portano valore ed etichetta del gruppo', function () use ($render, $righe, $conGruppi) {
    $html = $render($righe, $conGruppi($righe));

    return str_contains($html, 'data-wi-group-product_variant_id="10"')
        && str_contains($html, 'data-wi-group-label-product_variant_id="Blu"')
        && str_contains($html, 'data-wi-group-label-product_variant_id="Rosso"');
});

check('la barra offre "Nessuno" e la colonna, con la sua etichetta', function () use ($render, $righe, $conGruppi) {
    $html = $render($righe, $conGruppi($righe));

    return str_contains($html, 'wi-repeater-groupbar')
        && str_contains($html, '<option value="">Nessuno</option>')
        && str_contains($html, '<option value="product_variant_id">Colore</option>');
});

check('il template della testata porta comando e parole del conteggio', function () use ($render, $righe, $conGruppi) {
    $html = $render($righe, $conGruppi($righe));

    return str_contains($html, 'wi-repeater-group-header')
        && str_contains($html, 'data-wi-command-column="price"')
        && str_contains($html, 'Prezzo del gruppo')
        && str_contains($html, 'data-wi-count-singular="versione"')
        && str_contains($html, 'data-wi-count-plural="versioni"');
});

check('il contenitore dice dove sta il template e se i gruppi nascono chiusi', function () use ($render, $righe, $conGruppi) {
    $html = $render($righe, array_merge($conGruppi($righe), ['group_collapsed' => true]));

    return str_contains($html, 'data-wi-group-template="products-group-template"')
        && str_contains($html, 'data-wi-group-collapsed="true"');
});

check('con una riga sola non c\'è niente da raggruppare', function () use ($render, $conGruppi) {
    $una = ['row_1' => ['name' => 'Unica', 'product_variant_id' => '10']];

    return !str_contains($render($una, $conGruppi($una)), 'wi-repeater-groupbar');
});

check('una colonna dichiarata ma assente non rompe niente', function () use ($render, $righe) {
    $html = $render($righe, ['group_by' => ['colore_che_non_esiste']]);

    return !str_contains($html, 'data-wi-group-colore_che_non_esiste')
        && !str_contains($html, 'wi-repeater-groupbar');
});

check('il template della riga resta fuori da ogni gruppo', function () use ($render, $righe, $conGruppi) {
    $html = $render($righe, $conGruppi($righe));
    $template = substr($html, (int) strpos($html, '<template id="products-template">'));

    return !str_contains(substr($template, 0, (int) strpos($template, '</template>')), 'data-wi-group-');
});

check('il raggruppamento fisso non stampa la tendina, e i gruppi ci sono lo stesso', function () use ($render, $righe) {
    $html = $render($righe, ['group_fixed' => 'product_variant_id']);

    return !str_contains($html, 'wi-repeater-groupbar')
        && str_contains($html, 'data-wi-group-fixed="true"')
        && str_contains($html, "wiRepeaterGroupInit('products-rows', 'products-group-template', 'products-groupby', 'products', 'product_variant_id')")
        && str_contains($html, 'data-wi-group-product_variant_id="10"');
});

check('fisso, i gruppi ci sono anche con una riga sola', function () use ($render) {
    $una = ['row_1' => ['name' => 'Blu / S', 'price' => '19.90', 'product_variant_id' => '10']];
    $html = $render($una, ['group_fixed' => 'product_variant_id']);

    return str_contains($html, 'data-wi-group-fixed="true"')
        && str_contains($html, 'wi-repeater-group-header')
        && str_contains($html, 'data-wi-group-label-product_variant_id="Blu"');
});

check('fisso, le frecce di riordino non si stampano', function () use ($render, $markup, $righe) {
    // Solo il markup: il blocco <script> nomina le frecce in ogni caso.
    $html = $markup($render($righe, ['group_fixed' => 'product_variant_id', 'sortable' => true]));

    // Niente frecce e niente colonna larga tre a tenere il loro posto vuoto.
    return !str_contains($html, 'wi-repeater-move-up')
        && !str_contains($html, 'col-3 d-flex align-items-stretch');
});

check('una colonna fissa che non esiste non raggruppa niente', function () use ($render, $markup, $righe) {
    $html = $markup($render($righe, ['group_fixed' => 'colore']));

    return !str_contains($html, 'data-wi-group-fixed')
        && !str_contains($html, 'wi-repeater-group-header');
});

check('senza bottone «Aggiungi» il contenitore lo dice, e il template resta', function () use ($render, $markup, $righe) {
    $html = $markup($render($righe, ['add_button' => false]));

    return !str_contains($html, 'wiRepeaterAddRow(')
        && str_contains($html, 'data-wi-add-button="false"')
        && str_contains($html, '<template id="products-template">');
});

check('senza righe ne compare una vuota, salvo quando si chiede di partire vuoti', function () use ($render, $markup) {
    $conFinta = $markup($render([], []));
    $vuoto = $markup($render([], ['start_empty' => true]));

    return substr_count($conFinta, 'wi-repeater-row') > substr_count($vuoto, 'wi-repeater-row')
        && !str_contains($vuoto, 'data-wi-row-key="row_1"');
});

check('il contenitore porta il nome del campo, per chi genera righe da fuori', function () use ($render, $markup, $righe) {
    return str_contains($markup($render($righe, [])), 'data-wi-repeater="products"');
});

summary();
