<?php

use Wonder\App\Scheduler\{Repository, TaskRegistry};

$repository = new Repository();
$_SESSION['scheduler_csrf'] ??= bin2hex(random_bytes(32));
$message = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!is_string($_POST['scheduler_csrf'] ?? null) || !hash_equals($_SESSION['scheduler_csrf'], $_POST['scheduler_csrf'])) {
        http_response_code(403);
        exit('Richiesta non valida.');
    }
    try {
        $repository->request((int) ($_POST['schedule_id'] ?? 0));
        $message = 'Esecuzione richiesta: partira al prossimo passaggio dello scheduler.';
    } catch (\Throwable $error) { $message = $error->getMessage(); }
}
$days = (int) ($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 180], true)) { $days = 30; }
$options = [];
$schedules = $repository->rows("SELECT * FROM scheduler_schedules WHERE deleted = 'false' ORDER BY name");
foreach ($schedules as $schedule) {
    if ($schedule['enabled'] === 'true' && isset(TaskRegistry::all()[$schedule['task_key']])) {
        $options[$schedule['id']] = $schedule['name'];
    }
}
\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/scheduler/dashboard.php', [
    'TITLE' => 'Attivita pianificate', 'DAYS' => $days, 'STATS' => $repository->statistics($days),
    'HEARTBEAT' => $repository->state('heartbeat'), 'OPTIONS' => $options,
    'CSRF' => $_SESSION['scheduler_csrf'], 'MESSAGE' => $message,
])->render();
