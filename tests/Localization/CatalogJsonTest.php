<?php

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\Localization\Catalog\ImportPreview;
use Wonder\Localization\Catalog\ImportStore;
use Wonder\Localization\Catalog\JsonTranslationSource;
use Wonder\Localization\Catalog\Sheet;
use Wonder\Localization\Catalog\TextRules;

$tmp = sys_get_temp_dir().'/wi-catalog-'.bin2hex(random_bytes(4));
$core = "{$tmp}/core";
$site = "{$tmp}/site";

$write = static function (string $file, array $data): void {
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }
    file_put_contents($file, json_encode($data));
};

$write("{$core}/it/auth.json", ['login' => 'Accedi', 'hello' => 'Ciao {{name}}']);
$write("{$core}/it/urls.json", ['home' => '/']);
$write("{$site}/it/home.json", [
    'title' => 'Benvenuto',
    'steps' => [['title' => 'Uno', 'text' => '<b>Primo</b>'], ['title' => 'Due', 'text' => 'Secondo']],
]);
$write("{$site}/it/menu/main.json", ['about' => 'Chi siamo']);
$write("{$site}/en/home.json", ['title' => 'Welcome']);

$source = fn () => new JsonTranslationSource([$core, $site], $site);

echo "Catalogo JSON\n";

check('le voci hanno id puntato, contesto e traduzione', function () use ($source) {
    $e = $source()->entries('it', 'en');

    return $e['home.title']->target === 'Welcome'
        && $e['home.title']->context === 'home'
        && $e['menu.main.about']->context === 'menu/main'
        && $e['auth.login']->target === ''
        && !isset($e['urls.home']);
});

check('le voci di una lista hanno il gruppo', function () use ($source) {
    $e = $source()->entries('it', 'en');

    return $e['home.steps.0.title']->group === 'home.steps' && $e['home.title']->group === null;
});

check('apply scrive solo nel JSON del sito e non cancella', function () use ($source, $site) {
    $errors = $source()->apply('it', 'en', ['auth.login' => 'Sign in', 'home.title' => 'Hi']);
    $auth = json_decode(file_get_contents("{$site}/en/auth.json"), true);
    $home = json_decode(file_get_contents("{$site}/en/home.json"), true);

    return $errors === [] && $auth === ['login' => 'Sign in'] && $home === ['title' => 'Hi'];
});

check('una lista si scrive intera e resta un array', function () use ($source, $site) {
    $source()->apply('it', 'en', [
        'home.steps.0.title' => 'One', 'home.steps.0.text' => '<b>First</b>',
        'home.steps.1.title' => 'Two', 'home.steps.1.text' => 'Second',
    ]);
    $home = json_decode(file_get_contents("{$site}/en/home.json"), true);

    return $home['steps'] === [['title' => 'One', 'text' => '<b>First</b>'], ['title' => 'Two', 'text' => 'Second']]
        && $home['title'] === 'Hi';
});

check('apply rifiuta un id inesistente', fn () => isset($source()->apply('it', 'en', ['nope.x' => 'y'])['nope.x']));

echo "\nRegole del testo\n";

check('placeholder mancante', fn () => TextRules::errors('Ciao {{name}}', 'Hello') !== []);
check('placeholder presenti', fn () => TextRules::errors('Ciao {{name}}', 'Hello {{name}}') === []);
check('tag diversi', fn () => TextRules::errors('<b>Ciao</b>', 'Hello') !== []);
check('tag uguali', fn () => TextRules::errors('<b>Ciao</b> <a href="x">qui</a>', '<a href="y">here</a> <b>Hi</b>') === []);

echo "\nAnteprima\n";

