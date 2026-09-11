<?php
declare(strict_types=1);

define('APP_URL', 'https://example.test');
$sandbox = sys_get_temp_dir().'/wonder-image-cache-'.uniqid();
define('ROOT', $sandbox);
require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

mkdir($sandbox);
file_put_contents($sandbox.'/photo-480.webp', 'image');
touch($sandbox.'/photo-480.webp', 1700000000);

$renderer = new class {
    use \Wonder\Themes\Concerns\RendersResponsiveImage;
};
$renderer->directoryUrl = APP_URL.'/';
$renderer->imageName = 'photo';
$renderer->extension = 'webp';
$renderer->sizes = [480];
$renderer->defaultSize = 480;
$renderer->src = APP_URL.'/photo-480.webp';
$renderer->attributes = 'loading="lazy"';

check('src and srcset version each existing image variant', function () use ($renderer) {
    return substr_count($renderer->renderImg(), 'photo-480.webp?v=1700000000') === 2;
});
check('missing variants keep original URL', function () use ($renderer) {
    $renderer->sizes = [960];
    return $renderer->renderSrcSet() === APP_URL.'/photo-960.webp 960w';
});

unlink($sandbox.'/photo-480.webp');
rmdir($sandbox);
summary();
