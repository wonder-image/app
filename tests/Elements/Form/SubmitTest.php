<?php
/** php tests/Elements/Form/SubmitTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', sys_get_temp_dir());
define('ASSETS_VERSION', '1.0.0');
define('APP_VERSION', '2.2.0');

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\Theme;
use Wonder\Elements\Form\Components\Submit;

Theme::set('wonder');

$fail = 0;
function has(string $label, string $html, string $needle): void {
    global $fail;
    if (str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  missing: $needle\n  in: $html\n"; }
}
function hasnt(string $label, string $html, string $needle): void {
    global $fail;
    if (!str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  unexpected: $needle\n  in: $html\n"; }
}

// Default Wonder: classe iniziale del tema + wi-submit
$default = (new Submit('send'))->render();
has('default wonder', $default, 'class="f-end btn btn-success wi-submit"');

// class() sovrascrive la classe di default ma wi-submit resta
$custom = (new Submit('send'))->class('btn btn-primary c-w w-60 w-p-100')->render();
has('class() sovrascrive', $custom, 'class="btn btn-primary c-w w-60 w-p-100 wi-submit"');
hasnt('default rimosso', $custom, 'btn-success');

// wi-submit passato dal caller non viene duplicato
$dup = (new Submit('send'))->class('btn btn-primary wi-submit')->render();
has('wi-submit non duplicato', $dup, 'class="btn btn-primary wi-submit"');
hasnt('nessun doppio wi-submit', $dup, 'wi-submit wi-submit');

// addClass() aggiunge alla classe impostata con class()
$added = (new Submit('send'))->class('btn btn-primary')->addClass('w-100')->render();
has('addClass() aggiunge', $added, 'class="btn btn-primary w-100 wi-submit"');

// Tema Bootstrap: stesso contratto
$bootstrap = (new Submit('send'))->class('btn btn-light')->render('bootstrap');
has('bootstrap class() sovrascrive', $bootstrap, 'class="btn btn-light wi-submit"');

$bootstrapDefault = (new Submit('send'))->render('bootstrap');
has('bootstrap default', $bootstrapDefault, 'class="float-end btn btn-dark wi-submit"');

echo $fail === 0 ? "\nTutti i test passati\n" : "\n$fail test falliti\n";
exit($fail === 0 ? 0 : 1);
