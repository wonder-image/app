<?php
/** php tests/App/Media/MediaStableSlugTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

foreach (['RESPONSIVE_IMAGE_WEBP' => true, 'RESPONSIVE_IMAGE_SIZES' => [240, 480]] as $constant => $value) {
    defined($constant) || define($constant, $value);
}

use Wonder\App\Media\MediaTypes;
use Wonder\App\Media\MediaUsages;
use Wonder\App\Media\MemoryMediaUsageStore;
use Wonder\App\Media\MemoryResizeStore;
use Wonder\App\Media\ResizeQueue;
use Wonder\App\Resources\Media\DocumentResource;
use Wonder\App\Resources\Media\IconResource;
use Wonder\App\Resources\Media\ImageResource;
use Wonder\App\Resources\Media\MediaResource;
use Wonder\App\Resources\Media\VideoResource;
use Wonder\App\Support\MediaFileManager;

/**
 * Lo slug di un media nasce con lui e non cambia più: con lui restano il
 * nome del file e il suo indirizzo. Un file sostituito non lascia in giro
 * le misure e il webp della foto di prima.
 */

$upload = ['name' => ['Tramonto.png'], 'type' => ['image/png'], 'tmp_name' => ['/tmp/php123'], 'error' => [0], 'size' => [10]];
$old = ['id' => 7, 'slug' => 'scarpa-rossa', 'name' => 'Scarpa Rossa', 'type' => 'image', 'file' => '["scarpa-rossa.jpg"]'];

check('in creazione lo slug viene dal nome', function () use ($upload) {
    $values = ImageResource::mutateRequestValues(['name' => 'Scarpa Rossa', 'file' => $upload], 'store');

    return $values['slug'] === 'Scarpa Rossa' && $values['type'] === 'image';
});

check('in creazione uno slug dato vince sul nome (campo del form con il suo modello)', function () use ($upload) {
    $values = ImageResource::mutateRequestValues(['name' => 'Tramonto', 'slug' => 'scarpa-rossa', 'file' => $upload], 'store');

    return $values['slug'] === 'scarpa-rossa';
});

check('file sostituito: lo slug resta quello di prima', function () use ($upload, $old) {
    $values = ImageResource::mutateRequestValues(['name' => 'Scarpa Rossa', 'file' => $upload], 'update', 'backend', $old);

    return $values['slug'] === 'scarpa-rossa';
});

check('file sostituito e media rinominato insieme: lo slug resta', function () use ($upload, $old) {
    $values = ImageResource::mutateRequestValues(['name' => 'Tramonto sul mare', 'file' => $upload], 'update', 'backend', $old);

    return $values['slug'] === 'scarpa-rossa' && $values['name'] === 'Tramonto sul mare';
});

check('uno slug mandato in modifica non passa', function () use ($old) {
    $values = ImageResource::mutateRequestValues(['name' => 'Scarpa', 'slug' => 'altro'], 'update', 'api', $old);

    return $values['slug'] === 'scarpa-rossa';
});

check('solo il file, senza nome (sostituzione dal campo di un form): lo slug resta', function () use ($upload, $old) {
    $values = ImageResource::mutateRequestValues(['file' => $upload], 'update', 'backend', $old);

    return $values['slug'] === 'scarpa-rossa' && !array_key_exists('name', $values);
});

check('ogni tipo ha la sua Resource', function () {
    return MediaResource::forType(MediaTypes::IMAGE) === ImageResource::class
        && MediaResource::forType(MediaTypes::ICON) === IconResource::class
        && MediaResource::forType(MediaTypes::DOCUMENT) === DocumentResource::class
        && MediaResource::forType(MediaTypes::VIDEO) === VideoResource::class;
});

