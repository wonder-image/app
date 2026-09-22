<?php
/** php tests/Themes/AccordionGridTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Accordion;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

$conColonne = static function (): string {
    return (new Accordion('Scheda tecnica'))
        ->columns(12)
        ->columnSpan(12)
        ->components([FormField::key('material')->text()->label('Materiale')->columnSpan(6)])
        ->render('bootstrap');
};

$soloTesto = static function (): string {
    return (new Accordion('Domande frequenti'))
        ->description('Una risposta lunga, senza campi dentro.')
        ->render('bootstrap');
};

check('con le colonne il corpo è una griglia, come il card-body della Card', function () use ($conColonne) {
    return (bool) preg_match('/class="accordion-body [^"]*row d-grid row-col-12/', $conColonne());
});

// La larghezza dei campi non la decide il tema: la calcola il layout dei form
// delle Resource, che conosce le colonne del genitore. È lì che l'accordion
// si schiacciava, perché finiva fra i componenti generici invece che fra i
// contenitori.
$nelForm = static function (object $riquadro): string {
    return ResourceFormLayoutRenderer::render(
        (new Form)->components([$riquadro])->columns(12)
    );
};

check('dentro un form il campo tiene la sua larghezza, come nella Card', function () use ($nelForm) {
    $campo = static fn () => FormField::key('material')->text()->label('Materiale')->columnSpan(6);

    $accordion = $nelForm((new Accordion('Scheda tecnica'))
        ->columns(12)
        ->columnSpan(12)
        ->components([$campo()]));

    $card = $nelForm((new Card)
        ->columns(12)
        ->columnSpan(12)
        ->components([$campo()]));

    return str_contains($accordion, 'class="col-6"')
        && str_contains($card, 'class="col-6"');
});

check('dentro un form il corpo dell\'accordion è una riga', function () use ($nelForm) {
    $html = $nelForm((new Accordion('Scheda tecnica'))
        ->columns(12)
        ->columnSpan(12)
        ->components([FormField::key('material')->text()->columnSpan(6)]));

    return (bool) preg_match('/class="accordion-body row/', $html);
});

check('il titolo e il bottone restano quelli di prima', function () use ($conColonne) {
    $html = $conColonne();

    return str_contains($html, 'accordion-button')
        && str_contains($html, 'Scheda tecnica')
        && str_contains($html, 'data-bs-toggle="collapse"');
});

check('un accordion di solo testo non cambia', function () use ($soloTesto) {
    return str_contains($soloTesto(), '<div class="accordion-body">')
        && str_contains($soloTesto(), 'Una risposta lunga');
});

summary();
