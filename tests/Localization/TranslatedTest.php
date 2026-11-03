<?php

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/MemoryTranslationStore.php';

use Wonder\Localization\Concerns\HasTranslations;
use Wonder\Localization\Contracts\Translatable;
use Wonder\Localization\LanguageContext;

final class TranslatedProduct implements Translatable
{
    use HasTranslations;

    public static $table = 'products';

    public static function translatableFields(): array
    {
        return ['name' => 'text', 'description' => 'html'];
    }
}

LanguageContext::defaultLang('it');

$store = new MemoryTranslationStore();
$store->save('products', 1, 'name', 'en', ['value' => 'Steel screw', 'status' => 'reviewed']);
$store->save('products', 1, 'description', 'en', ['value' => '', 'status' => 'automatic']);
$store->save('products', 2, 'name', 'en', ['value' => 'Nut', 'status' => 'automatic']);
$store->save('products', 1, 'name', 'de', ['value' => 'Stahlschraube']);

$rows = [
    ['id' => 1, 'name' => 'Vite in acciaio', 'description' => '<p>Zincata</p>', 'price' => 3],
    ['id' => 2, 'name' => 'Dado', 'description' => ''],
    ['id' => 3, 'name' => 'Rondella', 'description' => ''],
];

check('lista: sovrappone le traduzioni e lascia il resto', function () use ($rows, $store) {
    $before = $store->queries;
    $result = TranslatedProduct::translated($rows, 'en', $store);

    return $result[0]['name'] === 'Steel screw' && $result[0]['price'] === 3
        && $result[1]['name'] === 'Nut' && $result[2]['name'] === 'Rondella'
        && $store->queries === $before + 1;
});

check('traduzione vuota: resta la colonna', function () use ($rows, $store) {
    return TranslatedProduct::translated($rows, 'en', $store)[0]['description'] === '<p>Zincata</p>';
});

check('riga singola', function () use ($rows, $store) {
    $row = TranslatedProduct::translated($rows[0], 'de', $store);

    return $row['name'] === 'Stahlschraube' && $row['id'] === 1;
});

check('lingua base: nessuna query', function () use ($rows, $store) {
    $before = $store->queries;

    return TranslatedProduct::translated($rows, 'it', $store) === $rows && $store->queries === $before;
});

check('campo non selezionato: non viene aggiunto', function () use ($store) {
    $row = TranslatedProduct::translated(['id' => 1, 'price' => 3], 'en', $store);

    return !array_key_exists('name', $row);
});

summary();
