<?php

use Wonder\App\Import\ImportError;
use Wonder\App\Import\Importer;
use Wonder\App\Import\Store;
use Wonder\App\Resources\Config\ImportResource;
use Wonder\Http\Csrf;

// Scrive il prossimo blocco di righe di un caricamento avviato: lo chiama la
// pagina finché lo stato resta `running`.
$routeParameters = is_array($ROUTE_PARAMETERS ?? null) ? $ROUTE_PARAMETERS : [];
$id = (int) ($routeParameters['id'] ?? 0);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$fail = static function (int $code, string $message): never {
    http_response_code($code);
    echo json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
};

if (!Csrf::verify()) {
    $fail(403, 'Sessione scaduta: ricarica la pagina.');
}

$job = $id > 0 ? Store::job($id) : null;

if ($job === null) {
    $fail(404, ImportError::make('missing')->getMessage());
}

try {
    ImportResource::layout((string) $job['layout']);
} catch (ImportError $exception) {
    $fail(403, $exception->getMessage());
}

try {
    $progress = Importer::step($id, 50);
} catch (\Throwable $exception) {
    \Wonder\App\Logger::log($exception, 'backend', 'import', 'ERROR', 'error', ['job' => $id], false);
    $fail(500, 'Qualcosa non ha funzionato.');
}

echo json_encode($progress, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
