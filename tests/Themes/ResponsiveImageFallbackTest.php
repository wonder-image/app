<?php
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ASSETS_VERSION', 'test');
define('APP_VERSION', 'test');
$sandbox = sys_get_temp_dir().'/wonder-image-fallback-'.uniqid();
define('ROOT', $sandbox);
require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Elements\Media\Image;

mkdir($sandbox);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
$sizes = [240, 480, 960];

// Solo l'originale: resize ancora in coda.
file_put_contents($sandbox.'/only.png', $png);
check('no variants: src is the original, no srcset and no webp source', function () use ($sizes) {
    $html = Image::src(APP_URL.'/only.png')->size(480)->sizes($sizes)->hasWebP()->render('wonder');
    return str_contains($html, 'src="'.APP_URL.'/only.png')
        && !str_contains($html, 'only-')
        && !str_contains($html, '<source');
});
check('url() falls back to the original when the variant is missing', function () {
    return str_starts_with(Image::src(APP_URL.'/only.png')->size(480)->url(), APP_URL.'/only.png');
});

// Varianti parziali: 240 e 960, webp solo per 240.
file_put_contents($sandbox.'/part.png', $png);
foreach (['part-240.png', 'part-960.png', 'part-240.webp'] as $file) {
    file_put_contents($sandbox.'/'.$file, $png);
}
check('missing default size takes the next larger existing one', function () use ($sizes) {
    $html = Image::src(APP_URL.'/part.png')->size(480)->sizes($sizes)->render('wonder');
    return str_contains($html, 'src="'.APP_URL.'/part-960.png')
        && str_contains($html, 'part-240.png')
        && !str_contains($html, 'part-480');
});
check('webp source lists only sizes that exist in both formats', function () use ($sizes) {
    $html = Image::src(APP_URL.'/part.png')->size(240)->sizes($sizes)->hasWebP()->render('wonder');
    return str_contains($html, 'part-240.webp?v=') && !str_contains($html, 'part-960.webp');
});
check('url() keeps an existing variant', function () {
    return str_starts_with(Image::src(APP_URL.'/part.png')->size(240)->url(), APP_URL.'/part-240.png');
});

// Originale esterno: nessuna variante, come prima.
check('remote original stays untouched', function () use ($sizes) {
    $html = Image::src('https://cdn.example.org/x.png')->size(480)->sizes($sizes)->render('wonder');
    return str_contains($html, 'src="https://cdn.example.org/x.png"');
});

array_map('unlink', glob($sandbox.'/*'));
rmdir($sandbox);
summary();
