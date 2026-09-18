<?php
/** php tests/Backend/Support/ReadonlyFieldsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Resource;
use Wonder\Backend\Support\ReadonlyFields;

check('normalize: toglie vuoti, spazi e doppioni', fn () =>
    ReadonlyFields::normalize([' hours ', 'hours', '', 'special_hours', 0]) === ['hours', 'special_hours']
);

check('allowsUpdate: sempre vero se la pagina non è in sola lettura', fn () =>
    ReadonlyFields::allowsUpdate(false, []) === true
    && ReadonlyFields::allowsUpdate(false, ['hours']) === true
);

check('allowsUpdate: in sola lettura solo con campi dichiarati', fn () =>
    ReadonlyFields::allowsUpdate(true, []) === false
    && ReadonlyFields::allowsUpdate(true, ['hours']) === true
);

check('shouldDisable: in sola lettura si disabilita tutto tranne i campi dichiarati', fn () =>
    ReadonlyFields::shouldDisable('label', true, ['hours']) === true
    && ReadonlyFields::shouldDisable('hours', true, ['hours']) === false
    && ReadonlyFields::shouldDisable('label', false, []) === false
);

check('filter: tiene solo i campi dichiarati', fn () =>
    ReadonlyFields::filter(['label' => 'MC Nembro', 'hours' => [['id' => '1']]], ['hours'])
        === ['hours' => [['id' => '1']]]
);

check('filter senza campi dichiarati non lascia passare niente', fn () =>
    ReadonlyFields::filter(['label' => 'MC Nembro'], []) === []
);

check('Resource: nessun campo modificabile per impostazione predefinita', fn () =>
    Resource::editableWhenReadonly() === []
);

summary();
