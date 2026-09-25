<?php
/** php tests/Backend/Filter/FilterQueryTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Filter\FilterCustom;
use Wonder\Backend\Support\ResourceTableRenderer;
use Wonder\Backend\Table\Table;

final class FilterQueryTestModel extends Model
{
    public static string $table = 'filter_query_test';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class FilterQueryTestResource extends Resource
{
    public static string $model = FilterQueryTestModel::class;
}

$categories = [
    4 => ['name' => 'Maglie', 'child' => [7 => 'Polo']],
    5 => 'Pantaloni',
];

$calls = [];
$where = function (array $ids) use (&$calls): string {
    $calls[] = $ids;

    return '`category_id` IN ('.implode(', ', array_map('intval', $ids)).')';
};

$options = ['column' => 'category', 'input' => 'tree', 'array' => $categories, 'where' => $where];

check('filterQuery() registra il filtro con la sua closure', function () use ($categories, $where) {
    $filters = TableLayoutSchema::for(FilterQueryTestResource::class)
        ->filterQuery(' Categoria ', ' category ', $categories, $where, 'tree')
        ->all()['custom_filters'];

    return $filters === [[
        'label' => 'Categoria',
        'column' => 'category',
        'array' => $categories,
        'input' => 'tree',
        'search' => false,
        'column_type' => null,
        'value' => null,
        'where' => $where,
    ]];
});

check('la closure riceve solo i valori fra le opzioni', function () use ($options, &$calls) {
    $calls = [];
    $sql = FilterCustom::condition($options, ['', '4', '99', "1' OR 1=1"]);

    return $sql === '(`category_id` IN (4)) ' && $calls === [['4']];
});

check('anche i figli dell\'albero sono ammessi', function () use ($options, &$calls) {
    $calls = [];
    $sql = FilterCustom::condition($options, ['', '7']);

    return $sql === '(`category_id` IN (7)) ' && $calls === [['7']];
});

check('se non resta nessun valore la closure non si chiama', function () use ($options, &$calls) {
    $calls = [];

    return FilterCustom::condition($options, ['', '99']) === ''
        && FilterCustom::condition($options, ['']) === ''
        && $calls === [];
});

check('select: la closure riceve il valore scelto', function () use ($categories, $where, &$calls) {
    $calls = [];
    $sql = FilterCustom::condition(['column' => 'category', 'input' => 'select', 'array' => $categories, 'where' => $where], '5');

    return $sql === '(`category_id` IN (5)) ' && $calls === [['5']];
});

check('una closure che restituisce solo spazi non filtra', function () use ($categories) {
    $blank = static fn (array $values): string => '  ';

    return FilterCustom::condition(['column' => 'category', 'input' => 'select', 'array' => $categories, 'where' => $blank], '5') === '';
});

check('filterArguments: la closure arriva come ottavo argomento', function () use ($categories, $where) {
    $arguments = ResourceTableRenderer::filterArguments([
        'label' => 'Categoria', 'column' => 'category', 'array' => $categories, 'input' => 'tree', 'where' => $where,
    ]);

    return $arguments === ['Categoria', 'category', $categories, 'tree', false, null, null, $where];
});

check('filterArguments: filtri classici senza closure, filtri incompleti scartati', function () {
    $classic = ResourceTableRenderer::filterArguments(['label' => 'Stato', 'column' => 'status', 'array' => ['a' => 'A']]);

    return $classic === ['Stato', 'status', ['a' => 'A'], 'select', false, null, null, null]
        && ResourceTableRenderer::filterArguments(['label' => 'Stato', 'column' => 'status', 'array' => []]) === null
        && ResourceTableRenderer::filterArguments(['label' => '', 'column' => 'status', 'array' => ['a' => 'A']]) === null
        && ResourceTableRenderer::filterArguments('status') === null;
});

check('Table::addFilter() conserva la closure fino a FilterCustom', function () use ($categories, $where, &$calls) {
    $table = (new ReflectionClass(Table::class))->newInstanceWithoutConstructor();
    $table->addFilter('Categoria', 'category', $categories, 'tree', false, null, null, $where);
    $stored = (new ReflectionProperty(Table::class, 'filterCustom'))->getValue($table);

    $calls = [];

    return $stored[0]['where'] === $where
        && FilterCustom::condition($stored[0], ['', '4']) === '(`category_id` IN (4)) '
        && $calls === [['4']];
});

summary();
