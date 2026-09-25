<?php
/** php tests/Backend/Filter/FilterCustomGenerateTest.php */
declare(strict_types=1);

// Le funzioni del form (select, check, checkTree) stanno in
// app/function/backend/input.php, che collide con harness.php: qui si
// sostituiscono con funzioni vuote nel namespace di FilterCustom, che PHP
// cerca prima di quelle globali.
namespace Wonder\Backend\Filter {
    function select(...$args): string { return ''; }
    function check(...$args): string { return ''; }
    function checkTree(...$args): string { return ''; }
}

namespace {
    require __DIR__ . '/../../../vendor/autoload.php';
    require __DIR__ . '/../../harness.php';

    use Wonder\Backend\Filter\FilterCustom;

    function runFilter(array $filters, array $get, array $customGet = []): FilterCustom
    {
        $_GET = $get;

        $filter = (new ReflectionClass(FilterCustom::class))->newInstanceWithoutConstructor();
        $filter->table = 'tbl';
        $filter->filterColumn = $filters;
        $filter->dropdown = true;
        $filter->customGet = $customGet;

        (new ReflectionMethod(FilterCustom::class, 'generate'))->invoke($filter);

        return $filter;
    }

    $filters = [
        ['label' => 'Marchio', 'column' => 'brand_id', 'column_type' => null, 'array' => ['5' => 'A'], 'input' => 'select', 'search' => false, 'value' => null],
        ['label' => 'Stato', 'column' => 'status', 'column_type' => null, 'array' => ['a' => 'A'], 'input' => 'checkbox', 'search' => false, 'value' => null],
        ['label' => 'Tag', 'column' => 'tags', 'column_type' => 'multiple', 'array' => ['x' => 'X'], 'input' => 'checkbox', 'search' => false, 'value' => null],
    ];

    check('le condizioni si uniscono con AND, la checkbox senza spunte non conta', function () use ($filters) {
        $filter = runFilter($filters, ['tbl__brand_id' => '5', 'tbl__status' => [''], 'tbl__tags' => ['', 'x']]);

        return $filter->query === "`brand_id` = '5' AND (`tags` LIKE '%\"x\"%') "
            && str_contains($filter->button, '>2 <span class="visually-hidden">');
    });

    check('nessun filtro usato: niente query, niente contatore', function () use ($filters) {
        $filter = runFilter($filters, ['tbl__status' => ['']]);

        return empty($filter->query) && !str_contains($filter->button, 'badge');
    });

    check('i parametri da conservare escono escapati', function () use ($filters) {
        $filter = runFilter($filters, [], ['redirect' => '"><script>alert(1)</script>']);

        return str_contains($filter->filter, 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"')
            && !str_contains($filter->filter, '<script>');
    });

    summary();
}
