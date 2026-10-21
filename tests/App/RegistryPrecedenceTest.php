<?php
/**
 * php tests/App/RegistryPrecedenceTest.php
 *
 * Precedenza quando più classi mappano la stessa tabella:
 * - ResourceRegistry::resolveByTable(): prima non-fallback nell'ordine di all(), altrimenti la prima fallback;
 * - ModelRegistry::all(): vince il FQCN ultimo in ordine alfabetico, qualunque sia la cartella.
 * Nessuna API pubblica di registrazione: ogni caso usa un ROOT temporaneo con
 * `custom/config/resource/resources.php` (ordine scelto) o file Model nelle cartelle del sito, poi reset().
 */
declare(strict_types=1);

namespace Wonder\Tests\RegistryPrecedence;

use Wonder\App\Model;
use Wonder\App\ModelRegistry;
use Wonder\App\Resource;
use Wonder\App\ResourceRegistry;

require __DIR__ . '/../harness.php';
require __DIR__ . '/../../vendor/autoload.php';

const TABLE = 'zz_test_precedence';

final class PrecedenceModel extends Model
{
    public static string $table = TABLE;
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class FallbackAResource extends Resource
{
    public static string $model = PrecedenceModel::class;
    public static string $path = 'zz-precedence-fallback-a';
    public static function isTableFallback(): bool { return true; }
}

final class FallbackDResource extends Resource
{
    public static string $model = PrecedenceModel::class;
    public static string $path = 'zz-precedence-fallback-d';
    public static function isTableFallback(): bool { return true; }
}

final class PanelBResource extends Resource
{
    public static string $model = PrecedenceModel::class;
    public static string $path = 'zz-precedence-panel-b';
}

final class PanelCResource extends Resource
{
    public static string $model = PrecedenceModel::class;
    public static string $path = 'zz-precedence-panel-c';
}

$previousRoot = $GLOBALS['ROOT'] ?? null;
$base = sys_get_temp_dir().'/wi-registry-precedence-'.bin2hex(random_bytes(4));
$roots = 0;

$cleanup = static function (string $dir) use (&$cleanup): void {
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            is_dir($dir.'/'.$entry) ? $cleanup($dir.'/'.$entry) : unlink($dir.'/'.$entry);
        }
    }
    rmdir($dir);
};
register_shutdown_function(static fn () => is_dir($base) && $cleanup($base));

/** Nuovo ROOT con i file dati (percorso relativo => contenuto), registri azzerati. */
$root = static function (array $files) use ($base, &$roots): string {
    $root = $base.'/root'.(++$roots);
    foreach ($files as $path => $contents) {
        @mkdir(dirname($root.'/'.$path), 0777, true);
        file_put_contents($root.'/'.$path, $contents);
    }
    @mkdir($root, 0777, true);
    $GLOBALS['ROOT'] = $root;
    ResourceRegistry::reset();
    ModelRegistry::reset();
    return $root;
};

$configured = static fn (array $classes): array => [
    'custom/config/resource/resources.php' => '<?php return '.var_export($classes, true).';',
];

$resolve = static function (array $classes) use ($root, $configured): ?string {
    $root($configured($classes));
    return ResourceRegistry::resolveByTable(TABLE);
};

check('resource: fallback poi non-fallback → vince la non-fallback', fn () =>
    $resolve([FallbackAResource::class, PanelBResource::class]) === PanelBResource::class);

check('resource: non-fallback poi fallback → vince la non-fallback', fn () =>
    $resolve([PanelBResource::class, FallbackAResource::class]) === PanelBResource::class);

check('resource: solo una fallback → la fallback', fn () =>
    $resolve([FallbackAResource::class]) === FallbackAResource::class);

check('resource: solo fallback → la prima inserita', fn () =>
    $resolve([FallbackDResource::class, FallbackAResource::class]) === FallbackDResource::class
    && $resolve([FallbackAResource::class, FallbackDResource::class]) === FallbackAResource::class);

