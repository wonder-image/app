<?php
/** php tests/Backend/Support/SearchDescriptorValidationTest.php */
declare(strict_types=1);

// Il renderer chiede al DB se una colonna esiste (sqlColumnExists, in
// app/function/sql.php, non caricato qui): nel suo namespace lo sostituisce
// uno schema finto, che PHP trova prima della funzione globale.
namespace Wonder\Backend\Support {
    const FAKE_SCHEMA = [
        'gst_movement' => ['id', 'code', 'product_id'],
        'gst_product' => ['id', 'sku', 'ean', 'name', 'product_model_id'],
        'gst_product_model' => ['id', 'name'],
    ];

    function sqlColumnExists($table, $column): bool
    {
        return in_array($column, FAKE_SCHEMA[$table] ?? [], true);
    }
}

namespace {
    require __DIR__ . '/../../../vendor/autoload.php';
    require __DIR__ . '/../../harness.php';

    use Wonder\App\Model;
    use Wonder\App\Resource;
    use Wonder\App\ResourceSchema\TableLayoutSchema;
    use Wonder\Backend\Support\ResourceTableRenderer;

    final class MovementTestModel extends Model
    {
        public static string $table = 'gst_movement';

        public static function tableSchema(): array { return []; }
        public static function dataSchema(): array { return []; }
    }

    final class MovementTestResource extends Resource
    {
        public static string $model = MovementTestModel::class;
        public static array $fixtureSearchFields = [];

        public static function tableLayout(): TableLayoutSchema
        {
            return TableLayoutSchema::for(static::class)->searchFields(static::$fixtureSearchFields);
        }
    }

    $model = ['table' => 'gst_product_model', 'local_key' => 'product_model_id', 'foreign_key' => 'id', 'columns' => ['name']];
    $version = ['table' => 'gst_product', 'local_key' => 'product_id', 'foreign_key' => 'id', 'columns' => ['sku', 'ean', 'name'], 'relations' => [$model]];

    $validate = static fn (array $fields): array => ResourceTableRenderer::validSearchDescriptors(
        $fields,
        'gst_movement',
        'Wonder\Backend\Support\sqlColumnExists'
    );

    check('le stringhe passano senza controlli', function () use ($validate) {
        return $validate(['code', 'qualunque']) === ['code', 'qualunque'];
    });

    check('un descrittore annidato valido passa intero', function () use ($validate, $version) {
        return $validate([$version]) === [$version];
    });

    check('le colonne inesistenti spariscono, anche nei livelli annidati', function () use ($validate, $model, $version) {
        $version['columns'] = ['sku', 'nope'];
        $version['relations'][0]['columns'] = ['name', 'nope'];
        $out = $validate([$version]);

        return $out[0]['columns'] === ['sku'] && $out[0]['relations'][0]['columns'] === ['name'];
    });

    check('foreign_key mancante diventa id', function () use ($validate) {
        $out = $validate([['table' => 'gst_product', 'local_key' => 'product_id', 'columns' => ['sku']]]);

        return $out[0]['foreign_key'] === 'id' && $out[0]['columns'] === ['sku'];
    });

    check('local_key mancante o assente nel padre, foreign_key inesistente: scartato', function () use ($validate) {
        return $validate([
            ['table' => 'gst_product', 'foreign_key' => 'id', 'columns' => ['sku']],
            ['table' => 'gst_product', 'local_key' => 'nope', 'foreign_key' => 'id', 'columns' => ['sku']],
            ['table' => 'gst_product', 'local_key' => 'product_id', 'foreign_key' => 'nope', 'columns' => ['sku']],
        ]) === [];
    });

    check('il local_key annidato si controlla sulla tabella del padre', function () use ($validate, $model, $version) {
        $wrongParent = $version;
        $wrongParent['relations'] = [['table' => 'gst_product_model', 'local_key' => 'product_id', 'foreign_key' => 'id', 'columns' => ['name']]];

        return $validate([$model]) === []
            && !array_key_exists('relations', $validate([$wrongParent])[0]);
    });

    check('figli non validi tolti, relations sparisce se non ne resta nessuno', function () use ($validate, $version) {
        $version['relations'] = [['table' => 'nope'], 'x'];
        $out = $validate([$version]);

        return !array_key_exists('relations', $out[0]) && $out[0]['columns'] === ['sku', 'ean', 'name'];
    });

    check('solo relations: resta se un figlio è valido, sparisce se nessuno lo è', function () use ($validate, $model) {
        $only = ['table' => 'gst_product', 'local_key' => 'product_id', 'foreign_key' => 'id', 'relations' => [$model]];
        $out = $validate([$only]);
        $only['relations'] = [['table' => 'nope']];

        return $out[0]['columns'] === [] && $out[0]['relations'] === [$model] && $validate([$only]) === [];
    });

    check('voci che non sono descrittori scartate', function () use ($validate) {
        return $validate([['columns' => ['sku']], ['table' => ['x']], 42, null]) === [];
    });

    check('searchFields() accetta un descrittore con sole relations', function () use ($model) {
        $only = ['table' => 'gst_product', 'local_key' => 'product_id', 'relations' => [$model]];
        $fields = TableLayoutSchema::for(MovementTestResource::class)
            ->searchFields(['code', $only, ['table' => 'gst_product']])
            ->all()['search_fields'];

        return $fields === ['code', $only];
    });

    check('il renderer porta alla ricerca un descrittore con sole relations', function () use ($model) {
        MovementTestResource::$fixtureSearchFields = ['code', ['table' => 'gst_product', 'local_key' => 'product_id', 'relations' => [$model]]];

        $renderer = Closure::bind(
            static fn () => new ResourceTableRenderer(MovementTestResource::class),
            null,
            ResourceTableRenderer::class
        )();
        $fields = (new ReflectionMethod(ResourceTableRenderer::class, 'tableLayoutSearchFields'))->invoke($renderer);

        return count($fields) === 2
            && $fields[0] === 'code'
            && $fields[1]['relations'] === [$model]
            && $fields[1]['foreign_key'] === 'id';
    });

    summary();
}
