<?php
/** php tests/App/Resources/ErrorReportResourceTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Models\System\ErrorReport;
use Wonder\App\Resources\System\ErrorReportResource;

check('la pagina degli errori sta in Set Up, solo admin', function () {
    $navigation = ErrorReportResource::navigationSchema()->toArray();

    foreach (ErrorReportResource::permissionSchema()->toArray()['backend'] ?? [] as $authorities) {
        if ($authorities !== [] && $authorities !== ['admin']) {
            return false;
        }
    }

    return ($navigation['section_key'] ?? '') === 'set-up'
        && ErrorReportResource::path() === 'app/config/errori'
        && ErrorReportResource::$model === ErrorReport::class;
});

check('gli errori non si creano a mano', function () {
    $pages = (array) (ErrorReportResource::pageSchema()->get('pages') ?? []);
    $attive = array_keys(array_filter($pages));

    return in_array('list', $attive, true)
        && in_array('edit', $attive, true)
        && !in_array('create', $attive, true)
        && !in_array('delete', $attive, true);
});

check('l\'elenco dice servizio, gruppo, occorrenze e quando', function () {
    $colonne = array_map(
        static fn (object $column): string => (string) $column->name,
        ErrorReportResource::tableSchema()
    );

    return in_array('service', $colonne, true)
        && in_array('action', $colonne, true)
        && in_array('audience', $colonne, true)
        && in_array('occurrences', $colonne, true)
        && in_array('last_seen_at', $colonne, true);
});

check('la scheda ha solo l\'interruttore "risolto"', function () {
    $campi = array_map(
        static fn (object $field): string => (string) $field->name,
        ErrorReportResource::formSchema()
    );

    return $campi === ['resolved'];
});

check('segnare risolto scrive data e autore', function () {
    $GLOBALS['USER'] = (object) ['id' => 7, 'authority' => ['admin']];
    $valori = ErrorReportResource::mutateRequestValues(['resolved' => 'true'], 'update');
    unset($GLOBALS['USER']);

    return trim((string) ($valori['resolved_at'] ?? '')) !== ''
        && (int) ($valori['resolved_by'] ?? 0) === 7
        && !isset($valori['resolved']);
});

check('riaprire pulisce data e autore', function () {
    $valori = ErrorReportResource::mutateRequestValues(['resolved' => 'false'], 'update');

    return ($valori['resolved_at'] ?? 'x') === '' && (int) ($valori['resolved_by'] ?? 1) === 0;
});

check('la scheda mostra lo stato di adesso', fn () =>
    (ErrorReportResource::mutateFormValues(['resolved_at' => '2026-09-21 10:00:00'], 'edit')['resolved'] ?? '') === 'true'
    && (ErrorReportResource::mutateFormValues(['resolved_at' => ''], 'edit')['resolved'] ?? '') === 'false'
);

summary();
