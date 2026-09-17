<?php
/** php tests/Sql/SelectForUpdateTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Sql\Query;

check('SelectForUpdate fuori da una transazione: eccezione prima di interrogare', function () {
    $mysqli = mysqli_init();
    $query = new Query($mysqli);

    try {
        $query->SelectForUpdate('document_sequences', ['id' => 1], 1);
        return false;
    } catch (RuntimeException $exception) {
        return str_contains($exception->getMessage(), 'transazione attiva');
    }
});

check('Select resta disponibile con la stessa firma', function () {
    $method = new ReflectionMethod(Query::class, 'Select');
    $forUpdate = new ReflectionMethod(Query::class, 'SelectForUpdate');
    $names = fn (ReflectionMethod $m) => array_map(fn ($p) => $p->getName(), $m->getParameters());
    return $method->isPublic() && $forUpdate->isPublic() && $names($method) === $names($forUpdate);
});

summary();
