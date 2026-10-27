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
// formToArray() senza il bootstrap completo, come in RichTextFieldTest.
require __DIR__ . '/../../../app/function/string/common.php';
require __DIR__ . '/../../../app/function/string/sanitize.php';
require __DIR__ . '/../../../app/function/sql.php';

use Wonder\Api\Support\ResourceApiPresenter;
use Wonder\App\Model;
use Wonder\App\Models\Communications\Popup;
use Wonder\App\Resources\Communications\PopupResource;
use Wonder\App\TranslationBootstrap;

$root = dirname(__DIR__, 3);
TranslationBootstrap::preload($root . '/app', $root);

echo "PopupResource\n";

/** L'eccezione di mutateRequestValues, o null se passa. */
function popupRejection(array $values, string $action, string $context = 'backend'): ?InvalidArgumentException
{
    try {
        PopupResource::mutateRequestValues($values, $action, $context, $action === 'update' ? ['id' => 1] : null);
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
    'JSON null' => ['pages' => 'null'],
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

check('modifica che non manda le pagine: passa', fn () =>
    popupRejection(['visible' => 'false'], 'update') === null
);

check('pagine valide come array con l\'hidden del form: passano', fn () =>
    popupRejection(['pages' => ['', 'home']], 'store') === null
);

check('pagine valide come JSON: passano', fn () =>
    popupRejection(['pages' => '["__all__"]'], 'store') === null
);

check('pagina singola come stringa: passa', fn () =>
    popupRejection(['pages' => 'home'], 'update') === null
);

check('la pagina «0» resta una pagina', fn () =>
    popupRejection(['pages' => ['0']], 'store') === null
);

// Prima l'API scartava il payload (apiSchema() aveva solo `index`) e il
// controllo era escluso lì; ora dichiara i campi scrivibili e vale ovunque.
check('API: creazione senza pagine rifiutata con lo stesso messaggio', function () {
    $error = popupRejection(['name' => 'Saldi'], 'store', 'api');

    return $error !== null
        && $error->getCode() === 422
        && $error->getMessage() === __t('popup.pages_required');
});

check('API: modifica con pagine vuote rifiutata', fn () =>
    popupRejection(['pages' => []], 'update', 'api')?->getCode() === 422
);

check('API: modifica che non manda le pagine: passa', fn () =>
    popupRejection(['visible' => 'false'], 'update', 'api') === null
);

check('API: creazione con pagine valide passa', fn () =>
    popupRejection(['name' => 'Saldi', 'pages' => ['home']], 'store', 'api') === null
);

$presenter = new ResourceApiPresenter(PopupResource::class);
$posted = ['name' => 'Saldi', 'pages' => ['home'], 'images' => ['x'], 'position' => 3, 'id' => 9];

check('API store: `name` e `pages` arrivano al mutator, `images`/`position`/`id` no', fn () =>
    $presenter->writableValues($posted, 'store') === ['name' => 'Saldi', 'pages' => ['home']]
);

check('API update: `pages` arriva al mutator', fn () =>
    array_key_exists('pages', $presenter->writableValues(['pages' => []], 'update'))
);

check('backend: la stessa creazione senza pagine è rifiutata', fn () =>
    popupRejection([], 'store', 'backend')?->getCode() === 422
);

check('con pagine valide lo slug nasce ancora dal nome', fn () =>
    (PopupResource::mutateRequestValues(['name' => 'Saldi', 'pages' => ['', 'home']], 'store')['slug'] ?? null) === 'Saldi'
);

/** `pages` come lo salva prepare (formato json del Model), dopo il mutator. */
function savedPages(array $values): ?string
{
    $GLOBALS['ALERT'] = null;
    $GLOBALS['NAME'] = (object) ['folder' => ''];
    $GLOBALS['CHARACTERS'] = [];

    $field = null;
    foreach (Popup::dataSchema() as $candidate) {
        if ($candidate->key === 'pages') { $field = $candidate; }
    }

    $saved = formToArray('popup_test', $values, [
        'pages' => ['input' => ['format' => Model::prepareFormatFromField($field)]],
    ]);

    return isset($saved['pages']) ? json_encode(json_decode($saved['pages'], true)) : null;
}

foreach ([
    'stringa form-urlencoded' => ['home', '["home"]'],
    'stringa "0"' => ['0', '["0"]'],
    'JSON di un elenco' => ['["home"]', '["home"]'],
] as $label => [$posted, $expected]) {
    check("api: pages $label passa e si salva come $expected", function () use ($posted, $expected) {
        $values = PopupResource::mutateRequestValues(['name' => 'Saldi', 'pages' => $posted], 'store', 'api');

        return savedPages($values) === $expected;
    });
}

check('api: senza normalizzare, la stringa `home` si salverebbe vuota (il bug)', fn () =>
    savedPages(['pages' => 'home']) === '[]'
);

check('api: il JSON `null` resta un elenco vuoto, non la pagina "null"', fn () =>
    popupRejection(['name' => 'Saldi', 'pages' => 'null'], 'store', 'api')?->getCode() === 422
);

foreach (['true', 'false'] as $visible) {
    check("api: visible '$visible' passa", fn () =>
        popupRejection(['visible' => $visible], 'update', 'api') === null
    );
}

check('api: visible booleano JSON diventa la stringa del form', fn () =>
    (PopupResource::mutateRequestValues(['visible' => true], 'update', 'api')['visible'] ?? null) === 'true'
    && (PopupResource::mutateRequestValues(['visible' => false], 'update', 'api')['visible'] ?? null) === 'false'
);

foreach (['1' => '1', 'yes' => 'yes', 'TRUE' => 'TRUE', 'vuoto' => '', 'null' => null, 'intero' => 1] as $label => $visible) {
    check("api: visible $label è rifiutato con 422 e messaggio tradotto", function () use ($visible) {
        $error = popupRejection(['visible' => $visible], 'update', 'api');

        return $error?->getCode() === 422 && $error->getMessage() === __t('popup.visible_invalid')
            && $error->getMessage() !== 'popup.visible_invalid';
    });
}

check('backend: il select manda già \'true\'/\'false\' e passa', fn () =>
    popupRejection(['name' => 'Saldi', 'pages' => ['home'], 'visible' => 'true'], 'store', 'backend') === null
);

check('visible assente non viene controllato', fn () =>
    !array_key_exists('visible', PopupResource::mutateRequestValues(['pages' => ['home']], 'update', 'api'))
);

summary();
