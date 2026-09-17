<?php
/** php tests/Backend/Support/ResourceFormErrorMessageTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\Backend\Support\ResourcePagePresenter;

check('alert testuale mostrato nel form', fn () =>
    ResourcePagePresenter::errorMessage(['alert' => 'Orari, riga 2: orario non valido.']) === 'Orari, riga 2: orario non valido.'
);

check('codici legacy e errori dei campi restano generici', fn () =>
    ResourcePagePresenter::errorMessage(['alert' => '651']) === ''
    && ResourcePagePresenter::errorMessage(['alert' => 952]) === ''
    && ResourcePagePresenter::errorMessage(['email' => 'non valida']) === ''
    && ResourcePagePresenter::errorMessage([]) === ''
);

summary();
