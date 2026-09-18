<?php

use Wonder\App\PageSchema\SchedulerPageSchema;
use Wonder\Elements\Components\{Button, InfoCard, Link, MetricCard, Text};

\Wonder\View\View::layout('backend.main');
$format = static fn ($number, $factor = 1): string => $number === null ? 'Non disponibile' : number_format((float) $number / $factor, 2, ',', '.');
echo (new InfoCard('Ultimo contatto scheduler (UTC)', $HEARTBEAT ?: 'Mai ricevuto'))->render('bootstrap');
echo Text::make('Storico unico: 180 giorni. Le metriche dei processi esterni possono non essere disponibili.')->render('bootstrap');
echo Link::to('/backend/app/scheduler/schedules/', 'Gestisci pianificazioni')->render('bootstrap');
echo Link::to('/backend/app/scheduler/runs/', 'Registro esecuzioni')->render('bootstrap');
if ($MESSAGE !== '') { echo Text::make($MESSAGE)->render('bootstrap'); }
?>
<form method="get" class="my-3">
    <?= SchedulerPageSchema::periodField($DAYS)->render('bootstrap') ?>
    <?= Button::make('Aggiorna periodo')->type('submit')->render('bootstrap') ?>
</form>
<?php if ($OPTIONS !== []): ?>
<form method="post" class="my-3">
    <?php foreach (SchedulerPageSchema::requestFields($OPTIONS, $CSRF) as $field) { echo $field->render('bootstrap'); } ?>
    <?= Button::make('Esegui appena possibile')->type('submit')->render('bootstrap') ?>
</form>
<?php endif; ?>
<?php
$runs = array_sum(array_column($STATS, 'runs'));
$successes = array_sum(array_column($STATS, 'successes'));
echo (new MetricCard('Esecuzioni nel periodo', $runs))->render('bootstrap');
echo (new MetricCard('Esecuzioni riuscite', $runs ? $format(100 * $successes / $runs).'%' : 'Nessuna esecuzione'))->render('bootstrap');
?>
<div class="table-responsive">
<table class="table">
    <thead><tr><th>Attivita</th><th>Esecuzioni</th><th>Riuscite</th><th>Durata media / massima / totale (s)</th><th>Picco PHP medio / massimo (MiB)</th><th>CPU media / totale (ms)</th></tr></thead>
    <tbody>
    <?php foreach ($STATS as $row): ?>
        <tr>
            <td><?= e($row['task_key']) ?></td><td><?= e($row['runs']) ?></td><td><?= e($row['successes']) ?></td>
            <td><?= e($format($row['average_ms'], 1000).' / '.$format($row['maximum_ms'], 1000).' / '.$format($row['total_ms'], 1000)) ?></td>
            <td><?= e($format($row['average_memory'], 1048576).' / '.$format($row['maximum_memory'], 1048576)) ?></td>
            <td><?= e($format($row['average_cpu']).' / '.$format($row['total_cpu'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php \Wonder\View\View::end(); ?>
