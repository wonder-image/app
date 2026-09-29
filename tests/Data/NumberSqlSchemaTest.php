<?php
/** php tests/Data/NumberSqlSchemaTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

final class NumberSqlSchemaFixture extends Model
{
    public static string $table = 'number_sql_schema_fixture';

    public static function tableSchema(): array
    {
        return static::sqlColumnsFromDataSchema();
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('default_number')->number(),
            Field::key('quantity')->number()->decimals(3),
            Field::key('integer_value')->number()->integer(),
            Field::key('wide_quantity')->number()->precision(12)->decimal(3),
        ];
    }
}

check('il default resta DECIMAL(10,2)', function () {
    return Field::key('amount')->number()->sqlSchema() === [
        'type' => 'DECIMAL',
        'length' => '10,2',
    ];
});

check('decimals() determina la scala SQL', function () {
    return Field::key('quantity')->number()->decimals(3)->sqlSchema()['length'] === '10,3';
});

check('integer() e decimal(0) non lasciano una virgola vuota', function () {
    return Field::key('a')->number()->integer()->sqlSchema()['length'] === '10'
        && Field::key('b')->number()->decimal(0)->sqlSchema()['length'] === '10';
});

check('precision() permette di aumentare le cifre totali', function () {
    return Field::key('quantity')->number()->precision(12)->decimals(3)->sqlSchema()['length'] === '12,3';
});

check('la scala non può superare la precisione totale', function () {
    try {
        Field::key('invalid')->number()->precision(2)->decimals(3)->sqlSchema();
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

check('sqlColumnsFromDataSchema conserva precisione e scala', function () {
    $columns = [];

    foreach (NumberSqlSchemaFixture::tableSchema() as $column) {
        $columns[$column->name] = $column->getSchema('length');
    }

    return $columns === [
        'default_number' => '10,2',
        'quantity' => '10,3',
        'integer_value' => '10',
        'wide_quantity' => '12,3',
    ];
});

summary();
