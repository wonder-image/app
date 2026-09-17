<?php
/** php tests/Backend/Support/HeaderActionsPartialTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\PageActionNormalizer;

$render = static function (array $ACTIONS): string {
    ob_start();
    include __DIR__ . '/../../../app/view/layout/backend/partials/header-actions.php';

    return (string) ob_get_clean();
};

check('pulsanti con link, classe, icona e nuova scheda', function () use ($render) {
    $html = $render(PageActionNormalizer::normalize([
        ['label' => 'Orari e chiusure', 'href' => '/backend/app/config/opening-hours/1/edit/', 'icon' => 'bi bi-clock', 'class' => 'btn-outline-secondary'],
        ['label' => 'Guida', 'href' => 'https://guide.example.it', 'target' => '_blank'],
    ]));

    return str_contains($html, 'href="/backend/app/config/opening-hours/1/edit/"')
        && str_contains($html, 'btn btn-outline-secondary')
        && str_contains($html, 'bi bi-clock')
        && str_contains($html, 'Orari e chiusure')
        && str_contains($html, 'target="_blank" rel="noopener noreferrer"');
});

check('nessuna azione, nessun markup', fn () => trim($render([])) === '');

summary();
