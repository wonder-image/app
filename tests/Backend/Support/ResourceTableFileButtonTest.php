<?php
/**
 * Collegamento lista -> bottone «File»: id della finestra e formato preselezionato.
 *
 *   php tests/Backend/Support/ResourceTableFileButtonTest.php
 */
declare(strict_types=1);

ob_start(); // l'output dei check non deve bloccare session_start()

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

if (!function_exists('__t')) {
    function __t(string $key): string
    {
        return $key;
    }
}

if (!function_exists('__r')) {
    function __r(string $name, array $parameters = []): string
    {
        return '/'.$name;
    }
}

ini_set('session.use_cookies', '0');
ini_set('session.save_path', sys_get_temp_dir());
session_id('t');
@session_start();
$_SESSION = [];

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Support\ResourceTableRenderer;
use Wonder\Backend\Table\Table;

final class FileButtonModel extends Model
{
    public static string $table = 'file_button_test';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class FileButtonResource extends Resource
{
    public static string $model = FileButtonModel::class;
}

/** Html del bottone, senza DB: renderer e tabella si costruiscono a mano. */
$buttonHtml = static function (TableLayoutSchema $layout): string {
    $ref = new ReflectionClass(ResourceTableRenderer::class);
    $renderer = $ref->newInstanceWithoutConstructor();

    foreach (['resourceClass' => FileButtonResource::class, 'slug' => 'products', 'tableLayoutSchema' => $layout->all()] as $name => $value) {
        $prop = $ref->getProperty($name);
        $prop->setValue($renderer, $value);
    }

    $table = (new ReflectionClass(Table::class))->newInstanceWithoutConstructor();
    $ref->getMethod('applyButtonData')->invoke($renderer, $table);

    return (string) (new ReflectionProperty(Table::class, 'buttonDataHtml'))->getValue($table);
};

$layout = static fn () => TableLayoutSchema::for(FileButtonResource::class)
    ->download(['csv' => 'CSV', 'xlsx' => 'Excel']);

check('con download() compare la finestra wi-file-<slug>', function () use ($buttonHtml, $layout) {
    $html = $buttonHtml($layout());

    return str_contains($html, 'id="wi-file-products"') && str_contains($html, '#wi-file-products');
});

check('senza ultima scelta il formato di default è il primo', function () use ($buttonHtml, $layout) {
    $_SESSION = [];
    $html = $buttonHtml($layout());

    return (bool) preg_match('/value="csv"[^>]*checked/', $html);
});

check('l\'ultima scelta in sessione preseleziona il formato', function () use ($buttonHtml, $layout) {
    $_SESSION['wi_resource_export']['products'] = ['format' => 'xlsx', 'layout' => null];
    $html = $buttonHtml($layout());
    $_SESSION = [];

    return (bool) preg_match('/value="xlsx"[^>]*checked/', $html)
        && !preg_match('/value="csv"[^>]*checked/', $html);
});

check('slug vuoto: id di ripiego wi-file-resource', function () {
    $built = Wonder\Backend\Support\ResourceFileModal::build('', ['action' => '/x/', 'label' => '', 'formats' => ['csv' => 'CSV'], 'profiles' => [], 'layout_formats' => null], null);

    return str_contains($built['modal']->render('bootstrap'), 'id="wi-file-resource"');
});

check('remember: una sessione corrotta torna un array', function () {
    $_SESSION['wi_resource_export'] = 'rotto';
    Wonder\Backend\Support\ResourceDownloadController::remember('products', 'csv', null);
    $ok = $_SESSION['wi_resource_export'] === ['products' => ['format' => 'csv', 'layout' => null]];
    $_SESSION = [];

    return $ok;
});

check('feeds() dello schema arriva nella scheda Feed prodotti, con ?format=', function () use ($buttonHtml) {
    $html = $buttonHtml(TableLayoutSchema::for(FileButtonResource::class)
        ->download(['xlsx' => 'Excel', 'meta' => 'Catalogo Meta'])
        ->feeds([['format' => 'meta', 'label' => 'Catalogo Meta', 'url' => 'https://x.it/f.csv'], ['format' => 'google']]));

    return str_contains($html, 'Feed prodotti') && str_contains($html, 'format=meta')
        && !str_contains($html, 'format=google') && !str_contains($html, 'value="meta"');
});

summary();