check('resource: due non-fallback → la prima inserita', fn () =>
    $resolve([PanelBResource::class, PanelCResource::class]) === PanelBResource::class
    && $resolve([PanelCResource::class, PanelBResource::class]) === PanelCResource::class);

check('resource: fallback e due non-fallback in mezzo → la prima non-fallback', fn () =>
    $resolve([FallbackAResource::class, PanelCResource::class, FallbackDResource::class, PanelBResource::class]) === PanelCResource::class);

check('resource: le scoperte nelle cartelle precedono le configurate', function () use ($root, $configured) {
    $root($configured([PanelCResource::class]) + [
        'custom/class/Resources/ZzDiscoveredPanelResource.php' => '<?php
namespace Wonder\Tests\RegistryPrecedence\Discovered;
final class ZzDiscoveredPanelResource extends \Wonder\App\Resource
{
    public static string $model = \Wonder\Tests\RegistryPrecedence\PrecedenceModel::class;
    public static string $path = "zz-precedence-discovered";
}
',
    ]);

    return ResourceRegistry::resolveByTable(TABLE) === 'Wonder\\Tests\\RegistryPrecedence\\Discovered\\ZzDiscoveredPanelResource';
});

check('resource: tabella senza Resource → null', function () use ($root) {
    $root([]);
    return ResourceRegistry::resolveByTable('zz_test_precedence_none') === null;
});

$modelFile = static fn (string $namespace, string $class, string $table): string => "<?php\nnamespace {$namespace};\nfinal class {$class} extends \\Wonder\\App\\Model\n{\n    public static string \$table = '{$table}';\n    public static function tableSchema(): array { return []; }\n    public static function dataSchema(): array { return []; }\n}\n";

check('model: stessa tabella → vince il FQCN ultimo in ordine alfabetico (Zz in app/Models, Aa in custom)', function () use ($root, $modelFile) {
    $root([
        'app/Models/Model1.php' => $modelFile('Zz\\Late', 'Model1', 'zz_test_precedence_m1'),
        'custom/class/Models/Model1.php' => $modelFile('Aa\\Early', 'Model1', 'zz_test_precedence_m1'),
    ]);
    return (ModelRegistry::all()['zz_test_precedence_m1'] ?? null) === 'Zz\\Late\\Model1';
});

check('model: cartelle invertite (Aa in app/Models, Zz in custom) → vince ancora Zz', function () use ($root, $modelFile) {
    $root([
        'app/Models/Model2.php' => $modelFile('Aa\\Early', 'Model2', 'zz_test_precedence_m2'),
        'custom/class/Models/Model2.php' => $modelFile('Zz\\Late', 'Model2', 'zz_test_precedence_m2'),
    ]);
    return (ModelRegistry::all()['zz_test_precedence_m2'] ?? null) === 'Zz\\Late\\Model2';
});

check('model: nella stessa cartella decide il FQCN, non il nome del file', function () use ($root, $modelFile) {
    $root([
        'app/Models/A.php' => $modelFile('Zz\\Late', 'Model3', 'zz_test_precedence_m3'),
        'app/Models/B.php' => $modelFile('Aa\\Early', 'Model3', 'zz_test_precedence_m3'),
    ]);
    return (ModelRegistry::all()['zz_test_precedence_m3'] ?? null) === 'Zz\\Late\\Model3';
});

check('model: un solo Model sulla tabella → quello', function () use ($root, $modelFile) {
    $root(['custom/class/Models/Model4.php' => $modelFile('Aa\\Early', 'Model4', 'zz_test_precedence_m4')]);
    return (ModelRegistry::all()['zz_test_precedence_m4'] ?? null) === 'Aa\\Early\\Model4';
});

// Ripristina ROOT e registri per chi include questo file.
if ($previousRoot === null) { unset($GLOBALS['ROOT']); } else { $GLOBALS['ROOT'] = $previousRoot; }
ResourceRegistry::reset();
ModelRegistry::reset();

summary();
