<?php
/** php tests/Themes/RepeaterNestedTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs\InputRepeater;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\Support\Repeater as RepeaterSupport;

/**
 * Un repeater dentro la riga di un altro: i recapiti di un referente.
 *
 * I nomi hanno due chiavi di riga, e nel modello ognuna ha il suo segnaposto:
 * aggiungere un referente non deve toccare le chiavi dei recapiti, né
 * viceversa. Gli id del repeater interno portano la chiave della riga
 * esterna, sennò ogni referente aggiunto dal browser li ripeterebbe.
 */
$recapiti = static fn (): array => [
    RepeaterColumn::key('kind')->select(['email' => 'Email', 'phone' => 'Telefono', 'mobile' => 'Cellulare'])->label('Tipo')->columnSpan(3),
    RepeaterColumn::key('email')->email()->label('Email')->columnFill()->visibleWhen('kind', 'email'),
    RepeaterColumn::key('phone')->text()->label('Telefono')->columnFill()->visibleWhen('kind', ['phone', 'mobile']),
];

$campo = static fn (array $colonneInterne): InputRepeater => FormField::key('contact_people')
    ->repeater([
        RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(12),
        RepeaterColumn::key('points')
            ->repeater($colonneInterne)
            ->label('Recapiti')
            ->repeaterAddLabel('Aggiungi recapito')
            ->columnSpan(12),
    ])
    ->nested()
    ->repeaterCollapsible('name')
    ->label('Referenti')
    ->value([
        'row_a' => ['name' => 'Mario', 'points' => [
            'p1' => ['kind' => 'email', 'email' => 'mario@example.com'],
            'p2' => ['kind' => 'mobile', 'phone' => '3331234567'],
        ]],
    ]);

$html = $campo($recapiti())->render('bootstrap');

// Il modello delle righe esterne è l'ultimo `<template>` con un id; quello
// del repeater interno di una riga salvata viene prima.
$inizioModello = (int) strrpos($html, '-template">');
$modelloEsterno = substr($html, $inizioModello, (int) strpos($html, '<script', $inizioModello) - $inizioModello);
$righeSalvate = substr($html, 0, $inizioModello);

check('i campi interni di una riga salvata portano le due chiavi', function () use ($righeSalvate) {
    return str_contains($righeSalvate, 'name="contact_people[row_a][points][p1][kind]"')
        && str_contains($righeSalvate, 'name="contact_people[row_a][points][p1][email]"')
        && str_contains($righeSalvate, 'name="contact_people[row_a][points][p2][phone]"');
});

check('le righe interne si riempiono dal valore della riga esterna', function () use ($righeSalvate) {
    return str_contains($righeSalvate, 'value="mario@example.com"')
        && str_contains($righeSalvate, 'value="3331234567"')
        && preg_match('/<option[^>]*value="mobile"[^>]*selected/', $righeSalvate) === 1;
});

check('il modello interno di una riga salvata usa il suo segnaposto', function () use ($righeSalvate) {
    return str_contains($righeSalvate, 'data-wi-row-placeholder="__SUB_KEY__"')
        && str_contains($righeSalvate, 'name="contact_people[row_a][points][__SUB_KEY__][kind]"')
        && !str_contains($righeSalvate, '__ROW_KEY__');
});

check('nel modello esterno i due segnaposto restano distinti', function () use ($modelloEsterno) {
    return str_contains($modelloEsterno, 'name="contact_people[__ROW_KEY__][name]"')
        && str_contains($modelloEsterno, 'name="contact_people[__ROW_KEY__][points][__SUB_KEY__][kind]"')
        && !str_contains($modelloEsterno, '[__ROW_KEY__][points][__ROW_KEY__]')
        && !str_contains($modelloEsterno, '[__SUB_KEY__][points]');
});

check('nel modello esterno gli id del repeater interno portano la chiave della riga', function () use ($modelloEsterno) {
    return preg_match('/data-wi-repeater-inner="true"/', $modelloEsterno) === 1
        && preg_match('/<div id="(field_[a-z]+)-__ROW_KEY__" class="w-100 wi-input-repeater"/', $modelloEsterno, $m) === 1
        && str_contains($modelloEsterno, 'id="'.$m[1].'-__ROW_KEY__-rows"')
        && str_contains($modelloEsterno, '<template id="'.$m[1].'-__ROW_KEY__-template" data-wi-row-placeholder="__SUB_KEY__">')
        && str_contains($modelloEsterno, "window.wiRepeaterAddRow('{$m[1]}-__ROW_KEY__-rows', '{$m[1]}-__ROW_KEY__-template')")
        && preg_match('/<select[^>]+id="field_[a-z]+-__ROW_KEY__-__SUB_KEY__"/', $modelloEsterno) === 1;
});

check('una riga esterna nuova nasce senza righe interne', function () use ($modelloEsterno) {
    // Fuori dal modello interno non c'è nessuna riga di recapito.
    $primaDelModelloInterno = substr($modelloEsterno, 0, (int) strpos($modelloEsterno, '<template', 1));

    return substr_count($primaDelModelloInterno, 'wi-repeater-row') === 1;
});

