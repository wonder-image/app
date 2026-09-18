<?php
/** php tests/Elements/Components/ContainerMasonryTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Theme;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Text;

Theme::set('bootstrap');

$container = static fn (): Container => (new Container)->components([
    (new Card)->components([Text::make('Prima')]),
    (new Card)->components([Text::make('Seconda')]),
    (new Card)->components([Text::make('Terza')]),
]);

check('senza masonry resta la griglia a righe', function () use ($container) {
    $html = $container()->columns(2)->render();

    return str_contains($html, 'row') && !str_contains($html, 'columns:');
});

check('masonry mette le colonne CSS e il numero chiesto', function () use ($container) {
    $html = $container()->masonry(2)->render();

    return str_contains($html, 'columns: 22rem 2;') && str_contains($html, 'column-gap: 1rem;');
});

check('larghezza minima e distanza si possono cambiare', function () use ($container) {
    $html = $container()->masonry(3, '18rem', '2rem')->render();

    return str_contains($html, 'columns: 18rem 3;') && str_contains($html, 'column-gap: 2rem;');
});

check('ogni figlio non si spezza tra due colonne', function () use ($container) {
    $html = $container()->masonry(2)->render();

    return substr_count($html, 'break-inside: avoid') === 3;
});

check('il contenitore in masonry non è più una griglia a righe', function () use ($container) {
    $html = $container()->masonry(2)->render();

    // Le card mantengono la propria griglia interna: qui conta il contenitore.
    return !str_contains($html, 'class="row d-grid')
        && str_contains($html, '<div style="columns: 22rem 2;');
});

summary();
