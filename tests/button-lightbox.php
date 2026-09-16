<?php

require dirname(__DIR__).'/vendor/autoload.php';
define('APP_URL', 'https://example.test');
use Wonder\Elements\Components\Button;

function check(bool $value, string $message): void {
    if (!$value) throw new RuntimeException($message);
}
foreach (['wonder', 'bootstrap'] as $theme) {
    $button = Button::make('Open')->lightbox(['/a.jpg?x=1&y=2', 'https://example.org/tour']);
    $html = $button->render($theme);
    check(substr_count(explode('<script>', $html)[0], 'data-fancybox=') === 2, 'Two grouped links');
    check(str_contains($html, 'data-type="image"') && str_contains($html, 'data-type="iframe"'), 'Mixed types');
    check(str_contains($html, 'href="/a.jpg?x=1&amp;y=2"'), 'Escaped fallback link');
    check($button->getHref() === '', 'Caller not mutated');
    check(str_contains($html, 'autoSize:false'), 'Cross-origin iframe sizing');
    check(!str_contains($button->disabled()->render($theme), 'Fancybox.bind'), 'Disabled button inert');
    check(str_contains(Button::make('Open')->lightbox('/image-endpoint', 'image')->render($theme), 'data-type="image"'), 'Explicit type');
    check(!str_contains(Button::make('Open')->lightbox([])->render($theme), 'Fancybox'), 'Empty list is a normal button');
    try {
        Button::post('/save', 'Save')->lightbox('/tour')->render($theme);
        throw new RuntimeException('POST button accepted');
    } catch (InvalidArgumentException) {}
}
foreach (['javascript:alert(1)', 'data:text/html,test', '', "https://example.org/\npage"] as $url) {
    try {
        Button::make()->lightbox($url);
        throw new RuntimeException('Unsafe URL accepted');
    } catch (InvalidArgumentException) {}
}
echo "Button lightbox checks passed\n";