check('un tipo sconosciuto non ha Resource', function () {
    try {
        MediaResource::forType('audio');
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

check('le immagini hanno il testo alternativo nel form e nelle API', function () {
    $fields = array_map(static fn (object $field): string => $field->name, ImageResource::formSchema());
    $api = ImageResource::apiSchema()->get('fields');

    return in_array('alt', $fields, true)
        && in_array('alt', $api['store'], true)
        && in_array('alt', $api['show'], true)
        && ImageResource::getLabel('alt') === 'Testo alternativo';
});

// --- File: niente misure della foto di prima ------------------------------

$directory = sys_get_temp_dir().'/wi-media-slug-'.bin2hex(random_bytes(4));
mkdir($directory, 0775, true);
$format = ['dir' => '/', 'webp' => true, 'resize' => [240, 480]];
$touch = static function (string ...$names) use ($directory): void {
    foreach ($names as $name) {
        file_put_contents($directory.'/'.$name, 'x');
    }
};
$files = static function () use ($directory): array {
    $names = array_values(array_diff(scandir($directory), ['.', '..']));
    sort($names);

    return $names;
};
$empty = static function () use ($directory): void {
    foreach (array_diff(scandir($directory), ['.', '..']) as $name) {
        unlink($directory.'/'.$name);
    }
};

check('prima di scrivere su un nome già usato spariscono misure e webp, non il file', function () use ($directory, $format, $touch, $files) {
    $touch('scarpa.jpg', 'scarpa.webp', 'scarpa-240.jpg', 'scarpa-240.webp', 'scarpa-480.jpg', 'scarpa-480.webp', 'scarpa-2.jpg', 'altra-240.jpg');
    MediaFileManager::deleteDerived($directory.'/', 'scarpa.jpg', $format);

    return $files() === ['altra-240.jpg', 'scarpa-2.jpg', 'scarpa.jpg'];
});

check('estensione nuova sullo stesso nome: spariscono anche i webp della foto di prima', function () use ($directory, $format, $touch, $files, $empty) {
    $empty();
    $touch('scarpa.jpg', 'scarpa.webp', 'scarpa-240.jpg', 'scarpa-240.webp');
    MediaFileManager::deleteDerived($directory, 'scarpa.png', $format);

    return $files() === ['scarpa-240.jpg', 'scarpa.jpg'];
});

check('file vecchio tolto dopo il nuovo con lo stesso nome base: i webp nuovi restano', function () use ($directory, $format, $touch, $files, $empty) {
    $empty();
    // Il nuovo `scarpa.png` è già al suo posto con il suo webp.
    $touch('scarpa.jpg', 'scarpa-240.jpg', 'scarpa-480.jpg', 'scarpa.png', 'scarpa.webp', 'scarpa-240.webp', 'scarpa-240.png');
    MediaFileManager::deleteReplacedFiles($directory, $format, ['scarpa.jpg'], ['scarpa.png']);

    return $files() === ['scarpa-240.png', 'scarpa-240.webp', 'scarpa.png', 'scarpa.webp'];
});

check('file vecchio con un altro nome: se ne va con tutte le sue misure', function () use ($directory, $format, $touch, $files, $empty) {
    $empty();
    $touch('vecchia.jpg', 'vecchia.webp', 'vecchia-240.jpg', 'vecchia-240.webp', 'nuova.jpg');
    MediaFileManager::deleteReplacedFiles($directory, $format, ['vecchia.jpg'], ['nuova.jpg']);

    return $files() === ['nuova.jpg'];
});

$empty();
rmdir($directory);

// --- Avvisi nella scheda: stato delle misure ------------------------------

$root = sys_get_temp_dir().'/wi-media-root-'.bin2hex(random_bytes(4));
$queue = new MemoryResizeStore(gmdate('Y-m-d H:i:s'));
ResizeQueue::useStore($queue);
ResizeQueue::useRoot($root);
MediaUsages::useStore(new MemoryMediaUsageStore());
$path = $root.'/assets/upload/images/scarpa.jpg';

check('misure pronte o mai accodate: nessun avviso', function () use ($root) {
    return ImageResource::resizeNotices(['id' => 7, 'file' => '["scarpa.jpg"]'], $root) === [];
});

check('misure in coda: avviso che il sito mostra l\'originale', function () use ($root, $path) {
    ResizeQueue::enqueue($path, [240, 480], true);
    $notices = ImageResource::formNotices(['id' => 7, 'file' => '["scarpa.jpg"]'], 'edit');

    return ResizeQueue::status($path) === ResizeQueue::PENDING
        && ImageResource::resizeNotices(['id' => 7, 'file' => '["scarpa.jpg"]'], $root)[0]['level'] === 'info'
        && (defined('ROOT') || $notices === []);
});

check('misure fallite: avviso con il motivo', function () use ($root, $queue) {
    foreach ($queue->rows as $id => $row) {
        $queue->rows[$id]['status'] = ResizeQueue::FAILED;
        $queue->rows[$id]['error'] = 'Immagine non leggibile.';
    }

    $notices = ImageResource::resizeNotices(['id' => 7, 'file' => '["scarpa.jpg"]'], $root);

    return count($notices) === 1
        && $notices[0]['level'] === 'warning'
        && str_contains($notices[0]['message'], 'non si sono generate')
        && str_contains($notices[0]['message'], 'Immagine non leggibile.');
});

check('documenti e video non hanno misure: nessun avviso', function () use ($root) {
    return DocumentResource::resizeNotices(['id' => 7, 'file' => '["scarpa.jpg"]'], $root) === [];
});

check('in creazione nessun avviso', function () {
    return ImageResource::formNotices(['file' => '["scarpa.jpg"]'], 'create') === [];
});

ResizeQueue::useStore(null);
ResizeQueue::useRoot(null);
MediaUsages::useStore(null);

summary();
