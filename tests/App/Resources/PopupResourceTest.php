<?php
/**
 * Le pagine del popup sono obbligatorie anche lato server.
 *
 *   php tests/App/Resources/PopupResourceTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../app/function/helper.php';

use Wonder\App\Resources\Communications\PopupResource;
use Wonder\App\TranslationBootstrap;

$root = dirname(__DIR__, 3);
TranslationBootstrap::preload($root . '/app', $root);

echo "PopupResource\n";

/** L'eccezione di mutateRequestValues, o null se passa. */
function popupRejection(array $values, string $action): ?InvalidArgumentException
{
    try {
        PopupResource::mutateRequestValues($values, $action, 'backend', $action === 'update' ? ['id' => 1] : null);
    } catch (InvalidArgumentException $error) {
        return $error;
    }

    return null;
}

$empty = [
    'senza chiave' => [],
    'null' => ['pages' => null],
    'stringa vuota' => ['pages' => ''],
    'JSON vuoto' => ['pages' => '[]'],
    'array vuoto' => ['pages' => []],
    'hidden del form' => ['pages' => ['']],
    'solo spazi' => ['pages' => ['', '  ']],
];

check('il messaggio viene dal lang JSON, non è la chiave', fn () =>
    __t('popup.pages_required') === 'Scegli almeno una pagina in cui mostrare il popup.'
);

foreach ($empty as $label => $values) {
    check("creazione senza pagine ({$label}): rifiutata con 422 e il messaggio del lang", function () use ($values) {
        $error = popupRejection(['name' => 'Saldi'] + $values, 'store');

        return $error !== null
            && $error->getCode() === 422
            && $error->getMessage() === __t('popup.pages_required');
    });
}

foreach ($empty as $label => $values) {
    if ($values === []) {
        continue;
    }

    check("modifica che svuota le pagine ({$label}): rifiutata", fn () =>
        popupRejection($values, 'update')?->getCode() === 422
    );
}

check('modifica che non manda le pagine: passa (API parziale)', fn () =>
    popupRejection(['visible' => 'false'], 'update') === null
);

check('pagine valide come array, JSON o chiave singola: passano', fn () =>
    popupRejection(['pages' => ['', 'home']], 'store') === null
        && popupRejection(['pages' => '["__all__"]'], 'store') === null
        && popupRejection(['pages' => 'home'], 'update') === null
);

check('con pagine valide lo slug nasce ancora dal nome', fn () =>
    (PopupResource::mutateRequestValues(['name' => 'Saldi', 'pages' => ['', 'home']], 'store')['slug'] ?? null) === 'Saldi'
);

summary();
