<?php
/** php tests/Backend/Table/ReorderTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../app/function/backend/plugin.php';

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Table\Field;
use Wonder\Backend\Table\ListProvider;
use Wonder\Backend\Table\Reorder;

final class ReorderTestResource extends Resource
{
}

check('reorderable() salva colonna e gruppo, e rifiuta nomi che non sono colonne', function () {
    $schema = TableLayoutSchema::for(ReorderTestResource::class);

    if ($schema->get('reorder') !== null) {
        return false;
    }

    $schema->reorderable('position', 'parent_id');

    try {
        TableLayoutSchema::for(ReorderTestResource::class)->reorderable('position`; DROP');

        return false;
    } catch (RuntimeException) {
        return $schema->get('reorder') === ['column' => 'position', 'group' => 'parent_id']
            && TableLayoutSchema::for(ReorderTestResource::class)->reorderable()->get('reorder') === ['column' => 'position', 'group' => null];
    }
});

check('la config arriva ripulita, o null', function () {
    return Reorder::config(['column' => 'position', 'group' => '']) === ['column' => 'position', 'group' => null]
        && Reorder::config(['column' => 'ordine', 'group' => 'parent_id']) === ['column' => 'ordine', 'group' => 'parent_id']
        && Reorder::config(['column' => 'a b']) === null
        && Reorder::config(['column' => 'position', 'group' => 'x`y']) === null
        && Reorder::config(null) === null
        && Reorder::config('position') === null;
});

check('la maniglia si vede solo con la lista ordinata per posizione, dal basso in alto', function () {
    $config = ['column' => 'position', 'group' => null];

    return Reorder::active($config, 'position', 'asc')
        && Reorder::active($config, 'position', '')
        && !Reorder::active($config, 'position', 'DESC')
        && !Reorder::active($config, 'name', 'asc')
        && !Reorder::active(null, 'position', 'asc');
});

check('la riga va dopo la vicina che la precede', fn () =>
    Reorder::move([1, 2, 3, 4], 4, [1, 4, 2, 3]) === [1, 4, 2, 3]
    && Reorder::move([1, 2, 3, 4], 1, [2, 3, 1, 4]) === [2, 3, 1, 4]
);

check('in cima alla pagina va prima della vicina che la segue', fn () =>
    // Pagina 2 di una lista da 6: la riga 6 portata in cima alla pagina.
    Reorder::move([1, 2, 3, 4, 5, 6], 6, [6, 4, 5]) === [1, 2, 3, 6, 4, 5]
);

check('le righe di un altro gruppo o nascoste dai filtri non contano', fn () =>
    // 10 e 11 sono di un altro padre; 3 non si vede.
    Reorder::move([1, 2, 3, 4], 4, [10, 1, 11, 4, 2]) === [1, 4, 2, 3]
    && Reorder::move([1, 2, 3, 4], 2, [2, 10, 11]) === null
);

check('niente da fare: stessa posizione, riga fuori dal gruppo o fuori dalla pagina', fn () =>
    Reorder::move([1, 2, 3], 2, [1, 2, 3]) === null
    && Reorder::move([1, 2, 3], 9, [1, 9, 2]) === null
    && Reorder::move([1, 2, 3], 3, [1, 2]) === null
);

check('si scrivono solo le posizioni che cambiano, da 1, e i buchi si chiudono', fn () =>
    Reorder::positions([4, 1, 2], [1 => 1, 2 => 5, 4 => 9]) === [4 => 1, 1 => 2, 2 => 3]
    && Reorder::positions([1, 2], [1 => '1', 2 => '2']) === []
    && Reorder::positions([1, 2], [1 => null, 2 => 2]) === [1 => 1]
);

check('la maniglia porta l\'id della riga ed è un bottone che non invia il form', function () {
    $html = Reorder::handle('7"x');

    return str_contains($html, 'data-wi-reorder-id="7"')
        && str_contains($html, 'type="button"')
        && str_contains($html, 'data-wi-row-drag');
});

check('lo script non lascia uscire dalle virgolette id, endpoint e resource', function () {
    $script = Reorder::script('tbl</script>', '/api/backend/reorder/', "a'b");

    return substr_count($script, '</script>') === 2
        && str_contains($script, '"resource":"a\\u0027b"')
        && str_contains($script, 'window.wiRowDrag(body')
        && str_contains($script, "'X-WI-CSRF'");
});

$field = static function (): Field {
    return new Field(
        (object) ['id' => 't', 'table' => 'categorie', 'connection' => null, 'database' => 'main', 'field' => [], 'page' => 0, 'length' => 10, 'link' => []],
        (object) ['site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api'],
        (object) ['titleS' => 'x', 'titleP' => 'x', 'last' => '', 'all' => '', 'article' => '', 'full' => '', 'empty' => '', 'this' => ''],
        (object) ['area' => '', 'authority' => ''],
        (object) ['redirect' => '', 'redirectBase64' => '']
    );
};

$fields = [
    ['name' => 'row-drag', 'other' => ['reorder' => ['column' => 'position', 'group' => 'parent_id']]],
    ['name' => 'name', 'other' => ['format' => 'text']],
];

check('la colonna della maniglia legge l\'id e ha la maniglia solo se attiva', function () use ($field, $fields) {
    $on = ListProvider::buildColumns($fields, false, 2, $field(), true)[0];
    $off = ListProvider::buildColumns($fields, false, 2, $field(), false)[0];

    return $on['db'] === 'id'
        && str_contains(($on['formatter'])(['id' => 5], 'id', $on['format']), 'data-wi-reorder-id="5"')
        && ($off['formatter'])(['id' => 5], 'id', $off['format']) === '';
});

check('la config del riordino si ritrova dalle colonne della richiesta', fn () =>
    ListProvider::reorderConfig($fields) === ['column' => 'position', 'group' => 'parent_id']
    && ListProvider::reorderConfig([['name' => 'name']]) === null
);

summary();
