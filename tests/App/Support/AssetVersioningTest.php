<?php
/** php tests/App/Support/AssetVersioningTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ASSETS_VERSION', '1.0.0');

$root = sys_get_temp_dir().'/asset-versioning-test-'.uniqid();
define('ROOT', $root);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../app/function/helper.php';

use Wonder\App\Support\AppIconLinks;
use Wonder\App\Support\Asset;
use Wonder\App\Support\OptionVisual;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Media\Video;

$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void {
    global $fail;
    if ($ok) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n".($detail !== '' ? "  $detail\n" : ''); }
}
function same(string $label, ?string $actual, ?string $expected): void {
    check($label, $actual === $expected, 'expected: '.var_export($expected, true).' actual: '.var_export($actual, true));
}

function file_at(string $root, string $path, string $content, int $mtime): void {
    @mkdir(dirname($root.$path), 0777, true);
    file_put_contents($root.$path, $content);
    touch($root.$path, $mtime);
}

$up = '/assets/upload';
file_at($root, "$up/video/clip.mp4", 'v', 1700000001);
file_at($root, "$up/video/clip.webm", 'v', 1700000002);
file_at($root, "$up/video/clip.jpg", 'p', 1700000003);
file_at($root, "$up/gallery/a.jpg", 'a', 1700000004);
file_at($root, "$up/options/red.png", 'r', 1700000005);
file_at($root, "$up/logo/icon-192.png", 'i', 1700000006);
file_at($root, '/assets/fonts/inter.woff2', 'f', 1700000007);
file_at($root, '/assets/fonts/inter.css', "@font-face{src:url('inter.woff2') format('woff2'),url(inter.eot?#iefix)}\n.x{background:url(/assets/upload/gallery/a.jpg)}\n.y{background:url(\"data:image/png;base64,AA\")}", 1700000008);
file_at($root, '/assets/css/escape.css', '.z{background:url(../../../outside.png)}', 1700000009);

// --- Video ---
foreach (['wonder', 'bootstrap'] as $theme) {
    $html = Video::src(APP_URL."$up/video/clip.mp4")->webm()->render($theme);
    check("video $theme: src versionato", str_contains($html, "clip.mp4?v=1700000001"), $html);
    check("video $theme: webm versionato", str_contains($html, "clip.webm?v=1700000002"), $html);
    check("video $theme: poster versionato", str_contains($html, "clip.jpg?v=1700000003"), $html);

    $external = Video::src('https://cdn.example.org/clip.mp4')->render($theme);
    check("video $theme: src esterno invariato", !str_contains($external, '?v='), $external);
}

// --- Lightbox ---
foreach (['wonder', 'bootstrap'] as $theme) {
    $html = Button::make('Apri')->lightbox([APP_URL."$up/gallery/a.jpg", APP_URL."$up/video/clip.jpg"])->render($theme);
    check("lightbox $theme: primo href versionato", str_contains($html, "a.jpg?v=1700000004"), $html);
    check("lightbox $theme: secondo href versionato", str_contains($html, "clip.jpg?v=1700000003"), $html);
}

// --- OptionVisual ---
$visual = OptionVisual::of(['image' => APP_URL."$up/options/red.png"]);
same('OptionVisual immagine locale', $visual['value'], APP_URL."$up/options/red.png?v=1700000005");
$visual = OptionVisual::of(['image' => 'https://cdn.example.org/red.png']);
same('OptionVisual immagine esterna', $visual['value'], 'https://cdn.example.org/red.png');

// --- AppIconLinks ---
$icons = AppIconLinks::sized(APP_URL."$up/logo/icon.png", [192, 512]);
same('icona esistente versionata', $icons[192] ?? null, APP_URL."$up/logo/icon-192.png?v=1700000006");
same('icona mancante invariata', $icons[512] ?? null, APP_URL."$up/logo/icon-512.png");
check('icona vuota', AppIconLinks::sized('', [192]) === []);

// --- url() nei CSS inline ---
$inline = (string) Asset::inlineStyle(APP_URL.'/assets/fonts/inter.css', null, null, 32768, true);
check('inline: woff2 relativo assoluto e versionato', str_contains($inline, "url('".APP_URL."/assets/fonts/inter.woff2?v=1700000007')"), $inline);
check('inline: ?#iefix resta senza versione', str_contains($inline, 'inter.eot?#iefix'), $inline);
check('inline: root-relative versionato', str_contains($inline, 'url(/assets/upload/gallery/a.jpg?v=1700000004)'), $inline);
check('inline: data: invariato', str_contains($inline, 'data:image/png;base64,AA'), $inline);
same('inline: traversal oltre la radice', Asset::inlineStyle(APP_URL.'/assets/css/escape.css', null, null, 32768, true), null);

$helper = __inline_css(APP_URL.'/assets/fonts/inter.css');
check('__inline_css: url() riscritti', str_starts_with($helper, '<style>') && str_contains($helper, 'inter.woff2?v=1700000007'), $helper);
check('__inline_css: ripiega sul link versionato', str_contains(__inline_css(APP_URL.'/assets/css/escape.css'), 'escape.css?v=1700000009'));

// --- Helper legacy ---
$PATH = (object) ['upload' => APP_URL.$up];
require __DIR__ . '/../../../app/function/frontend/plugin/image.php';
file_at($root, "$up/gallery/a-480.jpg", 'a', 1700000010);
same('srcSet versionato', srcSet('gallery', 'a', 'jpg', [480]), APP_URL."$up/gallery/a-480.jpg?v=1700000010 480w");

exec('rm -rf '.escapeshellarg($root));
echo $fail === 0 ? "\nAll passed\n" : "\n$fail failed\n";
exit($fail === 0 ? 0 : 1);
