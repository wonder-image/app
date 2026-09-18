<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!isset($ROOT) || !is_file($ROOT.'/vendor/autoload.php')) {
    fwrite(STDERR, "Avviare bin/scheduler.php dalla cartella del sito.\n");
    exit(1);
}
$GLOBALS['ROOT'] = $ROOT;
chdir($ROOT);
$FRONTEND = $BACKEND = false;
require dirname(__DIR__).'/wonder-image.php';
try {
    $worker = null;
    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--worker=([1-9][0-9]*)$/D', $argument, $match)) { $worker = (int) $match[1]; }
        else { throw new InvalidArgumentException('Argomento scheduler non valido.'); }
    }
    if ($worker !== null) { exit(\Wonder\App\Scheduler\Worker::run($worker)); }
    echo json_encode(\Wonder\App\Scheduler\Scheduler::tick()).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
}
