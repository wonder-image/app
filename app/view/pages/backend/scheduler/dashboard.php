<?php

use Wonder\App\PageSchema\SchedulerPageSchema;
use Wonder\App\Resources\Scheduler\DashboardResource;
use Wonder\App\Scheduler\Presentation;
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Button, Container, MetricCard};
use Wonder\Elements\Form\Form;

// La pagina legge i propri dati dal dominio (DashboardResource) e li mostra con
// i Componenti wonder-image/app. Le istruzioni del cron stanno nella lista
// delle pianificazioni (`Presentation::cronModal()`).
$DAYS = DashboardResource::period($_GET);
$STATS = DashboardResource::statistics($DAYS);
$LOG_COUNTS = DashboardResource::logCounts($DAYS);
$HEARTBEAT = DashboardResource::heartbeat();

$TITLE = 'Riepilogo';
$SUBTITLE = 'Attività e log degli ultimi '.$DAYS.' giorni';
\Wonder\View\View::layout('backend.show', ['TITLE' => $TITLE, 'SUBTITLE' => $SUBTITLE]);
$format = Presentation::number(...);
$runs = array_sum(array_column($STATS, 'runs'));
$successes = array_sum(array_column($STATS, 'successes'));
$total = array_sum(array_column($STATS, 'total_ms'));
$measured = array_sum(array_column($STATS, 'measured_runs'));
$quarter = ['default' => 12, 'md' => 6, 'xl' => 3];
$third = ['default' => 12, 'md' => 4];
echo Layout::renderLayout((new Container())->columns(12)->components([
    (new MetricCard('Esecuzioni', $runs))->columnSpan($quarter),
    (new MetricCard('Riuscite', $runs ? $format(100 * $successes / $runs).'%' : '--'))->columnSpan($quarter),
    (new MetricCard('Fallite o interrotte', $runs - $successes))->columnSpan($quarter),
    (new MetricCard('Durata media', $measured ? $format($total / $measured, 1000).' s' : '--'))->columnSpan($quarter),
    (new MetricCard('Email inviate', $LOG_COUNTS['emails_sent']))->columnSpan($third),
    (new MetricCard('Accessi riusciti', $LOG_COUNTS['logins']))->columnSpan($third),
    (new MetricCard('Ultimo contatto scheduler (UTC)', $HEARTBEAT ?: 'Mai ricevuto'))->columnSpan($third),
]));
?>
<div class="card border mt-3"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h6 class="mb-0">Statistiche per attività</h6>
        <div class="d-flex flex-wrap gap-2">
            <?= Button::make('Pianificazioni', '/backend/app/scheduler/schedules/')->variant('light')->size('sm')->render('bootstrap') ?>
            <?= Button::make('Registro esecuzioni', '/backend/app/scheduler/runs/')->variant('light')->size('sm')->render('bootstrap') ?>
            <?= Button::make('Log email', '/backend/app/log/email/')->variant('light')->size('sm')->render('bootstrap') ?>
            <?= Button::make('Log accessi', '/backend/app/log/auth-users/')->variant('light')->size('sm')->render('bootstrap') ?>
        </div>
    </div>
    <?= Layout::render((new Form())->columns(12)->components([
        SchedulerPageSchema::periodField($DAYS)->columnSpan(['default' => 12, 'md' => 8, 'lg' => 4]),
        Button::make('Aggiorna periodo')->variant('light')->type('submit')->columnSpan(['default' => 12, 'md' => 4, 'lg' => 2]),
    ]), ['id' => 'scheduler-period', 'method' => 'get']) ?>
    <div class="mt-3"><?= DashboardResource::statisticsTable($DAYS) ?></div>
    <p class="small text-body-secondary mb-0 mt-3">Medie delle esecuzioni concluse, con massimo e totale. I valori non rilevabili sono esclusi dalle medie. Gli accessi contano i login riusciti, inclusi quelli automatici; le email contano gli invii riusciti registrati.</p>
</div></div>
<?php \Wonder\View\View::end(); ?>
