<?php

require dirname(__DIR__).'/vendor/autoload.php';
define('APP_URL', 'https://example.test');
define('ROOT', dirname(__DIR__));
define('ASSETS_VERSION', 'test');
define('APP_VERSION', 'test');
define('RESPONSIVE_IMAGE_SIZES', [240, 480, 960, 1440, 1920]);

use Wonder\Elements\Media\Image;
use Wonder\Elements\Media\Swiper;
use Wonder\Elements\Media\Gallery;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function images(string $html): array {
    preg_match_all('/<img\b[^>]*>/', $html, $matches);
    return $matches[0];
}
$photos = ['https://example.test/photos/house.jpg' => 'House', 'https://cdn.example.org/remote.jpg?size=full' => 'Remote'];
foreach (['wonder', 'bootstrap'] as $theme) {
    foreach (['plain', 'lightbox', 'zoom'] as $mode) {
        $swiper = Swiper::make($photos)->thumbnails()->priority()->size(240)->thumbsSize(480)
            ->imageSizes('70vw')->thumbsImageSizes('15vw');
        if ($mode !== 'plain') $swiper->$mode();
        $html = $swiper->render($theme);
        $imgs = images($html);
        check(count($imgs) === 4, 'Two main images and two thumbnails');
        check(substr_count($html, 'fetchpriority="high"') === 1, 'Only first main image prioritized');
        check(str_contains($imgs[0], 'loading="eager"') && !str_contains($imgs[0], 'skeleton'), 'Hero visible immediately');
        foreach (array_slice($imgs, 1) as $img) check(str_contains($img, 'loading="lazy"'), 'Remaining images lazy');
        check(substr_count($html, 'sizes="70vw"') === 3, 'Main sizes independent of source resolution');
        check(substr_count($html, 'sizes="15vw"') === 3, 'Separate thumbnail sizes');
        check(!str_contains($html, 'remote-'), 'No fabricated remote variants');
        check(!str_contains($swiper->priority(false)->render($theme), 'fetchpriority="high"'), 'Priority can be disabled');
    }
    $gallery = Gallery::make($photos)->columns(5, 4, 2);
    $html = $gallery->render($theme);
    check(str_contains($html, '50vw, (max-width: '.($theme === 'wonder' ? '992' : '1199.98').'px) 25vw, 20vw'), 'Column sizes match theme');
    check(!str_contains($html, 'fetchpriority'), 'Gallery remains lazy');
    check(str_contains($gallery->imageSizes('40vw')->render($theme), 'sizes="40vw"'), 'Gallery sizes override');
    $html = Image::src('https://example.test/a.jpg')->sizes([240, 480])->hasWebP()
        ->displaySizes('50vw" onload="bad')->render($theme);
    check(str_contains($html, '50vw&quot; onload=&quot;bad'), 'Sizes attribute escaped');
    check(!str_contains($html, ' onload="bad'), 'No attribute injection');
    check(!str_contains(Swiper::make()->slides(['<p>Content</p>'])->priority()->render($theme), 'fetchpriority'), 'Content slides unaffected');
}
echo "Responsive media checks passed\n";
