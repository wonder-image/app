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

// La variante a link: un bottone di testo con la freccia, come «Compila le
// informazioni avanzate» del repeater, e un corpo senza cornice.
$aLink = static function () use ($nelForm): string {
    return $nelForm((new Accordion('Compila le informazioni avanzate'))
        ->link()
        ->columns(12)
        ->columnSpan(12)
        ->hiddenWhen('has_variants', 'true')
        ->components([FormField::key('sku')->text()->label('SKU')->columnSpan(6)]));
};

check('a link il titolo è un bottone di testo con la freccia', function () use ($aLink) {
    $html = $aLink();

    return str_contains($html, 'btn btn-link btn-sm px-0 text-decoration-none')
        && str_contains($html, '<i class="bi bi-chevron-down me-1"></i>Compila le informazioni avanzate')
        && str_contains($html, 'data-bs-toggle="collapse"')
        && str_contains($html, 'wi-accordion-link');
});

check('a link non c\'è la cornice dell\'accordion', function () use ($aLink) {
    $html = $aLink();

    return !str_contains($html, 'accordion-button')
        && !str_contains($html, 'accordion-item')
        && !str_contains($html, 'class="accordion');
});

check('a link parte chiuso e il corpo resta una griglia', function () use ($aLink) {
    $html = $aLink();

    return (bool) preg_match('/class="collapse"[^>]*>\s*<div class="[^"]*row/', $html)
        && str_contains($html, 'aria-expanded="false"')
        && str_contains($html, 'class="col-6"');
});

check('a link aperto parte con il corpo visibile', function () use ($nelForm) {
    $html = $nelForm((new Accordion('Altro'))->link()->expanded()->columns(12)
        ->components([FormField::key('sku')->text()->columnSpan(6)]));

    return str_contains($html, 'class="collapse show"')
        && str_contains($html, 'aria-expanded="true"');
});

// Chi sparisce con hiddenWhen() dev'essere la colonna: nascosto solo il nodo
// interno, la colonna vuota lascerebbe il margine della riga.
check('dentro un form le regole di visibilità stanno sulla colonna', function () use ($aLink) {
    $html = $aLink();

    return (bool) preg_match(
        '/<div class="col-12" data-hidden-when="has_variants" data-hidden-when-values="true" data-wi-conditional-container="true">/',
        $html
    ) && substr_count($html, 'data-hidden-when="has_variants"') === 1;
});

check('dentro un form le regole di visibilità spostano anche l\'accordion classico', function () use ($nelForm) {
    $html = $nelForm((new Accordion('Scheda tecnica'))->columns(12)->visibleWhen('kind', 'shoe')
        ->components([FormField::key('material')->text()->columnSpan(6)]));

    return (bool) preg_match('/<div class="col-12" data-visible-when="kind"/', $html)
        && substr_count($html, 'data-visible-when="kind"') === 1
        && str_contains($html, 'accordion-button');
});

check('fuori dai form il link si rende da solo', function () {
    $html = (new Accordion('Dettagli'))->link()->description('Un testo.')->render('bootstrap');

    return str_contains($html, 'wi-accordion-link')
        && str_contains($html, 'Un testo.')
        && !str_contains($html, 'accordion-button');
});

summary();
