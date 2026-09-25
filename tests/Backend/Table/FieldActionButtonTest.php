<?php
/** php tests/Backend/Table/FieldActionButtonTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../app/function/backend/plugin.php';

use Wonder\Backend\Table\Field;

// Un warning (colonna assente, chiave mancante) deve far fallire il check.
set_error_handler(static function (int $severity, string $message, string $file = '', int $line = 0): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function makeField(): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => 'immobili', 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10,
        'link' => [ 'modify' => '/immobili/{rowId}/edit/', 'view' => '/immobili/{rowId}/' ],
    ];
    $PATH = (object) [ 'site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api' ];
    $TEXT = (object) [
        'titleS' => 'immobile', 'titleP' => 'immobili', 'last' => 'ultimi', 'all' => 'tutti',
        'article' => 'gli', 'full' => 'pieno', 'empty' => 'vuoto', 'this' => 'questo',
    ];
    $USER = (object) [ 'area' => '', 'authority' => '' ];
    $PAGE = (object) [ 'redirect' => '', 'redirectBase64' => '' ];

    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

function menu(array $row, array $actions): string {
    return makeField()->newField($row, 'action_button', $actions);
}

// Il pre-render passa le azioni come booleani veri, l'AJAX come stringhe:
// `true != 'false'` in PHP 8 è falso, e il menu usciva vuoto al primo draw.
check('azioni come booleani (pre-render) disegnano il menu', function () {
    $html = makeField()->newField(['id' => 7], 'action_button', ['modify' => true, 'view' => true]);

    return str_contains($html, 'bi-three-dots')
        && str_contains($html, "href='/immobili/7/edit/'")
        && str_contains($html, "href='/immobili/7/'");
});

check('azioni come stringhe (AJAX) disegnano il menu', function () {
    $html = makeField()->newField(['id' => 7], 'action_button', ['modify' => 'true']);

    return str_contains($html, "href='/immobili/7/edit/'");
});

check("un'azione spenta non compare", function () {
    $field = makeField();

    return $field->newField(['id' => 7], 'action_button', ['modify' => 'false']) === ''
        && $field->newField(['id' => 8], 'action_button', ['modify' => false]) === '';
});

check('una voce ad array compare con il segnaposto sostituito', function () {
    $html = menu(['id' => 7], ['movimenti' => ['label' => 'Movimenti', 'href' => '/backend/movimenti/?versione={id}']]);

    return str_contains($html, 'href="/backend/movimenti/?versione=7"')
        && str_contains($html, '>Movimenti</a>');
});

check('href e target escapati', function () {
    $html = menu(
        ['id' => 7, 'code' => "a'b\"c<d&e"],
        ['x' => ['label' => 'X', 'href' => '/x/?c={code}', 'target' => '_blank" onclick="alert(1)']]
    );

    return str_contains($html, 'href="/x/?c=a&#039;b&quot;c&lt;d&amp;e"')
        && str_contains($html, 'target="_blank&quot; onclick=&quot;alert(1)"')
        && !str_contains($html, 'c<d');
});

$doc = ['doc' => ['label' => ['load' => 'Visualizza carico', 'unload' => 'Visualizza scarico'], 'href' => '/doc/{id}/']];

check("l'etichetta segue il valore della colonna con il nome della voce", function () use ($doc) {
    return str_contains(menu(['id' => 7, 'doc' => 'load'], $doc), '>Visualizza carico</a>')
        && str_contains(menu(['id' => 8, 'doc' => 'unload'], $doc), '>Visualizza scarico</a>');
});

check("senza un'etichetta per quel valore la voce non compare", function () use ($doc) {
    return menu(['id' => 7, 'doc' => 'transfer'], $doc) === ''
        && menu(['id' => 8, 'doc' => null], $doc) === ''
        && menu(['id' => 9], $doc) === '';
});

$draftOnly = ['conferma' => ['label' => 'Conferma', 'href' => '/c/{id}/', 'filter' => ['row' => ['status' => 'draft']]]];

check('filter.row mostra la voce solo sulle righe giuste', function () use ($draftOnly) {
    return str_contains(menu(['id' => 7, 'status' => 'draft'], $draftOnly), '>Conferma</a>')
        && menu(['id' => 8, 'status' => 'confirmed'], $draftOnly) === ''
        && menu(['id' => 9], $draftOnly) === '';
});

check('filter.row con una lista di valori', function () {
    $actions = ['apri' => ['label' => 'Apri', 'href' => '/a/{id}/', 'filter' => ['row' => ['status' => ['draft', 'confirmed']]]]];

    return str_contains(menu(['id' => 7, 'status' => 'confirmed'], $actions), '>Apri</a>')
        && menu(['id' => 8, 'status' => 'cancelled'], $actions) === '';
});

check('modify e delete ad array rispettano filter.row', function () {
    $actions = [
        'modify' => ['filter' => ['row' => ['status' => 'draft']]],
        'delete' => ['filter' => ['row' => ['status' => 'draft']]],
    ];
    $draft = menu(['id' => 7, 'status' => 'draft'], $actions);

    return str_contains($draft, "href='/immobili/7/edit/'")
        && str_contains($draft, '>Elimina</a>')
        && menu(['id' => 8, 'status' => 'confirmed'], $actions) === '';
});

check('delete ad array nascosto quando la riga non si può eliminare', function () {
    $field = makeField();
    (new ReflectionProperty(Field::class, 'deleteButton'))->setValue($field, false);

    return $field->newField(['id' => 7], 'action_button', ['delete' => ['label' => 'Elimina']]) === '';
});

summary();
