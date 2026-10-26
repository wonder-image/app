<?php
/** php tests/Themes/AlertSnapshotTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Alert;

// Output dei due renderer catturato prima di ReadsAlertSchema: deve restare identico.
$atteso = array (
  'bootstrap.info' => '<div id=\'a1\' class=\'toast border-info overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-info border-bottom border-info\'> <i class=\'bi bi-info-circle me-2\'></i> <strong class=\'me-auto\'>Info</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>Messaggio</div></div>',
  'bootstrap.success' => '<div id=\'a1\' class=\'toast border-success overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-success border-bottom border-success\'> <i class=\'bi bi-check2-circle me-2\'></i> <strong class=\'me-auto\'>Success</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>Messaggio</div></div>',
  'bootstrap.warning' => '<div id=\'a1\' class=\'toast border-warning overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-warning border-bottom border-warning\'> <i class=\'bi bi-exclamation-triangle me-2\'></i> <strong class=\'me-auto\'>Warning</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>Messaggio</div></div>',
  'bootstrap.error' => '<div id=\'a1\' class=\'toast border-danger overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-danger border-bottom border-danger\'> <i class=\'bi bi-exclamation-triangle me-2\'></i> <strong class=\'me-auto\'>Danger</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>Messaggio</div></div>',
  'bootstrap.completo' => '<div id=\'a2\' class=\'toast border-warning overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' data-x="v&quot;1"><div class=\'toast-header text-bg-warning border-bottom border-warning\'> <i class=\'bi bi-exclamation-triangle me-2\'></i> <strong class=\'me-auto\'>Titolo &lt;x&gt;</strong></div><div class=\'toast-body bg-light\'>Riga &lt;b&gt;uno&lt;/b&gt; &amp; &quot;due&quot;<br />
riga tre</div></div>',
  'wonder.info' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-info-circle tx-primary\'></i> <b>Info</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>Messaggio</div></div>',
  'wonder.success' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-check2-circle tx-success\'></i> <b>Success</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>Messaggio</div></div>',
  'wonder.warning' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-exclamation-triangle tx-warning\'></i> <b>Warning</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>Messaggio</div></div>',
  'wonder.error' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-exclamation-triangle tx-danger\'></i> <b>Danger</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>Messaggio</div></div>',
  'wonder.completo' => '<div id=\'a2\' class=\'wi-alert wi-show\' data-x="v&quot;1"><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-exclamation-triangle tx-warning\'></i> <b>Titolo &lt;x&gt;</b></div><div class=\'wi-alert-body\'>Riga &lt;b&gt;uno&lt;/b&gt; &amp; &quot;due&quot;<br />
riga tre</div></div>',
);

$reso = [];
foreach (['bootstrap', 'wonder'] as $theme) {
    foreach (['info', 'success', 'warning', 'error'] as $level) {
        $reso["{$theme}.{$level}"] = Alert::make('Messaggio', $level)->id('a1')->render($theme);
    }
    $reso["{$theme}.completo"] = Alert::make("Riga <b>uno</b> & \"due\"\nriga tre", 'warning')
        ->id('a2')->title('Titolo <x>')->dismissible(false)->attr('data-x', 'v"1')->render($theme);
}

foreach ($atteso as $caso => $html) {
    check("snapshot {$caso}", fn () => ($reso[$caso] ?? null) === $html);
}

check('i due renderer usano ReadsAlertSchema', function () {
    foreach ([\Wonder\Themes\Bootstrap\Components\Alert::class, \Wonder\Themes\Wonder\Components\Alert::class] as $renderer) {
        if (!in_array(\Wonder\Themes\Concerns\ReadsAlertSchema::class, class_uses($renderer), true)) {
            return false;
        }
    }
    return true;
});

summary();
