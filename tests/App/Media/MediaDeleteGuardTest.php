<?php
/** php tests/App/Media/MediaDeleteGuardTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

foreach (['RESPONSIVE_IMAGE_WEBP' => true, 'RESPONSIVE_IMAGE_SIZES' => [240, 480]] as $constant => $value) {
    defined($constant) || define($constant, $value);
}

use Wonder\App\Media\MediaUsages;
use Wonder\App\Media\MediaUsageStore;
use Wonder\App\Media\MemoryMediaUsageStore;
use Wonder\App\Resources\Media\DocumentResource;
use Wonder\App\Resources\Media\IconResource;
use Wonder\App\Resources\Media\ImageResource;
use Wonder\App\Resources\Media\VideoResource;
use Wonder\Backend\Filter\FilterCustom;

MediaUsages::useStore(new MemoryMediaUsageStore());
MediaUsages::describeUsing(static fn (array $owner): array => ['label' => 'categoria', 'name' => 'Estate', 'field' => 'Immagine']);
MediaUsages::sync('gst_category', 7, 'image_id', [12]);

$refused = static function (callable $delete): ?string {
    try {
        $delete();
    } catch (RuntimeException $exception) {
        return $exception->getMessage();
    }

    return null;
};

check('un media non usato si può eliminare', function () {
    ImageResource::assertDeletable(99);
    ImageResource::assertFileDeletable(99, 'file');

    return true;
});

check('un media usato no, e il messaggio dice dove', function () use ($refused) {
    $message = $refused(static fn () => ImageResource::assertDeletable(12));

    return $message === 'Questa immagine non si può eliminare. In uso in: categoria «Estate» (campo Immagine). Togli prima questi utilizzi e riprova.';
});

check('il messaggio segue il genere del tipo', function () use ($refused) {
    return str_starts_with((string) $refused(static fn () => IconResource::assertDeletable(12)), 'Questa icona ')
        && str_starts_with((string) $refused(static fn () => DocumentResource::assertDeletable(12)), 'Questo documento ')
        && str_starts_with((string) $refused(static fn () => VideoResource::assertDeletable(12)), 'Questo video ');
});

check('deleteRecord() si ferma prima del database', function () use ($refused) {
    return str_contains((string) $refused(static fn () => ImageResource::deleteRecord(12)), 'non si può eliminare');
});

check('nemmeno il file di un media usato si toglie dall\'elenco', function () use ($refused) {
    return str_contains((string) $refused(static fn () => ImageResource::assertFileDeletable(12, 'file')), 'non si può eliminare');
});

check('tolto l\'utilizzo, il media torna eliminabile', function () {
    MediaUsages::forget('gst_category', 7);
    ImageResource::assertDeletable(12);
    MediaUsages::sync('gst_category', 7, 'image_id', [12]);

    return true;
});

check('registro che non risponde: l\'eliminazione si ferma', function () use ($refused) {
    $working = MediaUsages::store();
    MediaUsages::useStore(new class implements MediaUsageStore {
        public function replace(string $table, string $rowId, string $column, array $mediaIds): void {}
        public function forget(string $table, string $rowId): void {}
        public function used(int $mediaId): bool { throw new RuntimeException('registro giù'); }
        public function owners(int $mediaId): array { throw new RuntimeException('registro giù'); }
    });
    $message = $refused(static fn () => ImageResource::assertDeletable(99));
    $notices = ImageResource::formNotices(['id' => 99], 'edit');
    MediaUsages::useStore($working);

    return $message === 'registro giù' && $notices === [];
});

check('la scheda di un media usato avvisa dove è usato', function () {
    return ImageResource::formNotices(['id' => 12], 'edit') === [[
        'level' => 'info',
        'message' => 'In uso in: categoria «Estate» (campo Immagine).',
    ]]
        && ImageResource::formNotices(['id' => 99], 'edit') === []
        && ImageResource::formNotices([], 'create') === [];
});

$filter = static function (string $resource): array {
    foreach ($resource::tableLayoutSchema()->all()['custom_filters'] as $filter) {
        if ($filter['column'] === 'usage') {
            return $filter;
        }
    }

    return [];
};

check('gli elenchi di Media hanno il filtro usata / non usata con i testi della Resource', function () use ($filter) {
    return $filter(ImageResource::class)['array'] === ['' => 'Tutte', 'used' => 'Usata', 'unused' => 'Non usata']
        && $filter(DocumentResource::class)['array'] === ['' => 'Tutti', 'used' => 'Usato', 'unused' => 'Non usato']
        && $filter(IconResource::class) !== [] && $filter(VideoResource::class) !== [];
});

check('il filtro legge il registro, non una colonna', function () use ($filter) {
    $image = $filter(ImageResource::class);
    $exists = 'EXISTS (SELECT 1 FROM `media_usages` WHERE `media_usages`.`media_id` = `media`.`id`)';

    return FilterCustom::condition($image, 'used') === '('.$exists.') '
        && FilterCustom::condition($image, 'unused') === '(NOT '.$exists.') '
        && FilterCustom::condition($image, '') === ''
        && FilterCustom::condition($image, "used' OR '1'='1") === '';
});

MediaUsages::useStore(null);
MediaUsages::describeUsing(null);

summary();
