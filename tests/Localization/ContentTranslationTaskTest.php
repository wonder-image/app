<?php

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/MemoryTranslationStore.php';
require __DIR__.'/FakeTranslator.php';

use Wonder\App\Scheduler\Context;
use Wonder\App\Scheduler\Tasks\ContentTranslationTask;
use Wonder\Localization\Content\Translations;
use Wonder\Localization\Contracts\Translatable;
use Wonder\Localization\LanguageContext;

final class TaskProduct implements Translatable
{
    public static $table = 'products';

    public static function getColumns(): array
    {
        return ['name' => [], 'deleted' => []];
    }

    public static function translatableFields(): array
    {
        return ['name' => 'text'];
    }
}

LanguageContext::defaultLang('it');
LanguageContext::addLanguage('it', 'Italiano', '', '');
LanguageContext::addLanguage('en', 'English', '', '');

function taskStore(): MemoryTranslationStore
{
    $store = new MemoryTranslationStore();
    $store->sources['products'] = [
        1 => ['name' => 'Maglia', 'deleted' => 'false'],
        2 => ['name' => 'Felpa', 'deleted' => 'false'],
        3 => ['name' => 'Giacca', 'deleted' => 'false'],
    ];

    return $store;
}

function runTask(MemoryTranslationStore $store, ?FakeTranslator $driver, array $parameters = []): array
{
    $task = new ContentTranslationTask($driver, $store, [TaskProduct::class]);

    return $task->run(new Context($parameters, microtime(true) + 60));
}

check('traduce le righe mancanti e salva hash e motore', function () {
    $store = taskStore();
    $result = runTask($store, new FakeTranslator());
    $row = $store->rows['products|1|name|en'];

    return $result['translated'] === 3 && $row['value'] === '[en] Maglia' && $row['status'] === 'automatic'
        && $row['engine'] === 'fake' && $row['source_hash'] === Translations::hash('Maglia');
});

check('seconda esecuzione: niente da fare', function () {
    $store = taskStore();
    $driver = new FakeTranslator();
    runTask($store, $driver);
    $result = runTask($store, $driver);

    return $result['translated'] === 0 && count($driver->calls) === 1;
});

check('testo base cambiato: automatic si rifà, reviewed passa a review', function () {
    $store = taskStore();
    runTask($store, new FakeTranslator());
    $store->save('products', 2, 'name', 'en', ['value' => 'Hoodie', 'status' => 'reviewed', 'source_hash' => Translations::hash('Felpa')]);
    $store->sources['products'][1]['name'] = 'Maglietta';
    $store->sources['products'][2]['name'] = 'Felpa con cappuccio';
    $result = runTask($store, new FakeTranslator());

    return $result['translated'] === 1 && $result['review'] === 1
        && $store->rows['products|1|name|en']['value'] === '[en] Maglietta'
        && $store->rows['products|2|name|en']['value'] === 'Hoodie'
        && $store->rows['products|2|name|en']['status'] === 'review';
});

check('review non si rifà alle esecuzioni successive', function () {
    $store = taskStore();
    $store->save('products', 1, 'name', 'en', ['value' => 'Shirt', 'status' => 'review', 'source_hash' => 'x']);
    runTask($store, new FakeTranslator());

    return $store->rows['products|1|name|en']['value'] === 'Shirt';
});

check('budget: si ferma al numero di testi chiesto', function () {
    $store = taskStore();
    $result = runTask($store, new FakeTranslator(), ['budget' => 2]);

    return $result['translated'] === 2 && !isset($store->rows['products|3|name|en']);
});

check('senza motore: vocabolario e memoria lavorano, il resto aspetta', function () {
    $store = taskStore();
    $id = $store->saveTerm('Maglia', ['mode' => 'glossary', 'status' => 'confirmed']);
    $store->save('translation_terms', $id, 'term', 'en', ['value' => 'Shirt', 'status' => 'reviewed']);
    $result = runTask($store, null);

    return $result['skipped'] === 'missing_api_key' && $result['local'] === 1 && $result['waiting'] === 2
        && $store->rows['products|1|name|en']['value'] === 'Shirt' && !isset($store->rows['products|2|name|en']);
});

check('candidati: segmento ripetuto oltre la soglia, con la memoria', function () {
    $store = taskStore();
    $store->sources['products'] = [
        1 => ['name' => 'Cotone bio, Rosso', 'deleted' => 'false'],
        2 => ['name' => 'Cotone bio, Blu', 'deleted' => 'false'],
        3 => ['name' => 'Cotone bio, Verde', 'deleted' => 'false'],
        4 => ['name' => 'Cotone bio', 'deleted' => 'false'],
    ];
    $result = runTask($store, new FakeTranslator());
    $term = $store->terms['Cotone bio'] ?? null;

    return $result['candidates'] === 1 && $term['status'] === 'candidate' && $term['occurrences'] === 4
        && $store->rows['translation_terms|'.$term['id'].'|term|en']['value'] === '[en] Cotone bio'
        && !isset($store->terms['Rosso']);
});

check('candidati: un termine scartato non torna', function () {
    $store = taskStore();
    $store->sources['products'] = array_fill(1, 3, ['name' => 'Lino', 'deleted' => 'false']);
    $store->saveTerm('Lino', ['mode' => 'glossary', 'status' => 'rejected']);
    $result = runTask($store, new FakeTranslator());

    return $result['candidates'] === 0 && $store->terms['Lino']['status'] === 'rejected';
});

check('parametri non validi', function () {
    try {
        (new ContentTranslationTask())->validate(['budget' => 0, 'threshold' => 3]);
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

summary();
