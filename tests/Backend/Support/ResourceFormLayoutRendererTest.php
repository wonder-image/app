<?php
/** php tests/Backend/Support/ResourceFormLayoutRendererTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Accordion;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Components\QuickCreateButton;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Form;

// Tag di oggi, scritto prima della modifica.
$tag = '<form id="resource-layout-form" method="POST" enctype="multipart/form-data" action="" onsubmit="loadingSpinner()" class="row g-3">';
$form = static fn (array $components = []): Form => (new Form)->components($components);
$render = static fn (array $options = []): string => ResourceFormLayoutRenderer::render($form(), $options);

check('P9 senza attributi, con [] e con tutti false il tag resta quello di oggi', fn () =>
    $render() === $tag.'</form>'
    && $render(['attributes' => []]) === $tag.'</form>'
    && $render(['attributes' => ['data-wi-save-bar' => false, 'data-wi-save-bar-dirty' => false]]) === $tag.'</form>'
);

check('P10 gli attributi della barra dopo class, con lo spazio del renderer', fn () =>
    $render(['attributes' => ['data-wi-save-bar' => true, 'data-wi-save-bar-dirty' => true]])
        === substr($tag, 0, -1).' data-wi-save-bar data-wi-save-bar-dirty></form>'
    && $render(['attributes' => ['data-wi-save-bar' => true, 'data-wi-save-bar-dirty' => false]])
        === substr($tag, 0, -1).' data-wi-save-bar></form>'
);

check('P11 le sei chiavi riservate sono ignorate e restano i valori fissi', fn () =>
    $render(['attributes' => [
        'id' => 'altro',
        'method' => 'GET',
        'enctype' => 'text/plain',
        'action' => '/altrove',
        'onsubmit' => 'return false',
        ' Class ' => 'd-none',
        'data-wi-save-bar' => true,
    ]]) === substr($tag, 0, -1).' data-wi-save-bar></form>'
);

check('P12 hasSubmit trova upload dentro Card, Accordion aperto e Container annidati', fn () =>
    ResourceFormLayoutRenderer::hasSubmit($form([new Submit('upload')]))
    && ResourceFormLayoutRenderer::hasSubmit($form([
        (new Container)->components([
            (new Card)->components([
                (new Accordion('Altro'))->expanded()->components([new Submit('upload')]),
            ]),
        ]),
    ]))
);

check('P12 hasSubmit non entra in Modal e QuickCreate', function () use ($form) {
    $risorsa = new class {
        public static function slug(): string
        {
            return 'feature';
        }
    };

    return !ResourceFormLayoutRenderer::hasSubmit($form([
        Modal::make('Conferma')->components([new Submit('upload')]),
        QuickCreateButton::make(get_class($risorsa))->layout(fn () => (new Form)->components([new Submit('upload')])),
    ]));
});

check('P12 con upload-add cerca solo quel nome', fn () =>
    !ResourceFormLayoutRenderer::hasSubmit($form([new Submit('upload')]), 'upload-add')
    && ResourceFormLayoutRenderer::hasSubmit($form([new Submit('upload-add')]), 'upload-add')
    && !ResourceFormLayoutRenderer::hasSubmit($form([new Submit('upload-add')]))
);

check('P12 non conta dentro una Card condizionale o un Accordion chiuso', fn () =>
    !ResourceFormLayoutRenderer::hasSubmit($form([
        (new Card)->visibleWhen('has_variants', 'true')->components([new Submit('upload')]),
    ]))
    && !ResourceFormLayoutRenderer::hasSubmit($form([
        (new Container)->hiddenWhen('has_variants', 'false')->components([new Submit('upload')]),
    ]))
    && !ResourceFormLayoutRenderer::hasSubmit($form([
        (new Accordion('Altro'))->components([new Submit('upload')]),
        (new Accordion('Chiuso'))->expanded(false)->components([new Submit('upload')]),
    ]))
);

check('P13 Card con visibleWhen: output identico, attributes() non cambia', fn () =>
    ResourceFormLayoutRenderer::render($form([
        (new Card)->columns(12)->columnSpan(12)
            ->visibleWhen('has_variants', 'true')
            ->attr('data-note', null)
            ->attr('class', 'ignorata')
            ->components(['<p>Varianti</p>']),
    ])->columns(12)) === $tag
        .'<div class="col-12" data-visible-when="has_variants" data-visible-when-values="true" data-wi-conditional-container="true" data-note="">'
        .'<div class="card border"><div class="card-body row g-3"><p>Varianti</p></div></div></div></form>'
);

summary();
