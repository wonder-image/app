<?php
/** php tests/App/ResourceSchema/TableColumnBadgeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

// helper semantico: descrittore preset, non cliccabile di default
$schema = TableColumn::key('active')->activeBadge()->size('little')->toArray();
eq('activeBadge type', $schema['type'], 'badge');
eq('activeBadge descriptor', $schema['badge'], [
    'preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => false,
]);
eq('no function key', array_key_exists('function', $schema), false);

// clickable forzato dall'argomento
$schema = TableColumn::key('visible')->visibleBadge(true)->toArray();
eq('visibleBadge preset', $schema['badge']['preset'], 'visible');
eq('visibleBadge clickable forced', $schema['badge']['clickable'], true);

$schema = TableColumn::key('evidence')->evidenceBadge()->toArray();
eq('evidenceBadge preset', $schema['badge']['preset'], 'evidence');

// generico: preset null, colonna default = key, fluent on/off/variant/clickable
$schema = TableColumn::key('stato')->booleanBadge()
    ->badgeOn('Aperto', 'bi bi-unlock', 'success', 'Chiudi')
    ->badgeOff('Chiuso', 'bi bi-lock', 'danger', 'Apri')
    ->badgeVariant('badge')
    ->badgeClickable()
    ->toArray();
eq('booleanBadge descriptor', $schema['badge'], [
    'preset' => null, 'column' => 'stato', 'variant' => 'badge', 'clickable' => true,
    'on' => ['text' => 'Aperto', 'icon' => 'bi bi-unlock', 'color' => 'success', 'button' => 'Chiudi'],
    'off' => ['text' => 'Chiuso', 'icon' => 'bi bi-lock', 'color' => 'danger', 'button' => 'Apri'],
]);

// colonna esplicita diversa dalla key
$schema = TableColumn::key('stato_label')->booleanBadge('stato')->toArray();
eq('booleanBadge explicit column', $schema['badge']['column'], 'stato');

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
