<?php
/** php tests/Themes/RepeaterHandleRenderTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

$render = static function (array $context): string {
    $field = new class($context) {
        public array $schema;

        public function __construct(array $context)
        {
            $this->schema = [
                'id' => 'people',
                'name' => 'people',
                'label' => 'Referenti',
                'value' => ['row_1' => ['name' => 'Giulia', 'role' => 'Acquisti']],
                'columns' => [
                    RepeaterColumn::key('name')->text()->columnSpan(6),
                    RepeaterColumn::key('role')->text()->columnSpan(3),
                ],
                'context' => array_merge(['nested' => true], $context),
            ];
        }
    };

    $html = (new Repeater)->render($field);

    // Solo la riga vera: né il modello della riga nuova né lo script.
    return substr($html, 0, (int) strpos($html, '<template'));
};

check('senza riordino non c\'è maniglia', function () use ($render) {
    return !str_contains($render([]), 'wi-repeater-handle');
});

check('con il riordino la maniglia sta prima dei campi, e le frecce non ci sono più', function () use ($render) {
    $html = $render(['sortable' => true]);

    return str_contains($html, 'wi-repeater-handle')
        && str_contains($html, 'bi-grip-vertical')
        && strpos($html, 'wi-repeater-handle') < strpos($html, 'name="people[row_1][name]"')
        && !str_contains($html, 'wi-repeater-move-up')
        && !str_contains($html, 'wi-repeater-move-down');
});

check('la maniglia ha un nome per chi non la vede, e si può cambiare', function () use ($render) {
    return str_contains($render(['sortable' => true]), 'aria-label="Trascina per riordinare"')
        && str_contains($render(['sortable' => true, 'sort_label' => 'Sposta il referente']), 'aria-label="Sposta il referente"');
});

check('il cestino è un\'icona dopo i campi, non un bottone pieno', function () use ($render) {
    $html = $render([]);

    return strpos($html, 'wi-repeater-delete') > strpos($html, 'name="people[row_1][role]"')
        && !str_contains($html, 'btn btn-danger flex-fill');
});

check('le colonne dichiarate su nove riempiono tutta la riga', function () use ($render) {
    $html = $render(['sortable' => true]);

    return str_contains($html, '<div class="col-6" style="width:66.6667%">')
        && str_contains($html, '<div class="col-3" style="width:33.3333%">');
});

check('senza riordino si contano su undici', function () use ($render) {
    return str_contains($render([]), '<div class="col-6" style="width:54.5455%">');
});

check('in una scheda maniglia e cestino stanno sul riassunto, fuori dal fieldset', function () use ($render) {
    $html = $render(['sortable' => true, 'collapsible' => ['title' => ['name'], 'subtitle' => ['role']]]);
    $fieldset = (int) strpos($html, '<fieldset');

    return strpos($html, 'wi-repeater-handle') < $fieldset
        && strpos($html, 'wi-repeater-delete') < $fieldset
        && substr_count($html, 'wi-repeater-handle') === 1
        && substr_count($html, 'wi-repeater-delete') === 1;
});

check('il trascinamento e le frecce da tastiera stanno nello script', function () use ($render) {
    $field = new class {
        public array $schema = [
            'id' => 'people', 'name' => 'people', 'label' => '', 'value' => [],
            'columns' => [], 'context' => ['nested' => true, 'sortable' => true],
        ];
    };
    $field->schema['columns'] = [RepeaterColumn::key('name')->text()];
    $html = (new Repeater)->render($field);

    return str_contains($html, "'pointerdown'")
        && str_contains($html, "event.key !== 'ArrowUp'")
        && str_contains($html, 'wi-repeater-row-move');
});

summary();
