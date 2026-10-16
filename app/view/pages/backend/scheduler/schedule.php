<?php

use Wonder\App\Resources\Scheduler\{RunResource, ScheduleResource};
use Wonder\App\Scheduler\{ConfiguredTask, Presentation, TaskRegistry};
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Card, Container, Link, MetricCard, Text};

$schedule = (array) ($ITEM ?? []);
$enabled = ($schedule['enabled'] ?? '') === 'true';
$kind = $schedule['kind'] ?? 'task';
$presets = ['*/5 * * * *' => 'Ogni 5 minuti', '0 * * * *' => 'Ogni ora', '0 0 * * *' => 'Ogni giorno a mezzanotte', '0 3 * * 1' => 'Ogni lunedi alle 03:00'];
$expression = (string) ($schedule['expression'] ?? '');
try {
    $timeout = ConfiguredTask::resolve($schedule)->timeout();
} catch (\Throwable) {
    $timeout = null;
}

\Wonder\View\View::layout('backend.show', [
    'TITLE' => (string) ($schedule['name'] ?? 'Pianificazione'),
    'SUBTITLE' => ($schedule['origin'] ?? '') === 'code' ? 'Pianificazione predefinita' : 'Pianificazione dal backend',
]);

$detail = static fn (string $label, mixed $value) => Text::make(e($label).': <strong>'.e((string) (($value ?? '') !== '' ? $value : 'Non disponibile')).'</strong>')->tag('div')->html();
$parameters = trim((string) ($schedule['parameters'] ?? ''));
$decoded = $parameters === '' ? null : json_decode($parameters);
$parameters = $decoded !== null ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $parameters;

$runs = Text::make('Nessuna esecuzione registrata.')->muted();
if (!empty($schedule['id'])) {
    try {
        $table = RunResource::backendTable(['status', 'started_at', 'duration_ms', 'memory_bytes', 'cpu_ms']);
        $table->title(false);
        $table->titleNResult(false);
        $table->filterSearch(false);
        $table->filterDate(false);
        $table->filterLimit(false);
        $table->filterCustom(false);
        $table->length(10);
        $table->query('`schedule_id` = '.(int) $schedule['id']." AND `deleted` = 'false'");
        $table->queryOrder('id', 'DESC');
        $runs = '<div class="col-12">'.$table->generate(false).'</div>';
    } catch (\Throwable) {
    }
}

echo Layout::renderLayout((new Container())->columns(12)->components([
    (new MetricCard('Prossima scadenza (UTC)', $enabled ? (string) ($schedule['next_due'] ?? '') : 'Sospesa'))->columnSpan(['default' => 12, 'md' => 4]),
    (new MetricCard('Ultimo avvio (UTC)', (string) (($schedule['last_started'] ?? '') ?: 'Mai')))->columnSpan(['default' => 12, 'md' => 4]),
    (new MetricCard('Tempo massimo', $timeout === null ? 'Non disponibile' : $timeout.' s'))->columnSpan(['default' => 12, 'md' => 4]),
    (new Card())->columnSpan(['default' => 12, 'lg' => 6])->gap(2)->components([
        Text::make('Attività')->tag('div')->bold(),
        Text::make('Stato: '.Presentation::state($schedule))->tag('div')->html(),
        $detail('Tipo', ['task' => 'Attività da codice', 'php' => 'Script PHP', 'https' => 'URL HTTPS'][$kind] ?? $kind),
        $kind === 'task'
            ? $detail('Attività', (TaskRegistry::options()[$schedule['task_key'] ?? ''] ?? null) ?: ($schedule['task_key'] ?? null))
            : $detail('Destinazione', $schedule['target'] ?? null),
        $kind === 'https' ? $detail('Metodo HTTP', $schedule['http_method'] ?? null) : '',
        $kind === 'php' ? $detail('Argomenti', str_replace("\n", ' ', (string) ($schedule['arguments'] ?? ''))) : '',
        $detail('Origine', ($schedule['origin'] ?? '') === 'code' ? 'Predefinita (da codice)' : 'Backend'),
    ]),
    (new Card())->columnSpan(['default' => 12, 'lg' => 6])->gap(2)->components([
        Text::make('Quando eseguire')->tag('div')->bold(),
        $detail('Espressione cron', $expression),
        $detail('Frequenza', $presets[$expression] ?? 'Personalizzata'),
        $detail('Fuso orario', $schedule['timezone'] ?? null),
        Text::make('Il cron del server controlla le scadenze ogni minuto; gli orari seguono il fuso selezionato.')->small()->muted(),
    ]),
    $kind !== 'php' ? (new Card())->columnSpan(12)->components([
        Text::make('Parametri')->bold(),
        '<div class="col-12"><pre class="mb-0 text-wrap text-break">'.e($parameters !== '' ? $parameters : '{}').'</pre></div>',
    ]) : '',
    (new Card())->columnSpan(12)->components([
        '<div class="col-12 d-flex justify-content-between align-items-center"><strong>Esecuzioni</strong>'
            .Link::to(ScheduleResource::runsUrl($schedule), 'Registro completo')->render('bootstrap').'</div>',
        $runs,
    ]),
]));
echo Presentation::runAssets();

\Wonder\View\View::end();
