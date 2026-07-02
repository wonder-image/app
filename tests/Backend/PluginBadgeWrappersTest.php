<?php
/** php tests/Backend/PluginBadgeWrappersTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../app/function/backend/plugin.php';

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

// I warning diventano eccezioni: se un wrapper legge un global null, il test fallisce.
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// --- senza global $NAME/$PATH: nessun warning, action vuota, badge corretto
$obj = active('true', 5);
eq('active no-globals automaticResize', $obj->automaticResize,
    "<span class='badge text-bg-success'><span class='pc-none'><i class='bi bi-check-circle'></i></span><span class='phone-none'>ABILITATO</span></span>"
);
eq('active no-globals action empty', $obj->action, '');
eq('active no-globals button empty', $obj->button, '');

$obj = visible('false', 5);
eq('visible off text', $obj->text, 'Nascosto');
eq('visible off badge', $obj->badge, "<span class='badge text-bg-danger'>NASCOSTO</span>");

$obj = evidence('false', 5);
eq('evidence off automaticResize empty', $obj->automaticResize, '');

// --- con i global popolati (come nelle pagine legacy): action e button costruiti
\Wonder\App\LegacyGlobals::set('NAME', (object) [ 'table' => 'event' ]);
\Wonder\App\LegacyGlobals::set('PATH', (object) [ 'api' => '/api' ]);

$obj = active('true', 5);
eq('active with globals action',
    $obj->action,
    "onclick=\"ajaxRequest('/api/backend/active/?table=event&id=5')\""
);
eq('active with globals button',
    $obj->button,
    "<a class='dropdown-item ' onclick=\"ajaxRequest('/api/backend/active/?table=event&id=5')\" role='button'>Disabilita</a>"
);

$obj = visible('true', 9);
eq('visible with globals action',
    $obj->action,
    "onclick=\"ajaxRequest('/api/backend/visible/?table=event&id=9')\""
);

$obj = evidence('true', 3);
eq('evidence with globals action',
    $obj->action,
    "onclick=\"ajaxRequest('/api/backend/change/boolean/?table=event&column=evidence&id=3')\""
);

// --- returnBadge resta utilizzabile direttamente
$obj = returnBadge('Testo', 'bi bi-star', 'warning');
eq('returnBadge badge', $obj->badge, "<span class='badge text-bg-warning'>TESTO</span>");
eq('returnBadge tooltip', $obj->tooltip, "<i class='bi bi-star' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Testo'></i>");

restore_error_handler();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
