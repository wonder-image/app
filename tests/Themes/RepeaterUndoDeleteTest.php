<?php
/** php tests/Themes/RepeaterUndoDeleteTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Una riga eliminata che resta a schermo.
 *
 * Il contratto è il `fieldset`: spento, i suoi campi non vengono postati, e
 * per il server una riga che non arriva è una riga cancellata. Niente campi
 * `_delete` da inventare.
 */
$colonne = static fn (): array => [
    RepeaterColumn::key('id')->hidden(),
    RepeaterColumn::key('label')->text()->label('Nome')->columnSpan(8),
    RepeaterColumn::key('price')->number()->label('Prezzo')->columnSpan(3),
];

/** @param array<string, mixed> $context */
$render = static function (array $context) use ($colonne): string {
    $field = new class($context, $colonne()) {
        public array $schema;

        public function __construct(array $context, array $columns)
        {
            $this->schema = [
                'id' => 'products',
                'name' => 'products',
                'label' => '',
                'value' => ['row_1' => ['id' => '7', 'label' => 'Blu / S', 'price' => '19.90']],
                'columns' => $columns,
                'context' => array_merge(['nested' => true], $context),
            ];
        }
    };

    return (new Repeater)->render($field);
};

$markup = static fn (string $html): string => substr($html, 0, (int) strpos($html, '<script'));

check('senza il modificatore il markup non cambia', function () use ($render, $markup) {
    $html = $markup($render([]));

    return !str_contains($html, 'wi-repeater-row-undo')
        && !str_contains($html, '__wi_present');
});

check('i campi della riga stanno in un fieldset', function () use ($render, $markup) {
    $html = $markup($render(['undo_delete' => true]));

    return str_contains($html, '<fieldset class="row g-2 align-items-start border-0 p-0 m-0">')
        && str_contains($html, '</fieldset>');
});

check('la barra «Annulla» c\'è e nasce nascosta', function () use ($render, $markup) {
    $html = $markup($render(['undo_delete' => true]));

    return str_contains($html, 'wi-repeater-row-undo d-none')
        && str_contains($html, 'window.wiRepeaterUndoRemoveRow(this)')
        && str_contains($html, '>Annulla</button>');
});

check('la barra sta fuori dal fieldset, o non si potrebbe premere', function () use ($render, $markup) {
    $html = $markup($render(['undo_delete' => true]));
    $chiusura = strpos($html, '</fieldset>');

    return $chiusura !== false && strpos($html, 'wi-repeater-row-undo') > $chiusura;
});

check('le parole si possono cambiare', function () use ($render, $markup) {
    $html = $markup($render([
        'undo_delete' => true,
        'undo_label' => 'Rimetti',
        'undo_text' => 'Sparirà al salvataggio.',
    ]));

    return str_contains($html, '>Rimetti</button>') && str_contains($html, 'Sparirà al salvataggio.');
});

check('la sentinella tiene in vita la chiave del repeater', function () use ($render, $markup) {
    // Spegnendo tutte le righe il browser non posterebbe più `products`, e
    // chi controlla «il repeater c'è ma è vuoto» non vedrebbe più niente.
    $html = $markup($render(['undo_delete' => true]));

    return str_contains($html, '<input type="hidden" name="products[__wi_present]" value="1">');
});

check('la sentinella non è una riga', function () {
    $righe = \Wonder\App\Support\Repeater::rowsFromRequest('products', [
        'products' => [
            '__wi_present' => '1',
            'row_1' => ['id' => '7', 'label' => 'Blu / S'],
        ],
    ]);

    return count($righe) === 1 && ($righe[0]['id'] ?? '') === '7';
});

check('il conteggio del gruppo salta le righe annullate', function () use ($render) {
    $html = $render(['undo_delete' => true]);

    return str_contains($html, "!r.classList.contains('wi-repeater-row-deleted')");
});

check('eliminare spegne il fieldset invece di togliere la riga', function () use ($render) {
    $html = $render(['undo_delete' => true]);

    return str_contains($html, "fieldset.disabled = true")
        && str_contains($html, "wi-repeater-row-delete")
        && str_contains($html, "wi-repeater-row-restore");
});

summary();
