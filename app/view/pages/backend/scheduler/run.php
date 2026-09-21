<?php

use Wonder\App\Scheduler\{Presentation, Repository};
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Card, Container, Link, MetricCard, Text};

$run = (array) ($ITEM ?? []);
$schedule = empty($run['schedule_id']) ? null : ((new Repository())->rows("SELECT name, id FROM scheduler_schedules WHERE id = ? AND deleted = 'false'", [$run['schedule_id']])[0] ?? null);
$TITLE = 'Esecuzione #'.($run['id'] ?? '');
$SUBTITLE = $schedule['name'] ?? $run['task_key'] ?? '';
\Wonder\View\View::layout('backend.show', ['TITLE' => $TITLE, 'SUBTITLE' => $SUBTITLE]);
$detail = static fn (string $label, mixed $value) => Text::make(e($label).': <strong>'.e((string) ($value ?? 'Non disponibile')).'</strong>')->tag('div')->html();
$pretty = static function (?string $json): string {
    if (!$json) { return 'Nessun risultato restituito.'; }
    $decoded = json_decode($json);
    return json_last_error() === JSON_ERROR_NONE ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $json;
};
echo Layout::renderLayout((new Container())->columns(12)->components([
    (new Card())->columnSpan(['default' => 12, 'lg' => 6])->gap(2)->components([
        Text::make('Attivita')->tag('div')->bold(), $detail('Codice', $run['task_key'] ?? null),
        Text::make('Esito: '.Presentation::status($run['status'] ?? ''))->tag('div')->html(),
        $schedule ? Link::to('/backend/app/scheduler/schedules/'.$schedule['id'].'/edit/', $schedule['name']) : Text::make('Pianificazione non piu disponibile')->muted(),
    ]),
    (new Card())->columnSpan(['default' => 12, 'lg' => 6])->gap(2)->components([
        Text::make('Tempi di esecuzione')->tag('div')->bold(),
        $detail('Avvio (UTC)', $run['started_at'] ?? null), $detail('Fine (UTC)', $run['finished_at'] ?? null),
        Text::make('Storico conservato per 180 giorni.')->small()->muted(),
    ]),
    (new MetricCard('Durata', Presentation::number($run['duration_ms'] ?? null, 1000, 's')))->columnSpan(['default' => 12, 'md' => 4]),
    (new MetricCard('Picco memoria PHP', Presentation::number($run['memory_bytes'] ?? null, 1048576, 'MiB')))->columnSpan(['default' => 12, 'md' => 4]),
    (new MetricCard('Tempo CPU', Presentation::number($run['cpu_ms'] ?? null, 1, 'ms')))->columnSpan(['default' => 12, 'md' => 4]),
    (new Card())->columnSpan(12)->components([
        Text::make('Output')->bold(),
        '<div class="col-12"><pre class="mb-0 text-wrap text-break">'.e(($run['output'] ?? '') ?: 'Nessun output prodotto.').'</pre></div>',
    ]),
    (new Card())->columnSpan(12)->components([
        Text::make('Risultato')->bold(),
        '<div class="col-12"><pre class="mb-0 text-wrap text-break">'.e($pretty($run['result'] ?? null)).'</pre></div>',
        Text::make('Per script esterni e richieste HTTPS, memoria e CPU non descrivono il consumo del servizio remoto. I dati non rilevabili sono indicati come non disponibili.')->small()->muted(),
    ]),
]));
\Wonder\View\View::end();
