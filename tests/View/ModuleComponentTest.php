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

// componente modulo "card" + override consumer dello stesso nome
file_put_contents($moduleBase.'/card.php', '<?php echo "MODULE"; ?>');
file_put_contents($consumerRoot.'/custom/view/components/demo/card.php', '<?php echo "CONSUMER"; ?>');
file_put_contents($moduleBase.'/sub/leaf.php', '<?php echo "LEAF"; ?>');

check('module file resolved when no override', function () {
    return View::component('demo/withslot', ['slots' => ['body' => 'X']]) === 'X';
});

check('consumer override wins over module', function () {
    return View::component('demo/card') === 'CONSUMER';
});

check('nested path resolves', function () {
    return View::component('demo/sub/leaf') === 'LEAF';
});

check('dotted syntax also works', function () {
    return View::component('demo.sub.leaf') === 'LEAF';
});

check('unregistered prefix falls back to legacy (throws when missing)', function () {
    try {
        View::component('totallyunknown/x');
        return false;
    } catch (\RuntimeException $e) {
        return str_contains($e->getMessage(), 'Component non trovato');
    }
});

check('traversal in rest is rejected', function () {
    try {
        View::component('demo/../../../etc/passwd');
        return false;
    } catch (\RuntimeException $e) {
        return str_contains($e->getMessage(), 'non valido') || str_contains($e->getMessage(), 'non trovato');
    }
});

summary();
