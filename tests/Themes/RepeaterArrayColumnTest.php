<?php
/** php tests/Themes/RepeaterArrayColumnTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Le colonne array (`['name' => ..., 'helper' => ...]`) sono deprecate: il
 * renderer le converte in `RepeaterColumn` e da lì segue l'unico percorso.
 */
$render = static function (array $columns): string {
    $field = new class {
        public array $schema = [];
    };

    $field->schema = [
        'id' => 'rows',
        'name' => 'rows',
        'label' => 'Righe',
        'value' => ['row_1' => ['title' => 'Primo', 'id' => '5', 'kind' => 'b']],
        'columns' => $columns,
        'context' => ['nested' => true],
    ];

    $html = (new Repeater)->render($field);

    return preg_replace('/_[a-z]{10}\b/', '_ID', $html) ?? $html;
};

$fromArrays = $render([
    ['name' => 'title', 'helper' => 'text', 'label' => 'Titolo', 'col' => 6],
    ['name' => 'id', 'helper' => 'hidden'],
    ['name' => 'kind', 'helper' => 'select', 'label' => 'Tipo', 'options' => ['a' => 'A', 'b' => 'B']],
]);

$fromColumns = $render([
    RepeaterColumn::key('title')->text()->label('Titolo')->columnSpan(6),
    RepeaterColumn::key('id')->hidden(),
    RepeaterColumn::key('kind')->select(['a' => 'A', 'b' => 'B'])->label('Tipo'),
]);

check('le colonne array rendono come le RepeaterColumn equivalenti', function () use ($fromArrays, $fromColumns) {
    return $fromArrays === $fromColumns;
});

check('la colonna hidden resta fuori dalla griglia visibile', function () use ($fromArrays) {
    return str_contains($fromArrays, 'type="hidden"')
        && !str_contains($fromArrays, '>Id<');
});

check('il valore della riga arriva alla colonna convertita', function () use ($fromArrays) {
    return str_contains($fromArrays, 'value="Primo"');
});

check('senza helper la colonna array è un testo', function () use ($render) {
    return str_contains($render([['name' => 'title']]), 'type="text"');
});

summary();
