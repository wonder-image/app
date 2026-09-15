<?php

require dirname(__DIR__).'/vendor/autoload.php';
define('APP_URL', 'https://example.test');
define('ROOT', dirname(__DIR__));
define('ASSETS_VERSION', 'test');
define('APP_VERSION', 'test');
function __t(string $key): string { return 'Load content'; }

use Wonder\Elements\Media\Iframe;
use Wonder\Elements\Media\Deferred;
use Wonder\Elements\Components\Button;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

foreach (['wonder', 'bootstrap'] as $theme) {
    $button = Button::make('Watch <video>')->variant('secondary')->outline()->icon('bi bi-play');
    $iframe = Iframe::url('https://example.org/embed?a=1&b=2')->attr('title', 'Video')
        ->ratio('4:3')->deferred(button: $button);
    $html = $iframe->render($theme);
    check(str_contains($html, 'data-wi-deferred="interaction"'), 'Interaction mode');
    check(str_contains($html, 'aspect-ratio: 4 / 3'), 'Inferred ratio');
    check(str_contains($html, 'data-wi-deferred-template><iframe'), 'Iframe is inert');
    check(str_contains($html, 'Watch &lt;video&gt;'), 'Button labels escaped');
    check(str_contains($html, 'bi-play'), 'Button icons preserved');
    check(str_contains($html, 'secondary'), 'Button variant preserved');
    check($button->getHref() === '', 'Do not mutate caller button');
    check(!str_contains($iframe->getStyle('height') ?? '', '100%'), 'Do not mutate caller sizing');
    check(str_contains($html, 'target="_blank"'), 'External fallback');
    $plain = $iframe->deferred(false)->render($theme);
    check(!str_contains($plain, '<template'), 'Plain rendering unchanged');
    $fill = Iframe::url('/embed')->fitCover()->deferred()->render($theme);
    check(str_contains($fill, 'inset: 0') && !str_contains($fill, 'aspect-ratio'), 'Fill sized parent');
    $dimensions = Iframe::url('/embed')->attr('width', 640)->attr('height', 360)->deferred('visible')->render($theme);
    check(str_contains($dimensions, 'aspect-ratio: 640 / 360'), 'HTML dimensions infer ratio');
    check(str_contains($dimensions, 'data-wi-deferred="visible"'), 'Visible mode');
    $expanded = Iframe::url('/embed')->expandable()->deferred()->render($theme);
    check(str_contains($expanded, 'Fancybox.bind'), 'Deferred expandable binding preserved');
    $secondExpanded = Iframe::url('/other')->expandable()->deferred()->render($theme);
    check(str_contains($secondExpanded, 'Fancybox.bind'), 'Independent deferred expansion');
    check(str_contains(Deferred::make('<p>Trusted HTML</p>')->button($button)->render($theme), '<p>Trusted HTML</p>'), 'Generic content');
}
foreach (['automatic', 'lazy', ''] as $invalid) {
    try {
        Iframe::url('/embed')->deferred($invalid);
        throw new RuntimeException('Invalid mode accepted');
    } catch (InvalidArgumentException) {}
}
try {
    Deferred::make('test')->fallbackUrl('javascript:alert(1)');
    throw new RuntimeException('Unsafe fallback accepted');
} catch (InvalidArgumentException) {}
echo "Deferred media checks passed in Wonder and Bootstrap\n";
