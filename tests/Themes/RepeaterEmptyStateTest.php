<?php
/** php tests/Themes/RepeaterEmptyStateTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/** Lo stato vuoto (`repeaterEmptyState()`): riquadro con il bottone al centro. */
$render = static function (array $context, array $value = [], ?array $columns = null): string {
    $field = new class {
        public array $schema = [];
    };
    $field->schema = [
        'id' => 'people',
        'name' => 'people',
        'label' => '',
        'value' => $value,
        'columns' => $columns ?? [RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(6)],
        'context' => $context + ['nested' => true, 'add_label' => 'Aggiungi referente'],
    ];

    return (new Repeater)->render($field);
};
// Le righe stampate, senza il modello e senza lo script.
$rows = static fn (string $html): int => substr_count(
    (string) preg_replace('~<template.*?</template>|<script.*?</script>~s', '', $html),
    'wi-repeater-row"'
);
$tag = static function (string $html, string $attr): string {
    preg_match('~<div[^>]*'.preg_quote($attr, '~').'[^>]*>~', $html, $m);

    return $m[0] ?? '';
};

check('il setter scrive il context', function () {
    $context = (array) (FormField::key('people')->repeater([])->repeaterEmptyState()->get('context') ?? []);

    return ($context['empty_state'] ?? null) === true;
});

check('senza righe: nessuna riga di cortesia, riquadro visibile, bottone in fondo nascosto', function () use ($render, $rows, $tag) {
    $html = $render(['empty_state' => true]);
    $empty = $tag($html, 'data-wi-repeater-empty');
    $add = $tag($html, 'data-wi-repeater-add');

    return $rows($html) === 0
        && $empty !== '' && !str_contains($empty, 'd-none')
        && str_contains($add, 'd-none')
        && str_contains($html, "wiRepeaterAddFirstRow('people-rows', 'people-template')")
        && substr_count($html, 'Aggiungi referente') === 2
        && str_contains($html, 'data-wi-allow-empty="true"');
});

check('con le righe: riquadro nascosto, bottone in fondo visibile', function () use ($render, $rows, $tag) {
    $html = $render(['empty_state' => true], ['row_1' => ['name' => 'Ada']]);

    return $rows($html) === 1
        && str_contains($tag($html, 'data-wi-repeater-empty'), 'd-none')
        && !str_contains($tag($html, 'data-wi-repeater-add'), 'd-none');
});

check('senza il setter niente cambia: riga di cortesia e nessun riquadro', function () use ($render, $rows, $tag) {
    $html = $render([]);

    return $rows($html) === 1 && $tag($html, 'data-wi-repeater-empty') === ''
        && !str_contains($html, 'data-wi-allow-empty');
});

check('con le righe minime o senza il bottone lo stato vuoto non vale', function () use ($render, $tag) {
    return $tag($render(['empty_state' => true, 'min_rows' => 1]), 'data-wi-repeater-empty') === ''
        && $tag($render(['empty_state' => true, 'add_button' => false, 'start_empty' => true]), 'data-wi-repeater-empty') === '';
});

check('vale anche per il repeater dentro una riga, nel modello compreso', function () use ($render) {
    $columns = [
        RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(12),
        RepeaterColumn::key('points')
            ->repeater([RepeaterColumn::key('value')->text()->label('Valore')->columnSpan(12)])
            ->repeaterAddLabel('Aggiungi contatto')
            ->repeaterEmptyState()
            ->columnSpan(12),
    ];
    $html = (string) preg_replace('~<script.*?</script>~s', '', $render([], ['row_1' => ['name' => 'Ada']], $columns));
    preg_match('~<template id="people-template">(.*)</template>~s', $html, $m);

    // Una volta nella riga e una nel modello, tutte e due con il riquadro aperto.
    return substr_count($html, 'data-wi-repeater-empty') === 2
        && str_contains($m[1] ?? '', 'data-wi-repeater-empty')
        && !preg_match('~<div[^>]*d-none[^>]*data-wi-repeater-empty~', $html)
        && substr_count($html, 'wiRepeaterAddFirstRow(') >= 2;
});

check('lo script tiene allineati riquadro e bottone', function () use ($render) {
    $html = $render(['empty_state' => true]);

    return str_contains($html, 'window.wiRepeaterSyncEmpty = window.wiRepeaterSyncEmpty || function')
        && str_contains($html, 'window.wiRepeaterAddFirstRow = window.wiRepeaterAddFirstRow || function')
        && str_contains($html, "new CustomEvent('wi-repeater-row-removed'");
});

summary();