check('categorie', function () use ($source) {
    $p = ImportPreview::build($source()->entries('it', 'en'), [
        ['id' => 'auth.hello', 'source' => 'Ciao {{name}}', 'target' => 'Hello {{name}}'],
        ['id' => 'home.title', 'source' => 'Benvenuto', 'target' => 'Welcome!'],
        ['id' => 'auth.login', 'source' => 'Accedi', 'target' => 'Sign in'],
        ['id' => 'menu.main.about', 'source' => 'Chi siamo!', 'target' => 'About'],
        ['id' => 'nope', 'source' => '', 'target' => 'x'],
        ['id' => 'home.steps.0.title', 'source' => 'Uno', 'target' => ''],
    ]);

    return $p['auth.hello']['status'] === ImportPreview::NEW
        && $p['home.title']['status'] === ImportPreview::MODIFIED
        && $p['auth.login']['status'] === ImportPreview::UNCHANGED
        && $p['menu.main.about']['status'] === ImportPreview::SOURCE_CHANGED
        && $p['nope']['status'] === ImportPreview::ERROR
        && !isset($p['home.steps.0.title']);
});

check('lista incompleta in errore', function () use ($site) {
    $source = new JsonTranslationSource([$site], $site);
    // in fr la lista non esiste: tradurne una sola voce la lascerebbe a metà
    $p = ImportPreview::build($source->entries('it', 'fr'), [
        ['id' => 'home.steps.0.title', 'source' => 'Uno', 'target' => 'Un'],
    ]);

    return $p['home.steps.0.title']['status'] === ImportPreview::ERROR;
});

check('changes ignora errori e id non in anteprima', function () use ($source) {
    $p = ImportPreview::build($source()->entries('it', 'en'), [
        ['id' => 'nope', 'source' => '', 'target' => 'x'],
        ['id' => 'auth.hello', 'source' => 'Ciao {{name}}', 'target' => 'Hello {{name}}'],
    ]);

    return ImportPreview::changes($p, ['nope', 'auth.hello', 'home.title']) === ['auth.hello' => 'Hello {{name}}'];
});

check('apice di formula tolto solo se la sorgente non lo ha', function () use ($source) {
    $p = ImportPreview::build($source()->entries('it', 'en'), [
        ['id' => 'home.title', 'source' => 'Benvenuto', 'target' => "'=Hi"],
    ]);

    return $p['home.title']['value'] === '=Hi';
});

echo "\nFoglio\n";

check('export e rilettura CSV', function () use ($source, $tmp) {
    $rows = Sheet::rows($source()->entries('it', 'en'), 'it', 'en');
    file_put_contents("{$tmp}/t.csv", Sheet::csv($rows));
    $read = Sheet::read("{$tmp}/t.csv", 'csv', 'it', 'en');

    return $rows[0] === ['id', 'contesto', 'it', 'en'] && count($read) === count($rows) - 1
        && $read[0]['id'] === $rows[1][0];
});

check('export e rilettura XLSX con formule neutralizzate', function () use ($tmp) {
    $rows = [['id', 'contesto', 'it', 'en'], ['a', 'x', '=SUM(1)', "'=SUM(1)"]];
    file_put_contents("{$tmp}/t.xlsx", Sheet::xlsx($rows));
    $read = Sheet::read("{$tmp}/t.xlsx", 'xlsx', 'it', 'en');

    return $read === [['id' => 'a', 'context' => 'x', 'source' => '=SUM(1)', 'target' => "'=SUM(1)"]];
});

check('intestazione con lingue sbagliate', function () use ($tmp) {
    file_put_contents("{$tmp}/bad.csv", "id,contesto,it,de\n");
    try {
        Sheet::read("{$tmp}/bad.csv", 'csv', 'it', 'en');
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

echo "\nStore\n";

check('token legato alla sessione', function () use ($tmp) {
    $store = new ImportStore("{$tmp}/store", 'abc');
    $token = $store->save(['x' => 1]);

    return $store->load($token) === ['x' => 1]
        && (new ImportStore("{$tmp}/store", 'other'))->load($token) === null
        && $store->load('../../etc') === null;
});

exec('rm -rf '.escapeshellarg($tmp));
summary();
