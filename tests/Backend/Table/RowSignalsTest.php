<?php
/** php tests/Backend/Table/RowSignalsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Dataset\ResourceSource;
use Wonder\Backend\Table\RowSignal;
use Wonder\Backend\Table\RowSignals;

$defaults = [
    RowSignal::warning('missing_email', "COALESCE(TRIM({table}.email), '') = ''", 'Manca l\'email'),
    RowSignal::warning('missing_city', "COALESCE(TRIM({table}.city), '') = ''", 'Manca la città'),
];

check('resolve: predefiniti nell\'ordine', function () use ($defaults) {
    RowSignals::reset();
    return array_map(fn ($s) => $s->code, RowSignals::resolve('contacts', $defaults)) === ['missing_email', 'missing_city'];
});

check('resolve: il sito toglie e aggiunge anche prima dei predefiniti', function () use ($defaults) {
    RowSignals::reset();
    RowSignals::remove('contacts', 'missing_city');
    RowSignals::add('contacts', RowSignal::danger('twin', '1 = 1', 'Doppione'));
    RowSignals::add('contacts', RowSignal::warning('missing_email', '1 = 0', 'Altro testo'));
    RowSignals::add('companies', RowSignal::warning('other', '1 = 1', 'Altra lista'));
    $out = RowSignals::resolve('contacts', $defaults);
    RowSignals::reset();
    return array_map(fn ($s) => $s->code, $out) === ['missing_email', 'twin']
        && $out[0]->message === 'Altro testo';
});

check('resolve: when() spegne il segnale', function () use ($defaults) {
    RowSignals::reset();
    $off = [$defaults[0]->when(fn () => false), $defaults[1]];
    return array_map(fn ($s) => $s->code, RowSignals::resolve('contacts', $off)) === ['missing_city']
        && $defaults[0]->active();
});

check('selectSql: un IF per segnale con la tabella sostituita', function () use ($defaults) {
    $sql = RowSignals::selectSql($defaults, 'contacts');
    return $sql === "CONCAT_WS(',', IF((COALESCE(TRIM(contacts.email), '') = ''), 'missing_email', NULL), IF((COALESCE(TRIM(contacts.city), '') = ''), 'missing_city', NULL)) AS row_signals"
        && RowSignals::selectSql([], 'contacts') === "'' AS row_signals";
});

check('selectSql: un solo AS, quello dell\'alias', function () use ($defaults) {
    return preg_match_all('/\bAS\s+/i', RowSignals::selectSql($defaults, 't')) === 1;
});

check('whereAny: OR delle condizioni, per livello, mai vuoto', function () use ($defaults) {
    $all = RowSignals::whereAny($defaults, 'c');
    return $all === "((COALESCE(TRIM(c.email), '') = '') OR (COALESCE(TRIM(c.city), '') = ''))"
        && RowSignals::whereAny($defaults, 'c', RowSignal::DANGER) === '0 = 1'
        && RowSignals::whereAny([], 'c') === '0 = 1';
});

check('fromRow: solo i codici accesi e conosciuti', function () use ($defaults) {
    $on = RowSignals::fromRow($defaults, ['row_signals' => 'missing_city,unknown']);
    return count($on) === 1 && $on[0]->code === 'missing_city'
        && RowSignals::fromRow($defaults, ['row_signals' => null]) === []
        && RowSignals::fromRow($defaults, []) === [];
});

check('dot: vuoto senza segnali, giallo con i motivi', function () use ($defaults) {
    $html = RowSignals::dot($defaults);
    return RowSignals::dot([]) === ''
        && str_contains($html, 'text-warning')
        && str_contains($html, 'data-bs-toggle="tooltip"')
        && str_contains($html, 'Manca l&amp;#039;email&lt;br&gt;Manca la città')
        && str_contains($html, 'visually-hidden');
});

check('dot: rosso se c\'è un danger, e il rosso viene prima', function () use ($defaults) {
    $html = RowSignals::dot([$defaults[0], RowSignal::danger('twin', '1 = 1', 'Doppione')]);
    return str_contains($html, 'text-danger') && !str_contains($html, 'text-warning')
        && strpos($html, 'Doppione') < strpos($html, 'Manca');
});

check('dot: il motivo è escapato', function () {
    $html = RowSignals::dot([RowSignal::warning('x', '1 = 1', '<script>"')]);
    return !str_contains($html, '<script>') && str_contains($html, '&amp;lt;script&amp;gt;');
});

check('codice non valido: eccezione', function () {
    try {
        RowSignal::warning('Bad code', '1 = 1', 'x');
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
});

check('column: prima colonna senza etichetta, fuori dall\'export, legge i segnali una volta', function () use ($defaults) {
    $calls = 0;
    $column = RowSignals::column(function () use ($defaults, &$calls) { $calls++; return $defaults; });
    $schema = $column->toArray();
    $first = ($schema['formatter'])(['row_signals' => 'missing_email']);
    $second = ($schema['formatter'])(['row_signals' => '']);
    return $schema['name'] === 'row_signals' && $schema['label'] === '' && $schema['exportable'] === false
        && str_contains($first, 'text-warning') && $second === '' && $calls === 1;
});

final class RowSignalsExportResource
{
    public static function tableSchema(): array
    {
        return [
            RowSignals::column(fn () => []),
            \Wonder\App\ResourceSchema\TableColumn::key('name')->text(),
        ];
    }
}

check('export di ripiego: la colonna dei segnali resta fuori', function () {
    $method = new ReflectionMethod(ResourceSource::class, 'fallback');
    $source = (new ReflectionClass(ResourceSource::class))->newInstanceWithoutConstructor();
    $property = new ReflectionProperty(ResourceSource::class, 'resource');
    $property->setValue($source, RowSignalsExportResource::class);
    return array_column($method->invoke($source), 'field') === ['name'];
});

summary();
