<?php
/** php tests/Themes/HiddenFieldLayoutTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

$nelForm = static function (array $campi): string {
    return ResourceFormLayoutRenderer::render(
        (new Form)->components([
            (new Card)->columns(12)->columnSpan(12)->components($campi),
        ])->columns(12)
    );
};

check('un campo nascosto non prende una colonna della griglia', function () use ($nelForm) {
    $html = $nelForm([
        FormField::key('name')->text()->label('Nome')->columnSpan(12),
        FormField::key('secret')->hidden()->value('3')->columnSpan(12),
        FormField::key('code')->text()->label('Codice')->columnSpan(12),
    ]);

    // Dentro una `row g-3` anche una colonna vuota porta il margine della
    // riga: il campo sta direttamente nella riga, senza il suo `col-12`.
    return str_contains($html, '<input type="hidden" name="secret"')
        && !preg_match('/<div class="col-12">\s*<input type="hidden" name="secret"/', $html);
});

check('un campo nascosto che dipende da un altro porta la regola con sé', function () use ($nelForm) {
    $html = $nelForm([
        FormField::key('kind')->select(['a' => 'A', 'b' => 'B'])->label('Tipo')->columnSpan(12),
        FormField::key('secret')->hidden()->value('3')->columnSpan(12)->visibleWhen('kind', 'b'),
    ]);

    return (bool) preg_match('/<input type="hidden" name="secret"[^>]*data-visible-when="kind"/', $html);
});

check('la colonna di un campo condizionale è il contenitore che si nasconde', function () use ($nelForm) {
    $html = $nelForm([
        FormField::key('kind')->select(['a' => 'A', 'b' => 'B'])->label('Tipo')->columnSpan(12),
        FormField::key('price')->text()->label('Prezzo')->columnSpan(4)->hiddenWhen('kind', 'b'),
        FormField::key('code')->text()->label('Codice')->columnSpan(4),
    ]);

    // La lib nasconde il contenitore marcato più vicino: la colonna del
    // prezzo sparisce intera, quella del codice non si marca.
    return (bool) preg_match('/<div class="col-4" data-wi-conditional-container="true">(?:(?!<div class="col-).)*name="price"/s', $html)
        && !preg_match('/<div class="col-4" data-wi-conditional-container="true">(?:(?!<div class="col-).)*name="code"/s', $html);
});

summary();
