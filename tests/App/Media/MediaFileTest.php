<?php
/** php tests/App/Media/MediaFileTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

$root = sys_get_temp_dir().'/wi-media-file-'.bin2hex(random_bytes(4));
mkdir($root.'/assets/upload/images', 0775, true);

define('APP_URL', 'https://example.test');
define('APP_VERSION', 'dev');
define('ASSETS_VERSION', 'dev');
define('ROOT', $root);
define('RESPONSIVE_IMAGE_SIZES', [240, 480]);
define('RESPONSIVE_IMAGE_WEBP', true);

use Wonder\App\Media\MediaFile;
use Wonder\App\Media\MediaLibrary;
use Wonder\App\Media\MediaStore;
use Wonder\App\Media\MemoryMediaStore;
use Wonder\Elements\Media\Image;

/**
 * Leggere un media dal suo id, per le pagine del sito, e la ricerca del
 * selettore del backend. In memoria: nessun database.
 */

$store = new MemoryMediaStore([
    ['id' => 10, 'type' => 'image', 'slug' => 'scarpa', 'name' => 'Scarpa', 'alt' => 'Scarpa &quot;rossa&quot; di citt&agrave;', 'file' => 'scarpa.jpg'],
    ['id' => 11, 'type' => 'icon', 'slug' => 'stella', 'name' => 'Stella', 'alt' => 'Stella', 'file' => 'stella.svg'],
    ['id' => 12, 'type' => 'document', 'slug' => 'manuale', 'name' => 'Manuale', 'file' => 'manuale.pdf'],
    ['id' => 13, 'type' => 'video', 'slug' => 'spot', 'name' => 'Spot', 'file' => 'spot.mp4'],
    ['id' => 14, 'type' => 'image', 'slug' => 'tolta', 'name' => 'Tolta', 'file' => 'tolta.jpg', 'deleted' => true],
    ['id' => 15, 'type' => 'image', 'slug' => 'senza-file', 'name' => 'Senza file', 'file' => ''],
    ['id' => 16, 'type' => 'image', 'slug' => 'scarpa-blu', 'name' => 'Scarpa blu', 'alt' => '', 'file' => 'scarpa-blu.png'],
]);
MediaLibrary::useStore($store);

check('un media si legge dal suo id, anche scritto come testo', function () {
    $media = MediaFile::find('10');

    return $media !== null
        && $media->id === 10
        && $media->type === 'image'
        && $media->slug === 'scarpa'
        && $media->name === 'Scarpa'
        && $media->fileName() === 'scarpa.jpg'
        && $media->extension() === 'jpg'
        && $media->path() === 'https://example.test/assets/upload/images/scarpa.jpg';
});

check('il testo alternativo esce come testo semplice, senza le entità del database', function () {
    return MediaFile::find(10)->alt === 'Scarpa "rossa" di città';
});

check('id vuoto, non valido, sconosciuto o di un media eliminato: null, mai un\'eccezione', function () {
    foreach ([null, '', 0, '0', -1, 'abc', '10 OR 1=1', [], 999, 14, 1.5] as $id) {
        if (MediaFile::find($id) !== null) {
            return false;
        }
    }

    return true;
});

check('più media con una lettura sola, senza quelli che mancano', function () use ($store) {
    $store->reads = 0;
    $media = MediaFile::many([10, '12', 999, null, 10, 14, 'x']);

    return $store->reads === 1 && array_keys($media) === [10, 12];
});

check('nessun id valido: nessuna lettura', function () use ($store) {
    $store->reads = 0;

    return MediaFile::many([null, '', 'x']) === [] && $store->reads === 0;
});

check('una libreria che non risponde non rompe la pagina', function () use ($store) {
    MediaLibrary::useStore(static fn (): MediaStore => throw new RuntimeException('database spento'));
    $found = MediaFile::find(10);
    $html = Image::fromMedia(10)->render('wonder');
    MediaLibrary::useStore($store);

    return $found === null && $html === '';
});

check('l\'indirizzo di documenti e video è quello del file', function () {
    return MediaFile::find(12)->url() === 'https://example.test/assets/upload/documents/manuale.pdf'
        && MediaFile::find(13)->url() === 'https://example.test/assets/upload/videos/spot.mp4'
        && MediaFile::find(12)->image() === null
        && MediaFile::find(13)->thumbnailUrl() === '';
});

check('un file su disco prende la versione di Asset::version()', function () use ($root) {
    file_put_contents($root.'/assets/upload/images/scarpa.jpg', 'x');
    touch($root.'/assets/upload/images/scarpa.jpg', 1700000000);

    return MediaFile::find(10)->url() === 'https://example.test/assets/upload/images/scarpa.jpg?v=1700000000';
});

