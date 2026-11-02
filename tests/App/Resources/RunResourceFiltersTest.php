<?php
/** php tests/App/Resources/RunResourceFiltersTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Resources\Scheduler\RunResource;
use Wonder\App\Scheduler\Presentation;
use Wonder\Backend\Filter\FilterCustom;

$now = gmmktime(12, 0, 0, 11, 2, 2026);

check('le attivita prendono prima il nome in codice, poi la pianificazione, poi la chiave', function () {
    $options = RunResource::taskOptions(
        ['wonder.sitemap', 'custom.abc', 'removed.task', ' ', 'wonder.sitemap'],
        ['wonder.sitemap' => 'Sitemap'],
        ['custom.abc' => 'Backup notturno', 'wonder.sitemap' => 'Ignorato']
    );

    return $options === ['custom.abc' => 'Backup notturno', 'removed.task' => 'removed.task', 'wonder.sitemap' => 'Sitemap'];
});

check('il filtro Esito usa le etichette dei badge', function () {
    return Presentation::statusLabels() === [
        'success' => 'Riuscita', 'failed' => 'Fallita', 'interrupted' => 'Interrotta',
        'running' => 'In esecuzione', 'pending' => 'In attesa', 'skipped' => 'Saltata',
    ] && str_contains(Presentation::status('failed'), 'Fallita') && str_contains(Presentation::status('altro'), 'altro');
});

check('il periodo diventa una soglia UTC su started_at', function () use ($now) {
    return RunResource::periodCondition(['7d'], $now) === "`started_at` >= '2026-10-26 12:00:00'"
        && RunResource::periodCondition(['24h'], $now) === "`started_at` >= '2026-11-01 12:00:00'";
});

check('fra piu periodi vince il piu ampio, quelli sconosciuti non contano', function () use ($now) {
    return RunResource::periodCondition(['24h', '30d', "1' OR 1=1"], $now) === "`started_at` >= '2026-10-03 12:00:00'"
        && RunResource::periodCondition(['90d'], $now) === ''
        && RunResource::periodCondition([], $now) === '';
});

$filters = RunResource::tableLayoutSchema()->all()['custom_filters'];

check('la tabella dichiara i filtri Attivita, Esito e Periodo', function () use ($filters) {
    return array_map(static fn (array $f) => [$f['label'], $f['column'], $f['input']], $filters) === [
        ['Attivita', 'task_key', 'checkbox'],
        ['Esito', 'status', 'checkbox'],
        ['Periodo', 'period', 'radio'],
    ];
});

check('il filtro Esito filtra sulla colonna status solo per valore', function () use ($filters) {
    return FilterCustom::condition($filters[1], ['failed', 'interrupted']) === "`status` IN ('failed', 'interrupted') ";
});

check('il filtro Periodo accetta solo le chiavi in elenco', function () use ($filters) {
    $condition = FilterCustom::condition($filters[2], '7d');

    return str_starts_with($condition, "(`started_at` >= '")
        && FilterCustom::condition($filters[2], '') === ''
        && FilterCustom::condition($filters[2], "1' OR '1'='1") === '';
});

summary();
