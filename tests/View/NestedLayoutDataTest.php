<?php // tests/View/NestedLayoutDataTest.php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\App\LegacyGlobals;
use Wonder\View\View;

// Regressione: una pagina apre `backend.show` con TITLE, `backend.show` apre
// `backend.main` e poi `backend.base`, che scrive <title>. I dati della pagina
// devono arrivare anche ai layout annidati.

$tmp = sys_get_temp_dir().'/wi_nested_layout_'.uniqid();
@mkdir($tmp, 0777, true);
LegacyGlobals::share(['ROOT' => $tmp, 'ROOT_APP' => $tmp]);

$base = $tmp.'/base.php';
$main = $tmp.'/main.php';
$show = $tmp.'/show.php';
file_put_contents($base, '<title><?= $TITLE ?? "" ?></title><?= $PAGE_CONTENT ?>');
file_put_contents($main, '<?php \Wonder\View\View::layout('.var_export($base, true).'); ?>[main]<?= $PAGE_CONTENT ?><?php \Wonder\View\View::end(); ?>');
file_put_contents($show, '<?php \Wonder\View\View::layout('.var_export($main, true).'); ?><h3><?= $TITLE ?? "" ?></h3><?= $PAGE_CONTENT ?><?php \Wonder\View\View::end(); ?>');

$render = function (string $layout, array $data, string $body): string {
    ob_start();
    View::layout($layout, $data);
    echo $body;
    View::end();

    return (string) ob_get_clean();
};

check('il TITLE della pagina arriva al <title> del layout più esterno', function () use ($render, $show) {
    $html = $render($show, ['TITLE' => 'Etichette'], 'corpo');

    return str_contains($html, '<title>Etichette</title>') && str_contains($html, '<h3>Etichette</h3>');
});

check('i dati del layout interno prevalgono su quelli ereditati', function () use ($render, $tmp, $base) {
    $inner = $tmp.'/inner.php';
    file_put_contents($inner, '<?php \Wonder\View\View::layout('.var_export($base, true).', ["TITLE" => "Proprio"]); ?><?= $PAGE_CONTENT ?><?php \Wonder\View\View::end(); ?>');

    return str_contains($render($inner, ['TITLE' => 'Ereditato'], 'x'), '<title>Proprio</title>');
});

check('il contenuto del layout esterno non sostituisce quello interno', function () use ($render, $show) {
    $html = $render($show, ['TITLE' => 'T'], 'corpo');

    return substr_count($html, 'corpo') === 1 && str_contains($html, '[main]<h3>T</h3>corpo');
});

check('dopo il rendering i dati non restano per la pagina successiva', function () use ($render, $base) {
    $render($base, ['TITLE' => 'Prima'], '');

    return str_contains($render($base, [], ''), '<title></title>');
});

summary();
