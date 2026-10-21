<?php
declare(strict_types=1);

require __DIR__ . '/../harness.php';

$root = dirname(__DIR__, 2);

check('list.php non usa più $USER_FILTER', function () use ($root) {
    foreach (token_get_all((string) file_get_contents($root . '/app/html/backend/list.php')) as $token) {
        if (is_array($token) && $token[0] === T_VARIABLE && $token[1] === '$USER_FILTER') {
            return false;
        }
    }
    return true;
});

check('list.php valido e senza la condizione authority/area', function () use ($root) {
    $source = (string) file_get_contents($root . '/app/html/backend/list.php');
    return !str_contains($source, "`authority` LIKE")
        && !str_contains($source, "`area` LIKE");
});

check('filtri legacy di filter.php marcati @deprecated', function () use ($root) {
    require_once $root . '/app/function/backend/filter.php';
    foreach (['filter', 'filterOrder', 'filterDate', 'filterCustom', 'createFilterCustom'] as $name) {
        $doc = (new ReflectionFunction($name))->getDocComment();
        if (!is_string($doc) || !str_contains($doc, '@deprecated')) {
            echo "    manca @deprecated su {$name}()\n";
            return false;
        }
    }
    return true;
});

summary();
