<?php
/** php tests/App/Media/RasterIconMigrationTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Media\RasterIconMigration;

$root = sys_get_temp_dir().'/wi-raster-icons-'.bin2hex(random_bytes(4));
mkdir($root.'/icons', 0775, true);
mkdir($root.'/images', 0775, true);

$write = static function (string $path, string $content) use ($root): void {
    file_put_contents($root.'/'.$path, $content);
};
$read = static fn (string $path): ?string => is_file($root.'/'.$path) ? (string) file_get_contents($root.'/'.$path) : null;
$list = static function (string $folder) use ($root): array {
    $files = array_values(array_diff(scandir($root.'/'.$folder) ?: [], ['.', '..']));
    sort($files);

    return $files;
};

$write('icons/carrello.png', 'icona carrello');
$write('icons/carrello-240.png', 'icona carrello 240');
$write('icons/carrello.webp', 'icona carrello webp');
$write('icons/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
$write('icons/cuore.jpg', 'icona cuore');
$write('images/carrello.png', 'foto di un carrello');
$write('images/spiaggia.jpg', 'foto');

// La tabella `media` in memoria: `$update` fa quello che fa l'UPDATE della migrazione.
$media = [
    1 => ['id' => 1, 'slug' => 'carrello', 'type' => 'icon', 'file' => json_encode(['carrello.png'])],
    2 => ['id' => 2, 'slug' => 'logo', 'type' => 'icon', 'file' => json_encode(['logo.svg'])],
    3 => ['id' => 3, 'slug' => 'cuore', 'type' => 'icon', 'file' => json_encode(['cuore.jpg'])],
];
$icons = static function () use (&$media): array {
    return array_values(array_filter($media, static fn (array $row): bool => $row['type'] === 'icon'));
};
$update = static function (array $step) use (&$media): bool {
    $media[$step['id']] = ['id' => $step['id'], 'slug' => $step['slug'], 'type' => 'image', 'file' => json_encode($step['file'])];

    return true;
};

$iconsBefore = $list('icons');

check('le icone raster diventano immagini, gli svg restano icone', function () use ($root, $icons, $update, &$media) {
    $summary = RasterIconMigration::apply($icons(), ['spiaggia'], $root, $update);

    return $summary === ['migrated' => 2, 'renamed' => [1], 'skipped' => []]
        && $media[1]['type'] === 'image' && $media[3]['type'] === 'image' && $media[2]['type'] === 'icon';
});

check('i file sono in images, con le loro misure', function () use ($list) {
    return $list('images') === [
        'carrello-icona-240.png', 'carrello-icona.png', 'carrello-icona.webp',
        'carrello.png', 'cuore.jpg', 'spiaggia.jpg',
    ];
});

check('l\'immagine che c\'era non è stata sovrascritta', function () use ($read) {
    return $read('images/carrello.png') === 'foto di un carrello'
        && $read('images/carrello-icona.png') === 'icona carrello'
        && $read('images/carrello-icona-240.png') === 'icona carrello 240';
});

check('la riga punta al file nuovo e tiene il suo slug', function () use (&$media) {
    return $media[1]['file'] === json_encode(['carrello-icona.png']) && $media[1]['slug'] === 'carrello'
        && $media[3]['file'] === json_encode(['cuore.jpg']);
});

check('in icons non è stato cancellato né cambiato niente', function () use ($list, $read, $iconsBefore) {
    return $list('icons') === $iconsBefore && $read('icons/carrello.png') === 'icona carrello';
});

check('il secondo giro non fa niente', function () use ($root, $icons, $update, $list) {
    $before = $list('images');

    return RasterIconMigration::apply($icons(), ['spiaggia', 'carrello', 'cuore'], $root, $update) === []
        && $list('images') === $before;
});

check('riga non aggiornata dopo la copia: il giro dopo riusa i file già copiati', function () use ($root, $write, $list, $read) {
    $write('icons/stella.png', 'icona stella');
    $write('icons/stella-240.png', 'icona stella 240');
    $row = [['id' => 9, 'slug' => 'stella', 'file' => json_encode(['stella.png'])]];

    $failed = RasterIconMigration::apply($row, [], $root, static fn (array $step): bool => false);
    $files = $list('images');
    $retry = RasterIconMigration::apply($row, [], $root, static fn (array $step): bool => $step['file'] === ['stella.png']);

    return $failed === ['migrated' => 0, 'renamed' => [], 'skipped' => [9 => 'riga non aggiornata']]
        && $retry === ['migrated' => 1, 'renamed' => [], 'skipped' => []]
        && $list('images') === $files
        && $read('images/stella-240.png') === 'icona stella 240';
});

check('cartella images mancante: viene creata', function () use ($root) {
    $other = $root.'/altro';
    mkdir($other.'/icons', 0775, true);
    file_put_contents($other.'/icons/nuova.png', 'icona');

    $summary = RasterIconMigration::apply(
        [['id' => 1, 'slug' => 'nuova', 'file' => json_encode(['nuova.png'])]],
        [],
        $other,
        static fn (array $step): bool => true
    );

    return $summary['migrated'] === 1 && file_get_contents($other.'/images/nuova.png') === 'icona';
});

check('solo svg: le cartelle non si leggono nemmeno', function () {
    return RasterIconMigration::apply(
        [['id' => 2, 'slug' => 'logo', 'file' => json_encode(['logo.svg'])]],
        [],
        '/cartella/che/non/esiste',
        static fn (array $step): bool => throw new RuntimeException('riga toccata')
    ) === [];
});

// Pulizia della cartella temporanea del test.
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
    $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
}
rmdir($root);

summary();
