<?php

use Wonder\App\Resources\Scheduler\ScheduleResource;
use Wonder\App\Scheduler\Repository;
use Wonder\Backend\Support\FlashAlert;
use Wonder\Http\Csrf;

$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];
$id = (int) ($routeParameters['id'] ?? 0);
$list = '/backend/app/scheduler/schedules/';
$back = ($_POST['return'] ?? '') === 'view' && $id > 0 ? ScheduleResource::url(['id' => $id]) : $list;

if (!Csrf::verify()) {
    FlashAlert::custom('Sessione scaduta', 'Ricarica la pagina e riprova.', 'danger');
} else {
    try {
        $row = (new Repository())->toggle($id, ($_POST['enabled'] ?? '') === 'true');
        $enabled = $row['enabled'] === 'true';
        FlashAlert::custom(
            $enabled ? 'Pianificazione attivata' : 'Pianificazione sospesa',
            $enabled ? '"'.$row['name'].'" partirà alla prossima scadenza.' : '"'.$row['name'].'" non verrà eseguita finché non la riattivi.'
        );
    } catch (\InvalidArgumentException $exception) {
        FlashAlert::custom('Operazione non riuscita', $exception->getMessage(), 'danger');
    }
}

header('Location: '.$back, true, 303);
exit;
