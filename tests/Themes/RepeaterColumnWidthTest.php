<?php
/** php tests/Themes/RepeaterColumnWidthTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Larghezze delle caselle e blocco delle informazioni avanzate.
 *
 * Una casella che dichiara un dodicesimo deve prenderne uno: prima l'uno
 * contava come "non dichiarato" e prezzi e quantità venivano fuori larghi
 * quanto la riga, uno sotto l'altro.
 */
$colonne = static fn (): array => [
    RepeaterColumn::key('id')->hidden(),
    RepeaterColumn::key('option')->text()->label('Opzione')->columnSpan(3),
    RepeaterColumn::key('price')->number()->label('Prezzo')->columnSpan(1),
    RepeaterColumn::key('note')->text()->label('Nota'),
    RepeaterColumn::key('sku')->text()->label('SKU')->columnSpan(4),
    RepeaterColumn::key('photo')->text()->label('Foto')->columnSpan(12),
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
                'label' => '',
                'value' => $value,
                'columns' => $columns,
                'context' => array_merge(['nested' => true], $context),
            ];
        }
    };

    return (new Repeater)->render($field);
};

$markup = static fn (string $html): string => substr($html, 0, (int) strpos($html, '<script'));

$riga = ['row_1' => ['option' => 'S', 'price' => '19.90', 'note' => '', 'sku' => '', 'photo' => '']];

check('una casella che chiede un dodicesimo ne prende uno', function () use ($render, $markup, $riga) {
    $html = $markup($render($riga, []));

    return str_contains($html, '<div class="col-1">')
        && substr_count($html, '<div class="col-1">') >= 1;
});

check('chi non dichiara niente prende tutto lo spazio che resta', function () use ($render, $markup, $riga) {
    return str_contains($markup($render($riga, [])), '<div class="col-11">');
});

check('le altre larghezze restano quelle dichiarate', function () use ($render, $markup, $riga) {
    $html = $markup($render($riga, []));

    return str_contains($html, '<div class="col-3">')
        && str_contains($html, '<div class="col-4">')
        && str_contains($html, '<div class="col-12">');
});

check('senza colonne avanzate non compare nessun bottone', function () use ($render, $markup, $riga) {
    $html = $markup($render($riga, []));

    return !str_contains($html, 'wi-repeater-advanced');
});

check('le colonne avanzate escono dalla riga e vanno nel blocco', function () use ($render, $markup, $riga) {
    $html = $markup($render($riga, ['advanced' => ['sku', 'photo']]));
    $blocco = substr($html, (int) strpos($html, 'wi-repeater-advanced"'));
    $prima = substr($html, 0, (int) strpos($html, 'wi-repeater-advanced-toggle'));

    return str_contains($blocco, 'name="products[row_1][sku]"')
        && str_contains($blocco, 'name="products[row_1][photo]"')
        && !str_contains($prima, 'name="products[row_1][sku]"')
        && str_contains($prima, 'name="products[row_1][price]"');
});

check('il bottone porta le parole che gli si danno', function () use ($render, $markup, $riga) {
    $html = $markup($render($riga, ['advanced' => ['sku'], 'advanced_label' => 'Compila tutto']));

    return str_contains($html, 'Compila tutto')
        && str_contains($html, 'window.wiRepeaterToggleAdvanced(this)');
});

check('senza parole proprie il bottone ne ha di sue', function () use ($render, $markup, $riga) {
    return str_contains(
        $markup($render($riga, ['advanced' => ['sku']])),
        'Compila le informazioni avanzate'
    );
});

check('il blocco nasce chiuso', function () use ($render, $markup, $riga) {
    return str_contains($markup($render($riga, ['advanced' => ['sku']])), 'wi-repeater-advanced d-none');
});

check('nasce chiuso anche su una riga che ha già i suoi codici', function () use ($render, $markup) {
    // Lo SKU lo propone il pannello: una griglia in cui ogni riga si apre da
    // sola è la griglia lunga da cui si scappava.
    $riga = ['row_1' => ['option' => 'S', 'price' => '19.90', 'sku' => 'MAG-9-S', 'photo' => '']];
    $html = $markup($render($riga, ['advanced' => ['sku', 'photo']]));

    return str_contains($html, 'wi-repeater-advanced d-none')
        && !str_contains($html, 'bi-chevron-up');
});

check('le caselle nascoste restano nel modulo, quindi si salvano', function () use ($render, $markup) {
    $riga = ['row_1' => ['option' => 'S', 'sku' => 'MAG-9-S']];
    $html = $markup($render($riga, ['advanced' => ['sku']]));

    return str_contains($html, 'name="products[row_1][sku]"')
        && str_contains($html, 'MAG-9-S');
});

summary();
