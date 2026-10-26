<?php
/**
 * La modale legacy #modal e il suo helper PHP modal() non esistono più: le
 * conferme del backend passano da data-wi-confirm / wi.confirm della lib.
 *
 *   php tests/Backend/LegacyModalRemovedTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../harness.php';

echo "LegacyModalRemoved\n";

$root = dirname(__DIR__, 2);

check('helper PHP modal() rimosso e non più caricato', function () use ($root) {
    return !file_exists($root.'/app/function/backend/modal.php')
        && !str_contains((string) file_get_contents($root.'/app/function/backend/function.php'), 'modal.php');
});

check('header del backend senza modal() né markup #modal', function () use ($root) {
    $header = (string) file_get_contents($root.'/app/view/components/backend/layout/header.php');

    return !str_contains($header, 'modal()')
        && preg_match('/id=[\'"]modal[\'"]/', $header) === 0;
});

check('nessun onclick del core chiama la modal() JS legacy', function () use ($root) {
    foreach (['class/Backend/Table/Field.php', 'app/function/backend/plugin.php'] as $file) {
        if (preg_match('/onclick=\\\\?"modal\(/', (string) file_get_contents($root.'/'.$file)) === 1) {
            echo "    {$file}\n";
            return false;
        }
    }

    return true;
});

summary();