check('il repeater interno non ristampa lo script né maniglie o riassunti', function () use ($html, $righeSalvate) {
    $interno = substr($righeSalvate, (int) strpos($righeSalvate, 'data-wi-repeater-inner'));

    return substr_count($html, 'window.wiRepeaterAddRow = ') === 1
        && !str_contains($interno, '<script')
        && !str_contains($interno, 'wi-repeater-summary-toggle')
        && !str_contains($interno, 'wi-repeater-handle');
});

check('le colonne condizionali interne tengono la regola sul contenitore', function () use ($righeSalvate) {
    return str_contains($righeSalvate, 'data-visible-when="kind" data-visible-when-values="email"')
        && str_contains($righeSalvate, 'data-visible-when="kind" data-visible-when-values="phone,mobile"');
});

check('senza etichetta dichiarata il repeater interno non ha titolo', function () {
    $html = FormField::key('contact_people')
        ->repeater([RepeaterColumn::key('points')->repeater([RepeaterColumn::key('kind')->text()])])
        ->nested()
        ->label('')
        ->render('bootstrap');

    return !str_contains($html, '<h6>');
});

check('lo script entra nei template annidati e conta solo le righe figlie', function () use ($html) {
    return str_contains($html, "(template.dataset || {}).wiRowPlaceholder || '__ROW_KEY__'")
        && str_contains($html, 'if (element.content) fill(element.content);')
        && str_contains($html, "container.querySelector(':scope > .wi-repeater-row[data-wi-row-key=\"' + rowKey + '\"]')")
        && str_contains($html, "container.dataset.wiAllowEmpty !== 'true'")
        && str_contains($html, 'data-wi-allow-empty="true"');
});

check('il repeater interno tiene il suo bottone e i testi di eliminazione', function () {
    $html = FormField::key('contact_people')
        ->repeater([
            RepeaterColumn::key('points')
                ->repeater([RepeaterColumn::key('kind')->text()])
                ->repeaterAddLabel('Aggiungi recapito')
                ->repeaterDeleteTitle('Togliere il recapito?'),
        ])
        ->nested()
        ->value(['row_a' => ['points' => ['p1' => ['kind' => 'email']]]])
        ->render('bootstrap');
    $interno = substr($html, (int) strpos($html, 'data-wi-repeater-inner'));

    return str_contains($interno, 'Aggiungi recapito')
        && str_contains($interno, 'data-wi-delete-title="Togliere il recapito?"');
});

check('le righe interne salvate come elenco tornano con chiavi distinte', function () {
    // `rowsFromRequest()` restituisce un elenco: è quello che finisce in una
    // colonna JSON e che torna come valore.
    $html = FormField::key('contact_people')
        ->repeater([RepeaterColumn::key('points')->repeater([RepeaterColumn::key('kind')->text()])])
        ->nested()
        ->value([['points' => [['kind' => 'email'], ['kind' => 'phone']]]])
        ->render('bootstrap');

    return preg_match_all('/name="contact_people\[(row_\d+)\]\[points\]\[(row_\d+)\]\[kind\]"/', $html, $m) === 2
        && $m[1][0] === $m[1][1]
        && $m[2][0] !== $m[2][1];
});

check('un repeater dentro un repeater interno lancia LogicException', function () use ($campo) {
    try {
        $campo([
            RepeaterColumn::key('kind')->text(),
            RepeaterColumn::key('extra')->repeater([RepeaterColumn::key('x')->text()]),
        ])->render('bootstrap');
    } catch (LogicException $e) {
        return str_contains($e->getMessage(), 'un solo livello');
    }

    return false;
});

check('senza nested() sul repeater esterno lancia LogicException', function () {
    try {
        FormField::key('contact_people')
            ->repeater([RepeaterColumn::key('points')->repeater([RepeaterColumn::key('kind')->text()])])
            ->render('bootstrap');
    } catch (LogicException $e) {
        return str_contains($e->getMessage(), 'nested()');
    }

    return false;
});

check('la riga esterna con solo un recapito mai toccato non si salva', function () use ($campo, $recapiti) {
    $defaults = RepeaterSupport::columnDefaults($campo($recapiti()));
    $rows = RepeaterSupport::rowsFromRequest('contact_people', ['contact_people' => [
        'a' => ['name' => '', 'points' => ['x' => ['kind' => 'email', 'email' => '', 'phone' => '']]],
        'b' => ['name' => 'Mario', 'points' => [
            'x' => ['kind' => 'email', 'email' => '', 'phone' => ''],
            'y' => ['kind' => 'email', 'email' => 'mario@example.com', 'phone' => ''],
        ]],
    ]], [], $defaults);

    return $defaults['points'] === ['kind' => 'email']
        && count($rows) === 1
        && $rows[0]['name'] === 'Mario'
        && count($rows[0]['points']) === 1
        && $rows[0]['points'][0]['email'] === 'mario@example.com';
});

summary();
