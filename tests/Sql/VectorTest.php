<?php
/** php tests/Sql/VectorTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Sql\CreateTable;
use Wonder\Sql\Query;
use Wonder\Sql\TableSchema as Column;
use Wonder\Sql\Vector;

$query = Query::withEscaper(static fn (string $value): string => addslashes($value));

$rifiuta = static function (callable $callback): bool {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
};

$colonna = static function (string $name, array $options, bool|string $action = false): string {
    $reflection = new ReflectionClass(CreateTable::class);
    $method = $reflection->getMethod('TableColumn');
    $method->setAccessible(true);

    return trim(preg_replace('/\s+/', ' ', $method->invoke($reflection->newInstanceWithoutConstructor(), $name, $options, $action)));
};

check('testo: solo numeri in notazione decimale, senza esponente', function () {
    return (new Vector([1, 0.5, -2500, '0.25', 1.0e-5]))->toText() === '[1,0.5,-2500,0.25,0.00001]'
        && (new Vector([0.0, -0.0]))->dimensions() === 2;
});

check('valore SQL: VEC_FromText senza passare dall\'escape', function () {
    return (new Vector([0.1, 0.2]))->toSql() === "VEC_FromText('[0.1,0.2]')";
});

check('valori non validi rifiutati', function () use ($rifiuta) {
    return $rifiuta(fn () => new Vector([]))
        && $rifiuta(fn () => new Vector([1, 'x']))
        && $rifiuta(fn () => new Vector([1, "0'); DROP TABLE user; --"]))
        && $rifiuta(fn () => new Vector([INF]))
        && $rifiuta(fn () => new Vector([NAN]))
        && $rifiuta(fn () => new Vector([[1]]))
        && $rifiuta(fn () => new Vector(array_fill(0, Vector::MAX_DIMENSIONS + 1, 0)));
});

check('lettura dal binario della colonna (float32 little-endian)', function () use ($rifiuta) {
    $vector = Vector::fromBinary(pack('g*', 1.0, 0.5, -2.0));

    return $vector->toArray() === [1.0, 0.5, -2.0]
        && $rifiuta(fn () => Vector::fromBinary(''))
        && $rifiuta(fn () => Vector::fromBinary('abc'));
});

check('INSERT e UPDATE: il vettore non viene quotato come stringa', function () use ($query) {
    $values = ['title' => 'a', 'embedding' => new Vector([1, 0])];

    return $query->Values($values) === "(`title`, `embedding`) VALUES ('a', VEC_FromText('[1,0]'))"
        && $query->Values($values, true) === "`title` = 'a', `embedding` = VEC_FromText('[1,0]')";
});

check('bind(): vettore come parametro', function () use ($query) {
    return $query->bind('ORDER BY VEC_DISTANCE_COSINE(`e`, :v)', ['v' => new Vector([0, 1])])
        === "ORDER BY VEC_DISTANCE_COSINE(`e`, VEC_FromText('[0,1]'))";
});

check('distanza: metrica, identificatore e uso in ORDER BY', function () use ($rifiuta) {
    $cosine = Vector::distance('embedding', [1, 0]);
    $euclidean = Vector::distance('doc_chunks.embedding', new Vector([1, 0]), 'Euclidean');

    return $cosine === "VEC_DISTANCE_COSINE(`embedding`, VEC_FromText('[1,0]'))"
        && $euclidean === "VEC_DISTANCE_EUCLIDEAN(`doc_chunks`.`embedding`, VEC_FromText('[1,0]'))"
        && Vector::distance('a`b', [1]) === "VEC_DISTANCE_COSINE(`a``b`, VEC_FromText('[1]'))"
        && Query::orderClause($cosine, 'ASC') === " ORDER BY $cosine ASC"
        && $rifiuta(fn () => Vector::distance('embedding', [1], 'manhattan'));
});

check('versione del server: solo MariaDB 11.7+', function () {
    return Vector::serverSupports('11.8.9-MariaDB-ubu2404')
        && Vector::serverSupports('11.7.2-MariaDB')
        && Vector::serverSupports('5.5.5-12.0.2-MariaDB-log')
        && !Vector::serverSupports('11.4.5-MariaDB')
        && !Vector::serverSupports('5.5.5-10.11.6-MariaDB-0+deb12u1')
        && !Vector::serverSupports('8.0.36')
        && !Vector::serverSupports('9.1.0')
        && !Vector::serverSupports('');
});

check('TableSchema: colonna VECTOR NOT NULL e indice vettoriale', function () use ($rifiuta) {
    $column = Column::key('embedding')->vector(1536)->schema;
    $index = Column::key('vec_embedding')->vectorIndex('embedding', 'COSINE', 8)->schema;

    return $column['type'] === 'VECTOR' && $column['length'] === 1536 && $column['null'] === false
        && $index['vector_index'] === ['column' => 'embedding', 'distance' => 'cosine', 'm' => 8]
        && $rifiuta(fn () => Column::key('e')->vector(0))
        && $rifiuta(fn () => Column::key('e')->vector(Vector::MAX_DIMENSIONS + 1))
        && $rifiuta(fn () => Column::key('i')->vectorIndex('e', 'manhattan'))
        && $rifiuta(fn () => Column::key('i')->vectorIndex('e', 'cosine', 2));
});

check('DDL: colonna e indice in CREATE e in ALTER', function () use ($colonna) {
    $column = Column::key('embedding')->vector(3)->schema;
    $index = Column::key('vec_embedding')->vectorIndex('embedding', 'cosine', 8)->schema;
    $default = Column::key('vec_embedding')->vectorIndex('embedding', 'euclidean')->schema;

    return $colonna('embedding', $column) === '`embedding` VECTOR(3) NOT NULL'
        && $colonna('embedding', $column, 'modify') === 'MODIFY COLUMN `embedding` VECTOR(3) NOT NULL'
        && $colonna('vec_embedding', $index) === 'VECTOR INDEX `vec_embedding` (`embedding`) M=8 DISTANCE=cosine'
        && $colonna('vec_embedding', $index, 'add') === 'ADD VECTOR INDEX `vec_embedding` (`embedding`) M=8 DISTANCE=cosine'
        && $colonna('vec_embedding', $default) === 'VECTOR INDEX `vec_embedding` (`embedding`) DISTANCE=euclidean';
});

summary();
