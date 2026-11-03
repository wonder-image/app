<?php

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/MemoryTranslationStore.php';

use Wonder\Localization\Catalog\ModelTranslationSource;
use Wonder\Localization\Contracts\Translatable;
use Wonder\Localization\Content\Translations;
use Wonder\Localization\LanguageContext;

final class SourceProduct implements Translatable
{
    public static $table = 'products';

    public static function getColumns(): array
    {
        return ['name' => [], 'description' => [], 'deleted' => []];
    }

    public static function translatableFields(): array
    {
        return ['name' => 'text', 'description' => 'html'];
    }
}

LanguageContext::defaultLang('it');

$store = new MemoryTranslationStore();
$store->sources['products'] = [
    1 => ['name' => 'Vite', 'description' => '<p>Zincata</p>', 'deleted' => 'false'],
    2 => ['name' => 'Dado', 'description' => '', 'deleted' => 'false'],
    3 => ['name' => 'Rondella', 'description' => '', 'deleted' => 'true'],
];
$store->save('products', 1, 'name', 'en', ['value' => 'Screw', 'status' => 'automatic']);
$source = new ModelTranslationSource(SourceProduct::class, $store);

check('entries: colonna come sorgente, traduzione come destinazione, niente cancellati né vuoti', function () use ($source) {
    $entries = $source->entries('it', 'en');

    return array_keys($entries) === ['1.name', '1.description', '2.name']
        && $entries['1.name']->target === 'Screw' && $entries['1.name']->source === 'Vite'
        && $entries['2.name']->target === '';
});

check('apply: scrive reviewed con l\'hash della colonna', function () use ($source, $store) {
    $errors = $source->apply('it', 'en', ['2.name' => 'Nut', '9.name' => 'X', '1.price' => 'Y']);
    $row = $store->rows['products|2|name|en'];

    return array_keys($errors) === ['9.name', '1.price'] && $row['value'] === 'Nut'
        && $row['status'] === 'reviewed' && $row['source_hash'] === Translations::hash('Dado');
});

check('apply verso la lingua base: errore, nessuna scrittura', function () use ($source, $store) {
    $before = $store->rows;
    $errors = $source->apply('en', 'it', ['1.name' => 'Vite nuova']);

    return $errors === ['1.name' => ModelTranslationSource::BASE_LANGUAGE] && $store->rows === $before;
});

check('sorgente in una lingua tradotta', function () use ($source) {
    $entries = $source->entries('en', 'de');

    return array_keys($entries) === ['1.name', '2.name'] && $entries['2.name']->source === 'Nut';
});

summary();
