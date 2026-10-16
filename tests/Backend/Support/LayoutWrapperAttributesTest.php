<?php
/** php tests/Backend/Support/LayoutWrapperAttributesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Link;

// Il div della colonna prende solo la classe e le regole di visibilità: gli
// attributi di un link (href, target, rel...) restano sull'elemento interno.
$layout = (new Container)->columns(2)->components([
    Button::to('/oauth/consent?client_id=x', 'Consenti')->blank(),
    Button::post('/oauth/revoke', 'Revoca'),
    Link::to('/docs', 'Documentazione')->external(),
    (new Card)->columns(2)->components([
        Link::to('/dentro', 'Dentro la card'),
    ]),
]);

$html = ResourceFormLayoutRenderer::renderLayout($layout);

$wrappers = static function (string $html): array {
    preg_match_all('/<div\s[^>]*>/', $html, $m);

    return $m[0];
};

check('il layout ha delle colonne col-* da controllare', fn () => preg_match('/<div class="col-/', $html) === 1);

check('nessun wrapper (col-* compreso) ha href', function () use ($wrappers, $html) {
    $bad = array_filter($wrappers($html), static fn (string $tag): bool => preg_match('/\shref=/', $tag) === 1);
    foreach ($bad as $tag) {
        echo "    {$tag}\n";
    }

    return $bad === [];
});

check('nessun wrapper (col-* compreso) ha target o rel', fn () =>
    array_filter($wrappers($html), static fn (string $tag): bool => preg_match('/\s(target|rel)=/', $tag) === 1) === []
);

check('href resta sull\'elemento interno', fn () =>
    str_contains($html, 'href="/oauth/consent?client_id=x"')
    && str_contains($html, 'href="/docs"')
    && str_contains($html, 'href="/dentro"')
);

check('visibleWhen su un bottone resta sulla colonna, href no', function () {
    $html = ResourceFormLayoutRenderer::renderLayout(
        (new Container)->columns(2)->components([
            Button::to('/x', 'X')->columnSpan(1)->visibleWhen('stato', 'attivo'),
        ])
    );

    return preg_match('/<div class="[^"]*col-[^"]*"[^>]*data-visible-when/', $html) === 1
        && preg_match('/<div class="[^"]*col-[^"]*"[^>]*href=/', $html) === 0;
});

summary();
