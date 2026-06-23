<?php // tests/View/ModuleComponentTest.php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/helper.php';

use Wonder\App\LegacyGlobals;
use Wonder\View\ComponentNamespaceRegistry;
use Wonder\View\View;

// --- fixtures su filesystem temporaneo ---
$tmp = sys_get_temp_dir().'/wi_view_'.uniqid();
$moduleBase = $tmp.'/module/view/components';   // dir base modulo
$consumerRoot = $tmp.'/consumer';               // ROOT consumer
@mkdir($moduleBase.'/sub', 0777, true);
@mkdir($consumerRoot.'/custom/view/components/demo', 0777, true);

// componente modulo che stampa uno slot
file_put_contents($moduleBase.'/withslot.php', '<?php slot("body", "DEFAULT"); ?>');

LegacyGlobals::share(['ROOT' => $consumerRoot, 'ROOT_APP' => $tmp.'/app']);
ComponentNamespaceRegistry::reset();
ComponentNamespaceRegistry::register('demo', $moduleBase);

check('slot default emitted', function () {
    return View::component('demo/withslot') === 'DEFAULT';
});

check('slot string override emitted', function () {
    return View::component('demo/withslot', ['slots' => ['body' => 'HELLO']]) === 'HELLO';
});

check('slot callable invoked', function () {
    $out = View::component('demo/withslot', ['slots' => ['body' => fn () => 'CB']]);
    return $out === 'CB';
});

summary();
