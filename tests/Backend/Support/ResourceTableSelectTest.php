<?php
/** php tests/Backend/Support/ResourceTableSelectTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Support\ResourceTableRenderer;

final class SelectTestModel extends Model
{
    public static string $table = 'select_test';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class SelectTestResource extends Resource
{
    public static string $model = SelectTestModel::class;
}

check('select() è vuoto per default', function () {
    return TableLayoutSchema::for(SelectTestResource::class)->all()['select'] === '';
});

check('select() toglie gli spazi e una seconda chiamata sostituisce la prima', function () {
    $schema = TableLayoutSchema::for(SelectTestResource::class)
        ->select('COUNT(*) AS n')
        ->select("  SUM(qty) AS qty \n")
        ->all();

    return $schema['select'] === 'SUM(qty) AS qty';
});

check('selectList: senza colonne calcolate resta SELECT *', function () {
    return ResourceTableRenderer::selectList('gst_product', '  ') === '';
});

check('selectList: tabella.* davanti alle colonne calcolate', function () {
    return ResourceTableRenderer::selectList('gst_product', ' COUNT(*) AS n ') === '`gst_product`.*, COUNT(*) AS n';
});

check('selectList: backtick nel nome della tabella escapato', function () {
    return ResourceTableRenderer::selectList('a`b', 'x AS y') === '`a``b`.*, x AS y';
});

check('selectAliases: alias di una subquery e alias fra backtick', function () {
    $select = '(SELECT SUM(s.qty) FROM gst_stock s WHERE s.product_id = gst_product.id) AS qty_total, '
        . '(SELECT m.name FROM gst_product_model m WHERE m.id = gst_product.product_model_id) as `model_name`';

    return ResourceTableRenderer::selectAliases($select) === ['qty_total', 'model_name'];
});

check('selectAliases: CAST(... AS SIGNED) aggiunge SIGNED, innocuo', function () {
    return ResourceTableRenderer::selectAliases('CAST(x AS SIGNED) AS qty') === ['SIGNED', 'qty'];
});

check('withoutAliases: toglie gli alias senza badare alle maiuscole e tiene i descrittori', function () {
    $brand = ['table' => 'gst_brand', 'local_key' => 'brand_id', 'foreign_key' => 'id', 'columns' => ['name']];
    $fields = ['sku', 'QTY_TOTAL', $brand, 'name', 'model_name'];

    return ResourceTableRenderer::withoutAliases($fields, ['qty_total', 'model_name']) === ['sku', $brand, 'name'];
});

summary();
