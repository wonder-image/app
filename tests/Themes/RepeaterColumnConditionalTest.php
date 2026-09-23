<?php
/** php tests/Themes/RepeaterColumnConditionalTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Una colonna di repeater che compare e sparisce con un altro campo.
 *
 * La regola deve stare sul contenitore della colonna: sull'input il JS della
 * lib nasconderebbe il genitore sbagliato — o il riquadro intero, se il
 * repeater sta in una card che si nasconde da sé — e un campo che il widget
 * sostituisce (FilePond) la perderebbe.
 */
$colonne = static fn (): array => [
    RepeaterColumn::key('id')->hidden(),
    RepeaterColumn::key('image')->text()->label('Fantasia')->columnSpan(2)->visibleWhen('type', 'pattern'),
    RepeaterColumn::key('label')->text()->label('Valore')->columnFill(),
    RepeaterColumn::key('color')->color()->label('Colore')->columnSpan(3)->visibleWhen('type', ['color', 'pattern']),
    RepeaterColumn::key('note')->text()->label('Nota')->columnSpan(4)->hiddenWhen('type', 'text'),
    RepeaterColumn::key('description')->text()->label('Descrizione')->columnSpan(12)->visibleWhen('type', 'select'),
];

/** @param array<string, mixed> $context */
$render = static function (array $context = []) use ($colonne): string {
    $field = new class($context, $colonne()) {
        public array $schema;

        public function __construct(array $context, array $columns)
        {
            $this->schema = [
                'id' => 'values',
                'name' => 'values',
                'label' => '',
                'value' => ['row_1' => ['label' => 'Blu', 'color' => '#1f4ed8']],
                'columns' => $columns,
                'context' => array_merge(['nested' => true], $context),
            ];
        }
    };

    return (new Repeater)->render($field);
};

$markup = static fn (string $html): string => substr($html, 0, (int) strpos($html, '<template'));

check('la regola va sul contenitore della colonna, marcato come tale', function () use ($render, $markup) {
    return str_contains(
        $markup($render()),
        '<div class="col-2" data-visible-when="type" data-visible-when-values="pattern" data-wi-conditional-container="true">'
    );
});

check('più valori si scrivono separati da virgola', function () use ($render, $markup) {
    return str_contains(
        $markup($render()),
        '<div class="col-3" data-visible-when="type" data-visible-when-values="color,pattern" data-wi-conditional-container="true">'
    );
});

check('hiddenWhen usa le sue chiavi', function () use ($render, $markup) {
    return str_contains(
        $markup($render()),
        '<div class="col-4" data-hidden-when="type" data-hidden-when-values="text" data-wi-conditional-container="true">'
    );
});

check('una colonna senza regole resta un contenitore nudo', function () use ($render, $markup) {
    $html = $markup($render());

    return str_contains($html, '<div class="col"><')
        && substr_count($html, 'data-wi-conditional-container') === 4;
});

check('la colonna che riempie prende col, non un dodicesimo', function () use ($render, $markup) {
    $html = $markup($render());
    $valore = strpos($html, 'name="values[row_1][label]"');
    $contenitore = strrpos(substr($html, 0, (int) $valore), '<div class="col');

    return $valore !== false
        && str_starts_with(substr($html, (int) $contenitore), '<div class="col">');
});

check('la regola resta anche sull\'input, per il required', function () use ($render, $markup) {
    $html = $markup($render());
    $input = substr($html, (int) strpos($html, 'name="values[row_1][color]"') - 400, 800);

    return str_contains($input, 'data-visible-when="type"');
});

check('anche il modello della riga nuova porta la regola sul contenitore', function () use ($render) {
    $html = $render();
    $modello = substr($html, (int) strpos($html, '<template'));

    return str_contains($modello, 'name="values[__ROW_KEY__][image]"')
        && str_contains($modello, 'data-visible-when-values="pattern" data-wi-conditional-container="true"');
});

check('una colonna avanzata porta la regola nel blocco', function () use ($render, $markup) {
    $html = $markup($render(['advanced' => ['description']]));
    $blocco = substr($html, (int) strpos($html, 'wi-repeater-advanced"'));

    return str_contains(
        $blocco,
        '<div class="col-12" data-visible-when="type" data-visible-when-values="select" data-wi-conditional-container="true">'
    );
});

check('le colonne nascoste restano senza contenitore', function () use ($render, $markup) {
    return str_contains(
        $markup($render()),
        'm-0"><input type="hidden" name="values[row_1][id]"'
    );
});

check('conditionalAttributes riporta la regola, vuota senza regole', function () {
    $con = FormField::key('unit')->select(['g' => 'g'])->visibleWhen('type', ['number', 'text']);
    $senza = FormField::key('name')->text();

    return $con->conditionalAttributes() === [
            'data-visible-when' => 'type',
            'data-visible-when-values' => 'number,text',
        ]
        && $senza->conditionalAttributes() === [];
});

check('la regola sopravvive al type-helper chiamato dopo', function () {
    $campo = FormField::key('unit')->visibleWhen('type', 'number')->select(['g' => 'g']);

    return ($campo->conditionalAttributes()['data-visible-when'] ?? '') === 'type';
});

check('un campo vuoto non scrive regole', function () {
    return FormField::key('unit')->text()->visibleWhen('  ', 'number')->conditionalAttributes() === [];
});

check('fuori dal repeater la regola resta sull\'input, senza contenitore', function () {
    $html = FormField::key('unit')->select(['g' => 'Grammi'])->visibleWhen('type', ['number', 'text'])->render('bootstrap');

    return str_contains($html, 'data-visible-when="type"')
        && str_contains($html, 'data-visible-when-values="number,text"')
        && !str_contains($html, 'data-wi-conditional-container');
});

summary();
