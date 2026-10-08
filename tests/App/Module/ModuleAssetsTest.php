<?php
/** php tests/App/Module/ModuleAssetsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Module\Assets;
use Wonder\App\Module\Manifest;

$site = sys_get_temp_dir().'/wi-module-assets-'.getmypid();
$module = $site.'/modules/negozio';
@mkdir($module.'/resources/assets/js', 0777, true);
file_put_contents($module.'/resources/assets/js/checkout.js', '// v1');
touch($module.'/resources/assets/js/checkout.js', 1700000000);

$manifest = Manifest::fromArray($module, $module.'/module.json', ['slug' => 'negozio', 'version' => '0.1.0'], 'modules');
$url = static fn (): string => Assets::urlFor($manifest, 'js/checkout.js', $site, 'https://sito.test', 'v1');

check('il file del modulo porta la versione del modulo nell\'URL', fn () =>
    str_starts_with($url(), 'https://sito.test/modules/negozio/resources/assets/js/checkout.js?v=0.1.0')
);

check('un file del modulo cambiato senza cambiare versione ha un URL nuovo', function () use ($url, $module): bool {
    $prima = $url();
    touch($module.'/resources/assets/js/checkout.js', 1700000100);
    clearstatcache();

    return $url() !== $prima;
});

array_map('unlink', glob($module.'/resources/assets/js/*') ?: []);

summary();
