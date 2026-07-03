<?php
/** php tests/Backend/Table/Badge/BooleanBadgeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../../vendor/autoload.php';

use Wonder\Backend\Table\Badge\BooleanBadge;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

// --- preset active, stato on: HTML identico a returnBadge() legacy
eq('active on automaticResize',
    BooleanBadge::active('true')->automaticResize(),
    "<span class='badge text-bg-success'><span class='pc-none'><i class='bi bi-check-circle'></i></span><span class='phone-none'>ABILITATO</span></span>"
);
eq('active off automaticResize',
    BooleanBadge::active('false')->automaticResize(),
    "<span class='badge text-bg-danger'><span class='pc-none'><i class='bi bi-x-circle'></i></span><span class='phone-none'>DISABILITATO</span></span>"
);
eq('active on badge',
    BooleanBadge::active('true')->badge(),
    "<span class='badge text-bg-success'>ABILITATO</span>"
);
eq('active on tooltip',
    BooleanBadge::active('true')->tooltip(),
    "<i class='bi bi-check-circle' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Abilitato'></i>"
);

// --- valore non-'true' è off (comportamento legacy: confronto con 'true')
eq('value 1 is off', BooleanBadge::active('1')->text(), 'Disabilitato');
eq('value bool true is on', BooleanBadge::active(true)->text(), 'Abilitato');

// --- preset visible / evidence
eq('visible off automaticResize',
    BooleanBadge::visible('false')->automaticResize(),
    "<span class='badge text-bg-danger'><span class='pc-none'><i class='bi bi-eye-slash'></i></span><span class='phone-none'>NASCOSTO</span></span>"
);
eq('evidence on automaticResize',
    BooleanBadge::evidence('true')->automaticResize(),
    "<span class='badge text-bg-warning'><span class='pc-none'><i class='bi bi-star-fill'></i></span><span class='phone-none'>IN EVIDENZA</span></span>"
);
eq('evidence off renders empty', BooleanBadge::evidence('false')->automaticResize(), '');
eq('evidence off badge empty', BooleanBadge::evidence('false')->badge(), '');

// --- preset() factory
eq('preset active', BooleanBadge::preset('active', 'true')->text(), 'Abilitato');
eq('preset unknown is null', BooleanBadge::preset('ghost', 'true'), null);

// --- button: identico a returnButton($text, $action) legacy (senza colore)
eq('button on',
    BooleanBadge::active('true')->action("onclick=\"x()\"")->button(),
    "<a class='dropdown-item ' onclick=\"x()\" role='button'>Disabilita</a>"
);
eq('button without action is empty', BooleanBadge::active('true')->button(), '');

// --- clickable: OFF di default, opt-in esplicito
eq('badge not clickable by default',
    BooleanBadge::active('true')->action("onclick=\"x()\"")->badge(),
    "<span class='badge text-bg-success'>ABILITATO</span>"
);
eq('badge clickable when forced',
    BooleanBadge::active('true')->action("onclick=\"x()\"")->clickable()->badge(),
    "<span role='button' onclick=\"x()\"><span class='badge text-bg-success'>ABILITATO</span></span>"
);
eq('clickable without action is no-op',
    BooleanBadge::active('true')->clickable()->badge(),
    "<span class='badge text-bg-success'>ABILITATO</span>"
);

// --- render() dispatch
eq('render automaticResize', BooleanBadge::active('true')->render('automaticResize'), BooleanBadge::active('true')->automaticResize());
eq('render badgeIcon alias', BooleanBadge::active('true')->render('badgeIcon'), BooleanBadge::active('true')->badgeTooltip());
eq('render unknown variant empty', BooleanBadge::active('true')->render('ghost'), '');

// --- generico con on/off custom
$custom = BooleanBadge::make('true')
    ->on('Aperto', 'bi bi-unlock', 'success', 'Chiudi')
    ->off('Chiuso', 'bi bi-lock', 'danger', 'Apri');
eq('custom on badge', $custom->badge(), "<span class='badge text-bg-success'>APERTO</span>");

// --- M1 hardening: escaping HTML dei testi/icone/colori interpolati.
// L'apostrofo tipografico ASCII in un testo custom deve essere escapato
// (ENT_QUOTES) per non rompere l'attributo class='...' o iniettare markup.
eq('custom on badge escapes text',
    BooleanBadge::make(true)->on("E' attivo", '', 'success')->badge(),
    "<span class='badge text-bg-success'>E&#039; ATTIVO</span>"
);

// --- legacyObject: shape identica al merge returnBadge+returnButton
$obj = BooleanBadge::active('true')->action("onclick=\"x()\"")->legacyObject();
eq('legacyObject keys',
    array_keys((array) $obj),
    ['color', 'bootstrapColor', 'text', 'classIcon', 'icon', 'tooltip', 'badge', 'badgeTooltip', 'badgeIcon', 'automaticResize', 'action', 'button']
);
eq('legacyObject text', $obj->text, 'Abilitato');
eq('legacyObject bootstrapColor', $obj->bootstrapColor, 'success');
eq('legacyObject classIcon', $obj->classIcon, 'bi bi-check-circle');
eq('legacyObject action', $obj->action, "onclick=\"x()\"");
eq('legacyObject badgeIcon equals badgeTooltip', $obj->badgeIcon, $obj->badgeTooltip);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
