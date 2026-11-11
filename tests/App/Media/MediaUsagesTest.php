<?php
/** php tests/App/Media/MediaUsagesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Media\MediaUsages;
use Wonder\App\Media\MediaUsageStore;
use Wonder\App\Media\MemoryMediaUsageStore;

/** Registro pulito per ogni test, con la descrizione che non cerca Resource. */
$fresh = static function (): MemoryMediaUsageStore {
    $store = new MemoryMediaUsageStore();
    MediaUsages::useStore($store);
    MediaUsages::describeUsing(static fn (array $owner): array => []);

    return $store;
};

check('sync() registra gli utilizzi di una colonna', function () use ($fresh) {
    $fresh();
    MediaUsages::sync('gst_category', 7, 'image_id', [12]);

    return MediaUsages::inUse(12) && !MediaUsages::inUse(13);
});

check('sync() sostituisce: i media di prima non sono più in uso', function () use ($fresh) {
    $fresh();
    MediaUsages::sync('gst_category', 7, 'gallery', [12, 13]);
    MediaUsages::sync('gst_category', 7, 'gallery', [13, 14]);

    return !MediaUsages::inUse(12) && MediaUsages::inUse(13) && MediaUsages::inUse(14);
});

check('sync() tocca solo la sua tabella, la sua riga e la sua colonna', function () use ($fresh) {
    $fresh();
    MediaUsages::sync('gst_category', 7, 'image_id', [12]);
    MediaUsages::sync('gst_category', 7, 'icon_id', [12]);
    MediaUsages::sync('gst_category', 8, 'image_id', [12]);
    MediaUsages::sync('gst_brand', 7, 'image_id', [12]);
    MediaUsages::sync('gst_category', 7, 'image_id', []);

    return count(MediaUsages::of(12)) === 3;
});

check('sync() con un array vuoto toglie tutto', function () use ($fresh) {
    $fresh();
    MediaUsages::sync('gst_tag', 3, 'image_id', [5]);
    MediaUsages::sync('gst_tag', 3, 'image_id', []);

    return !MediaUsages::inUse(5);
});

check('sync() scarta doppioni e id non validi', function () use ($fresh) {
    $store = $fresh();
    MediaUsages::sync('gst_tag', 3, 'gallery', [5, '5', ' 6 ', 0, -1, '', null, 'abc', '7 OR 1=1', 1.5]);

    return array_column($store->rows, 'media') === [5, 6];
});

check('sync() senza tabella, riga o colonna non scrive', function () use ($fresh) {
    $store = $fresh();
    MediaUsages::sync('', 3, 'image_id', [5]);
    MediaUsages::sync('gst_tag', '', 'image_id', [5]);
    MediaUsages::sync('gst_tag', 3, ' ', [5]);

    return $store->rows === [];
});

check('forget() toglie tutti gli utilizzi del record, solo i suoi', function () use ($fresh) {
    $fresh();
    MediaUsages::sync('gst_category', 7, 'image_id', [12]);
    MediaUsages::sync('gst_category', 7, 'gallery', [13]);
    MediaUsages::sync('gst_category', 8, 'image_id', [14]);
    MediaUsages::forget('gst_category', 7);

    return !MediaUsages::inUse(12) && !MediaUsages::inUse(13) && MediaUsages::inUse(14);
});

check('la riga può avere un id non numerico', function () use ($fresh) {
    $fresh();
    MediaUsages::sync('pages', 'home', 'cover', [9]);
    $usage = MediaUsages::of(9)[0];
    MediaUsages::forget('pages', 'home');

    return $usage['id'] === 'home' && !MediaUsages::inUse(9);
});

