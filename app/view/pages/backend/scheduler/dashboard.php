<?php

use Wonder\App\PageSchema\SchedulerPageSchema;
use Wonder\App\Resources\Scheduler\DashboardResource;
use Wonder\App\Scheduler\Presentation;
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Button, Card, Container, InfoCard, Link, MetricCard, Text};
use Wonder\Elements\Form\Form;

// La pagina legge i propri dati dal dominio (DashboardResource) e li mostra con
// i Componenti wonder-image/app.
$DAYS = DashboardResource::period($_GET);
$STATS = DashboardResource::statistics($DAYS);
$LOG_COUNTS = DashboardResource::logCounts($DAYS);
$HEARTBEAT = DashboardResource::heartbeat();
$OPTIONS = DashboardResource::runOptions();
$CSRF = DashboardResource::csrfToken();

$TITLE = 'Riepilogo';
$SUBTITLE = 'Attivita e log degli ultimi '.$DAYS.' giorni';
\Wonder\View\View::layout('backend.show', ['TITLE' => $TITLE, 'SUBTITLE' => $SUBTITLE]);
$format = Presentation::number(...);
$runs = array_sum(array_column($STATS, 'runs'));
$successes = array_sum(array_column($STATS, 'successes'));
$total = array_sum(array_column($STATS, 'total_ms'));
$measured = array_sum(array_column($STATS, 'measured_runs'));
echo Layout::renderLayout((new Container())->columns(12)->components([
    (new MetricCard('Esecuzioni', $runs))->columnSpan(['default' => 12, 'md' => 3]),
    (new MetricCard('Riuscite', $runs ? $format(100 * $successes / $runs).'%' : '--'))->columnSpan(['default' => 12, 'md' => 3]),
    (new MetricCard('Fallite o interrotte', $runs - $successes))->columnSpan(['default' => 12, 'md' => 3]),
    (new MetricCard('Durata media', $measured ? $format($total / $measured, 1000).' s' : '--'))->columnSpan(['default' => 12, 'md' => 3]),
    (new MetricCard('Email inviate', $LOG_COUNTS['emails_sent']))->columnSpan(['default' => 12, 'md' => 6]),
    (new MetricCard('Accessi riusciti', $LOG_COUNTS['logins']))->columnSpan(['default' => 12, 'md' => 6]),
]));
?>
<div class="row g-3 mt-0">
    <div class="col-12 col-lg-8">
        <div class="card border"><div class="card-body">
            <h6 class="mb-3">Statistiche per attivita</h6>
            <?= Layout::render((new Form())->columns(12)->components([
                SchedulerPageSchema::periodField($DAYS)->columnSpan(['default' => 12, 'md' => 8]), Button::make('Aggiorna periodo')->variant('light')->type('submit')->columnSpan(['default' => 12, 'md' => 4]),
            ]), ['id' => 'scheduler-period', 'method' => 'get']) ?>
            <div class="mt-3"><?= DashboardResource::statisticsTable($DAYS) ?></div>
            <p class="small text-body-secondary mb-0 mt-3">Medie delle esecuzioni concluse, con massimo e totale. I valori non rilevabili sono esclusi dalle medie. Gli accessi contano i login riusciti, inclusi quelli automatici; le email contano gli invii riusciti registrati.</p>
            <div class="d-flex gap-3 mt-3"><?= Link::to('/backend/app/log/email/', 'Log email')->render('bootstrap') ?><?= Link::to('/backend/app/log/auth-users/', 'Log accessi')->render('bootstrap') ?></div>
        </div></div>
    </div>
    <div class="col-12 col-lg-4">
        <?php
        echo Layout::renderLayout((new Container())->components([
            (new InfoCard('Ultimo contatto scheduler (UTC)', $HEARTBEAT ?: 'Mai ricevuto'))->valueLevel(5),
            (new Card())->gap(2)->components([
                Text::make('Avvio manuale')->tag('div')->bold(),
                Text::make('Richiedi un\'esecuzione aggiuntiva. Partira al prossimo passaggio del cron del server.')->tag('div')->small()->muted(),
                $OPTIONS !== [] ? Layout::render((new Form())->components([
                    ...SchedulerPageSchema::requestFields($OPTIONS, $CSRF),
                    Button::make('Richiedi esecuzione')->type('submit'),
                ]), ['id' => 'scheduler-request']) : Text::make('Nessuna pianificazione attiva.')->muted(),
                Link::to('/backend/app/scheduler/schedules/', 'Gestisci pianificazioni'),
                Link::to('/backend/app/scheduler/runs/', 'Registro esecuzioni'),
            ]),
        ]));
        ?>
    </div>
</div>
<?php \Wonder\View\View::end(); ?>
