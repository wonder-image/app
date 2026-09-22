<?php
/** php tests/Themes/GalleryAcceptTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;

$html = static fn (string $tipo, string $tema): string => FormField::key('media')
    ->fileDragDrop($tipo)
    ->label('Foto e video')
    ->render($tema);

check('il tipo gallery accetta foto e video', function () use ($html) {
    $reso = $html('gallery', 'bootstrap');

    return str_contains($reso, 'image/png')
        && str_contains($reso, 'image/jpeg')
        && str_contains($reso, 'image/webp')
        && str_contains($reso, 'video/mp4');
});

check('lo dice anche a parole', function () use ($html) {
    return str_contains($html('gallery', 'bootstrap'), 'le tue foto e i tuoi video');
});

check('vale anche sul tema pubblico', function () use ($html) {
    return str_contains($html('gallery', 'wonder'), 'video/mp4');
});

check('gli altri tipi non cambiano', function () use ($html) {
    $immagine = $html('image', 'bootstrap');

    return str_contains($immagine, 'image/png, image/jpeg')
        && !str_contains($immagine, 'video/mp4');
});

summary();
