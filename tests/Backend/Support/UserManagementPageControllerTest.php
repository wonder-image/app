<?php
/** php tests/Backend/Support/UserManagementPageControllerTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\UserManagementPageController;

// Gli attributi del form si verificano sul sito (I8): la pagina richiede il runtime del backend e, per gli admin, il DB.
$render = new ReflectionMethod(UserManagementPageController::class, 'render');
$signature = array_map(
    static fn (ReflectionParameter $parameter): string => (string) $parameter->getType().' $'.$parameter->getName()
        .($parameter->isOptional() ? ' = '.var_export($parameter->getDefaultValue(), true) : ''),
    $render->getParameters()
);

check('P15 i parametri di oggi non cambiano', fn () =>
    array_slice($signature, 0, 3) === ['string $mode', '?int $id = NULL', '?array $values = NULL']
);

check('P15 bool $dirty = false e l\'ultimo, facoltativo', fn () =>
    count($signature) === 4 && $signature[3] === 'bool $dirty = false'
);

summary();
