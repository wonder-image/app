<?php
/** php tests/Backend/Support/ResourcePageActionsDropdownTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Support\PageActionNormalizer;

$fail = 0;
function eq(string $label, $got, $expected): void
{
    global $fail;
    if ($got !== $expected) {
        $fail++;
        echo "FAIL: $label\n  expected: " . var_export($expected, true) . "\n  got: " . var_export($got, true) . "\n";
    } else {
        echo "ok: $label\n";
    }
}

$actions = PageActionNormalizer::normalize([
    // bottone piatto (retrocompatibile)
    ['label' => 'Modifica', 'href' => '/edit', 'icon' => 'bi bi-pencil'],

    // bottone con dropdown
    [
        'label' => 'Altre azioni',
        'icon' => 'bi bi-three-dots',
        'class' => 'btn-outline-secondary',
        'align' => 'end',
        'items' => [
            ['label' => 'Duplica', 'href' => '/dup', 'icon' => 'bi bi-files'],
            ['divider' => true],
            ['header' => 'Pericolo'],
            ['label' => 'Elimina', 'onclick' => 'del()', 'class' => 'text-danger'],
        ],
    ],

    // dropdown vuoto -> deve degradare a bottone piatto
    ['label' => 'Vuoto', 'href' => '/x', 'items' => []],

    // descriptor senza label -> scartato
    ['href' => '/nolabel'],

    // scarti vari
    'stringa',
    ['items' => [['label' => 'orfano']]], // dropdown senza label toggle -> scartato
]);

// tre descriptor validi
eq('count', count($actions), 3);

// [0] bottone piatto invariato, con items sempre presente e vuoto
$flat = $actions[0];
eq('flat label', $flat['label'] ?? null, 'Modifica');
eq('flat href', $flat['href'] ?? null, '/edit');
eq('flat class default', $flat['class'] ?? null, 'btn-primary');
eq('flat icon', $flat['icon'] ?? null, 'bi bi-pencil');
eq('flat target', $flat['target'] ?? null, '');
eq('flat onclick', $flat['onclick'] ?? null, '');
eq('flat items empty', $flat['items'] ?? null, []);

// [1] dropdown
$dd = $actions[1];
eq('dd label', $dd['label'] ?? null, 'Altre azioni');
eq('dd class', $dd['class'] ?? null, 'btn-outline-secondary');
eq('dd icon', $dd['icon'] ?? null, 'bi bi-three-dots');
eq('dd align', $dd['align'] ?? null, 'end');
eq('dd items count', count($dd['items'] ?? []), 4);

$items = $dd['items'];
eq('item0 kind', $items[0]['kind'] ?? null, 'item');
eq('item0 label', $items[0]['label'] ?? null, 'Duplica');
eq('item0 href', $items[0]['href'] ?? null, '/dup');
eq('item0 icon', $items[0]['icon'] ?? null, 'bi bi-files');
eq('item0 onclick', $items[0]['onclick'] ?? null, '');

eq('item1 divider', $items[1]['kind'] ?? null, 'divider');

eq('item2 header kind', $items[2]['kind'] ?? null, 'header');
eq('item2 header label', $items[2]['label'] ?? null, 'Pericolo');

eq('item3 kind', $items[3]['kind'] ?? null, 'item');
eq('item3 label', $items[3]['label'] ?? null, 'Elimina');
eq('item3 onclick', $items[3]['onclick'] ?? null, 'del()');
eq('item3 class', $items[3]['class'] ?? null, 'text-danger');
eq('item3 href empty', $items[3]['href'] ?? null, '');

// [2] dropdown con items vuoto -> degrada a bottone piatto
$empty = $actions[2];
eq('empty is flat', $empty['label'] ?? null, 'Vuoto');
eq('empty items empty', $empty['items'] ?? null, []);
eq('empty has no align', array_key_exists('align', $empty), false);

// align default = 'end' quando non specificato ma con items
$withDefaultAlign = PageActionNormalizer::normalize([
    ['label' => 'Menu', 'items' => [['label' => 'A', 'href' => '/a']]],
]);
eq('default align end', $withDefaultAlign[0]['align'] ?? null, 'end');
eq('default align items count', count($withDefaultAlign[0]['items'] ?? []), 1);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
