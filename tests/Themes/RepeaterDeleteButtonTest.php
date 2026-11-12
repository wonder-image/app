<?php
/** php tests/Themes/RepeaterDeleteButtonTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputRepeater;
use Wonder\App\ResourceSchema\RepeaterColumn;

/**
 * `repeaterDeleteButton(false)`: il cestino non si stampa, né nelle righe né
 * nel modello. Serve dove togliere la riga dal form non cancella niente.
 */
$campo = static fn (): InputRepeater => FormField::key('addresses')
    ->repeater([RepeaterColumn::key('street')->text()->label('Via')->columnSpan(11)])
    ->nested()
    ->label('Indirizzi')
    ->value(['row_1' => ['street' => 'Via Roma'], 'row_2' => ['street' => 'Via Milano']]);

// Il JS cita la classe del cestino: si guarda solo quello che viene prima.
$markup = static fn (string $html): string => substr($html, 0, (int) strpos($html, '<script'));
$cestini = static fn (string $html): int => substr_count($markup($html), 'wi-repeater-delete"');

check('di suo il cestino c\'è: uno per riga, più quello del modello', function () use ($campo, $cestini) {
    return $cestini($campo()->render('bootstrap')) === 3
        && $cestini($campo()->repeaterDeleteButton()->render('bootstrap')) === 3;
});

check('repeaterDeleteButton(false) scrive delete_button nel context', function () use ($campo) {
    $context = (array) $campo()->repeaterDeleteButton(false)->get('context');

    return ($context['delete_button'] ?? null) === false;
});

check('senza cestino non si stampa né nelle righe né nel modello', function () use ($campo, $markup, $cestini) {
    $html = $campo()->repeaterDeleteButton(false)->render('bootstrap');

    return $cestini($html) === 0
        && !str_contains($markup($html), 'wiRepeaterRemoveRow')
        && substr_count($markup($html), 'wi-repeater-row') === 3
        && str_contains($markup($html), 'value="Via Roma"');
});

check('il bottone che aggiunge resta: i due si spengono ognuno per conto suo', function () use ($campo, $markup) {
    $senzaCestino = $markup($campo()->repeaterDeleteButton(false)->render('bootstrap'));
    $senzaAggiungi = $markup($campo()->repeaterAddButton(false)->render('bootstrap'));

    return str_contains($senzaCestino, 'wiRepeaterAddRow')
        && !str_contains($senzaAggiungi, 'wiRepeaterAddRow')
        && str_contains($senzaAggiungi, 'wi-repeater-delete"');
});

check('nelle schede richiudibili il riassunto resta senza cestino', function () use ($campo, $markup, $cestini) {
    $html = $campo()->repeaterCollapsible('street')->repeaterDeleteButton(false)->render('bootstrap');

    return $cestini($html) === 0 && str_contains($markup($html), 'wi-repeater-summary-toggle');
});

check('con il riordino la maniglia resta', function () use ($campo, $markup, $cestini) {
    $html = $campo()->repeaterSortable()->repeaterDeleteButton(false)->render('bootstrap');

    return $cestini($html) === 0 && substr_count($markup($html), 'wi-repeater-handle"') === 3;
});

check('le righe minime non si rompono se il cestino manca', function () use ($campo, $cestini) {
    $html = $campo()->repeaterMinRows(1)->repeaterDeleteButton(false)->render('bootstrap');

    return $cestini($html) === 0
        && str_contains($html, 'data-wi-repeater-min="1"')
        && str_contains($html, "row.querySelectorAll('.wi-repeater-delete').forEach(");
});

// Il repeater dentro una riga decide per sé: la scelta di quello esterno non
// lo tocca, e la sua non tocca quello esterno.
$annidato = static fn (bool $esterno, bool $interno): string => $markup(FormField::key('people')
    ->repeater([
        RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(11),
        RepeaterColumn::key('points')
            ->repeater([RepeaterColumn::key('email')->email()->label('Email')->columnSpan(11)])
            ->repeaterDeleteButton($interno)
            ->label('Recapiti')
            ->columnSpan(11),
    ])
    ->nested()
    ->repeaterDeleteButton($esterno)
    ->label('Referenti')
    ->value(['row_a' => ['name' => 'Mario', 'points' => ['p1' => ['email' => 'mario@example.com']]]])
    ->render('bootstrap'));

check('il repeater interno senza cestino: resta solo quello delle righe esterne', function () use ($annidato) {
    // Con tutti e due: riga e modello esterni, riga interna e i suoi due modelli.
    return substr_count($annidato(true, true), 'wi-repeater-delete"') === 5
        && substr_count($annidato(true, false), 'wi-repeater-delete"') === 2;
});

check('il repeater esterno senza cestino lascia quello delle righe interne', function () use ($annidato) {
    return substr_count($annidato(false, true), 'wi-repeater-delete"') === 3
        && substr_count($annidato(false, false), 'wi-repeater-delete"') === 0;
});

summary();
