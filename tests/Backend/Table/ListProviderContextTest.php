<?php
/** php tests/Backend/Table/ListProviderContextTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\LegacyGlobals;
use Wonder\App\Table as AppTable;
use Wonder\Backend\Table\ListProvider;

function resetSchemas(): void {
    AppTable::$list = [];
    LegacyGlobals::set('TABLE', (object) []);
}

check('senza schemi registrati i metadati sono vuoti', function () {
    resetSchemas();

    return ListProvider::fields('articoli') === [];
});

check('lo schema della tabella viene trovato senza badare alle maiuscole', function () {
    resetSchemas();
    AppTable::$list['articoli'] = ['copertina' => ['input' => ['format' => ['dir' => '/articoli']]]];

    return ListProvider::fields('ARTICOLI') === ['copertina' => ['input' => ['format' => ['dir' => '/articoli']]]];
});

check('lo schema della resource ha la precedenza su quello della tabella', function () {
    resetSchemas();
    AppTable::$list['articoli'] = ['copertina' => ['input' => ['format' => ['dir' => '/articoli', 'resize' => 'thumb']]]];
    AppTable::$list['articoli_schema'] = ['copertina' => ['input' => ['format' => ['dir' => '/media/articoli']]]];

    $fields = ListProvider::fields('articoli', 'articoli_schema');

    return $fields['copertina']['input']['format']['dir'] === '/media/articoli'
        && $fields['copertina']['input']['format']['resize'] === 'thumb';
});

check('lo schema legacy fa da base', function () {
    resetSchemas();
    LegacyGlobals::set('TABLE', (object) ['ARTICOLI' => ['titolo' => ['label' => 'Titolo']]]);
    AppTable::$list['articoli'] = ['copertina' => ['label' => 'Copertina']];

    $fields = ListProvider::fields('articoli');

    return $fields['titolo']['label'] === 'Titolo' && $fields['copertina']['label'] === 'Copertina';
});

check('il redirect porta pagina, lunghezza e ordinamento della lista', function () {
    $url = ListProvider::redirect('/backend/articoli/', 'example.it', 'articoli', [
        'page' => 2,
        'length' => 25,
        'search' => '',
        'order' => 'creation',
        'order_direction' => 'desc',
    ]);

    return str_starts_with($url, 'https://www.example.it/backend/articoli/?')
        && str_contains($url, 'articoli__page=2')
        && str_contains($url, 'articoli__length=25')
        && str_contains($url, 'articoli__order=creation')
        && str_contains($url, 'articoli__order_dir=desc');
});

check('i parametri gia\' presenti nell\'url sopravvivono', function () {
    $url = ListProvider::redirect('/backend/articoli/?stato=bozza', 'example.it', 'articoli', [
        'page' => 0,
        'length' => 10,
        'search' => '',
        'order' => 'creation',
        'order_direction' => 'desc',
    ]);

    return str_contains($url, 'stato=bozza') && str_contains($url, 'articoli__page=0');
});

check('la pagina della lista sostituisce quella vecchia nell\'url', function () {
    $url = ListProvider::redirect('/backend/articoli/?articoli__page=7', 'example.it', 'articoli', [
        'page' => 1,
        'length' => 10,
        'search' => '',
        'order' => 'creation',
        'order_direction' => 'desc',
    ]);

    return str_contains($url, 'articoli__page=1') && !str_contains($url, 'articoli__page=7');
});

check('il www dell\'host non viene raddoppiato', function () {
    $url = ListProvider::redirect('/backend/articoli/', 'www.example.it', 'articoli', ['page' => 0, 'length' => 10]);

    return str_starts_with($url, 'https://www.example.it/backend/articoli/?');
});

summary();