check('anteprima: la misura più piccola se c\'è, altrimenti l\'originale', function () use ($root) {
    $original = MediaFile::find(10)->thumbnailUrl();
    file_put_contents($root.'/assets/upload/images/scarpa-240.jpg', 'x');
    touch($root.'/assets/upload/images/scarpa-240.jpg', 1700000001);
    \Wonder\App\Support\RequestMemo::forget(\Wonder\App\Support\Asset::VERSION_MEMO);

    return str_starts_with($original, 'https://example.test/assets/upload/images/scarpa.jpg')
        && MediaFile::find(10)->thumbnailUrl() === 'https://example.test/assets/upload/images/scarpa-240.jpg?v=1700000001'
        && MediaFile::find(11)->thumbnailUrl() === 'https://example.test/assets/upload/icons/stella.svg';
});

check('Image::fromMedia(): indirizzo, testo alternativo escapato e misure del sito', function () {
    $html = Image::fromMedia(10)->render('wonder');

    return str_contains($html, 'alt="Scarpa &quot;rossa&quot; di città"')
        && str_contains($html, '/assets/upload/images/scarpa.jpg')
        && preg_match('#scarpa-240\.jpg\S* 240w#', $html) === 1;
});

check('Image::fromMedia() accetta anche un MediaFile già letto', function () {
    return str_contains(Image::fromMedia(MediaFile::find(16))->render('bootstrap'), '/assets/upload/images/scarpa-blu.png');
});

check('Image::fromMedia() di un media che manca, senza file o non immagine non stampa niente', function () {
    return Image::fromMedia(999)->render('wonder') === ''
        && Image::fromMedia(null)->render('bootstrap') === ''
        && Image::fromMedia(14)->render('wonder') === ''
        && Image::fromMedia(15)->render('wonder') === ''
        && Image::fromMedia(12)->render('wonder') === '';
});

check('un\'icona svg esce come immagine, senza misure', function () {
    $html = Image::fromMedia(11)->render('wonder');

    return str_contains($html, '/assets/upload/icons/stella.svg') && !str_contains($html, 'stella-240');
});

// --- Ricerca del selettore ---------------------------------------------------

check('la ricerca dà solo id, nome, slug, alt, tipo e anteprima del tipo chiesto', function () {
    $result = MediaLibrary::search('image');
    $ids = array_column($result['items'], 'id');

    return $ids === [16, 15, 10]
        && array_keys($result['items'][0]) === ['id', 'name', 'slug', 'alt', 'type', 'thumbnail']
        && $result['total'] === 3 && $result['page'] === 1 && $result['pages'] === 1;
});

check('il tipo è uno di MediaTypes, altrimenti niente ricerca', function () use ($store) {
    foreach (['audio', '', 'image OR 1=1', "image'--", null, ['image'], 'IMAGE'] as $type) {
        if (MediaLibrary::search($type) !== null) {
            return false;
        }
    }

    return true;
});

check('il testo cercato filtra per nome, slug e testo alternativo', function () {
    return array_column(MediaLibrary::search('image', ' blu ')['items'], 'id') === [16]
        && array_column(MediaLibrary::search('image', 'città')['items'], 'id') === []
        && MediaLibrary::search('image', ['x'])['total'] === 3
        && MediaLibrary::search('document', 'manu')['items'][0]['thumbnail'] === '';
});

check('la ricerca è a pagine e una pagina non valida è la prima', function () {
    $many = new MemoryMediaStore();

    for ($i = 1; $i <= MediaLibrary::PAGE_SIZE + 5; $i++) {
        $many->add(['type' => 'image', 'slug' => 'foto-'.$i, 'name' => 'Foto '.$i, 'file' => 'foto-'.$i.'.jpg']);
    }

    MediaLibrary::useStore($many);
    $first = MediaLibrary::search('image', '', 'abc');
    $second = MediaLibrary::search('image', '', '2');
    $negative = MediaLibrary::search('image', '', -4);

    return count($first['items']) === MediaLibrary::PAGE_SIZE && $first['pages'] === 2 && $first['page'] === 1
        && count($second['items']) === 5 && $second['page'] === 2
        && $negative['page'] === 1;
});

check('il testo cercato è accorciato', function () {
    $seen = null;
    $spy = new class($seen) implements MediaStore {
        public function __construct(public ?string &$seen) {}
        public function rows(array $ids): array { return []; }
        public function slugTaken(string $slug): bool { return false; }
        public function create(string $type, array $values, array $upload): array { return ['id' => 0, 'error' => '']; }
        public function replaceFile(int $id, array $upload): string { return ''; }
        public function setAlt(int $id, string $alt): void {}
        public function search(string $type, string $term, int $limit, int $offset): array
        {
            $this->seen = $term;

            return ['rows' => [], 'total' => 0];
        }
    };
    MediaLibrary::useStore($spy);
    MediaLibrary::search('image', str_repeat('a', 500));

    return strlen((string) $seen) === MediaLibrary::TERM_LENGTH;
});

MediaLibrary::useStore(null);

foreach (glob($root.'/assets/upload/images/*') as $file) {
    unlink($file);
}
rmdir($root.'/assets/upload/images');
rmdir($root.'/assets/upload');
rmdir($root.'/assets');
rmdir($root);

summary();