check('inUse() e of() con un id non valido rispondono no, senza interrogare lo store', function () {
    MediaUsages::useStore(new class implements MediaUsageStore {
        public function replace(string $table, string $rowId, string $column, array $mediaIds): void {}
        public function forget(string $table, string $rowId): void {}
        public function used(int $mediaId): bool { throw new RuntimeException('store interrogato'); }
        public function owners(int $mediaId): array { throw new RuntimeException('store interrogato'); }
    });

    return !MediaUsages::inUse('abc') && !MediaUsages::inUse(0) && MediaUsages::of('1; DROP') === [] && MediaUsages::message('') === '';
});

check('of() senza Resource ripiega su tabella e id', function () use ($fresh) {
    $fresh();
    MediaUsages::sync('gst_category', 7, 'image_id', [12]);

    return MediaUsages::of(12) === [[
        'resource' => null,
        'label' => 'gst_category',
        'name' => '#7',
        'field' => '',
        'url' => null,
        'table' => 'gst_category',
        'id' => '7',
        'column' => 'image_id',
    ]];
});

check('of() usa la descrizione della Resource, ma tabella, riga e colonna restano quelle del registro', function () use ($fresh) {
    $fresh();
    MediaUsages::describeUsing(static fn (array $owner): array => [
        'label' => 'categoria',
        'name' => 'Estate',
        'field' => 'Immagine',
        'url' => '/backend/categorie/'.$owner['id'].'/',
        'table' => 'altra',
    ]);
    MediaUsages::sync('gst_category', 7, 'image_id', [12]);
    $usage = MediaUsages::of(12)[0];

    return $usage['label'] === 'categoria' && $usage['name'] === 'Estate'
        && $usage['url'] === '/backend/categorie/7/' && $usage['table'] === 'gst_category';
});

check('message() dice dove è usato, vuoto se non lo è', function () use ($fresh) {
    $fresh();
    MediaUsages::describeUsing(static fn (array $owner): array => ['label' => 'categoria', 'name' => 'Estate', 'field' => 'Immagine']);
    MediaUsages::sync('gst_category', 7, 'image_id', [12]);

    return MediaUsages::message(12) === 'In uso in: categoria «Estate» (campo Immagine).'
        && MediaUsages::message(99) === '';
});

check('message() elenca i primi utilizzi e conta gli altri', function () use ($fresh) {
    $fresh();

    foreach (range(1, MediaUsages::MESSAGE_LIMIT + 2) as $row) {
        MediaUsages::sync('gst_tag', $row, 'image_id', [12]);
    }

    $message = MediaUsages::message(12);

    return substr_count($message, 'gst_tag') === MediaUsages::MESSAGE_LIMIT
        && str_ends_with($message, ' e in altri 2 punti.');
});

check('message() è solo testo: i nomi perdono i tag', function () use ($fresh) {
    $fresh();
    MediaUsages::describeUsing(static fn (array $owner): array => ['label' => 'tag', 'name' => '<img src=x onerror=alert(1)>Saldi <b>estivi</b>']);
    MediaUsages::sync('gst_tag', 1, 'image_id', [12]);
    $message = MediaUsages::message(12);

    return !str_contains($message, '<') && !str_contains($message, '>') && str_contains($message, 'Saldi estivi');
});

check('filterCondition(): usata, non usata, tutte', function () {
    $exists = 'EXISTS (SELECT 1 FROM `media_usages` WHERE `media_usages`.`media_id` = `media`.`id`)';

    return MediaUsages::filterCondition(['used']) === $exists
        && MediaUsages::filterCondition(['unused']) === 'NOT '.$exists
        && MediaUsages::filterCondition([]) === ''
        && MediaUsages::filterCondition(['used', 'unused']) === ''
        && MediaUsages::filterCondition(["used' OR 1=1"]) === '';
});

check('useStore(null) torna allo store del database, senza aprire connessioni', function () {
    MediaUsages::useStore(null);

    return MediaUsages::store() instanceof \Wonder\App\Media\DatabaseMediaUsageStore;
});

MediaUsages::useStore(null);
MediaUsages::describeUsing(null);

summary();
