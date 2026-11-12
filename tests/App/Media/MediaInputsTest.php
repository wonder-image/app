<?php
/** php tests/App/Media/MediaInputsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Media\MediaInputs;
use Wonder\App\Media\MediaLibrary;
use Wonder\App\Media\MediaStore;
use Wonder\App\Media\MediaTypes;
use Wonder\App\Media\MediaUpload;
use Wonder\App\Media\MediaUsages;
use Wonder\App\Media\MediaUsageStore;
use Wonder\App\Media\MemoryMediaStore;
use Wonder\App\Media\MemoryMediaUsageStore;
use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\FormField;

/**
 * Il campo media al salvataggio del record: la colonna tiene l'id di un
 * media. Tutto in memoria: né database né file.
 */

final class MediaInputsProduct extends Model
{
    public static string $table = 'gst_product';

    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class MediaInputsProductResource extends Resource
{
    public static string $model = MediaInputsProduct::class;

    public static function formSchema(): array
    {
        return [
            FormField::key('name')->text(),
            FormField::key('image_id')->mediaImage()->mediaSlug('{slug}')->mediaAlt('{name}'),
            FormField::key('manual_id')->mediaDocument(),
        ];
    }
}

final class MediaInputsPlainResource extends Resource
{
    public static string $model = MediaInputsProduct::class;

    public static function formSchema(): array
    {
        return [FormField::key('name')->text()];
    }
}

/** Store che non deve mai essere toccato. */
final class MediaInputsUntouchable implements MediaStore, MediaUsageStore
{
    public function rows(array $ids): array { throw new LogicException('letto'); }
    public function slugTaken(string $slug): bool { throw new LogicException('letto'); }
    public function create(string $type, array $values, array $upload): array { throw new LogicException('scritto'); }
    public function replaceFile(int $id, array $upload): string { throw new LogicException('scritto'); }
    public function setAlt(int $id, string $alt): void { throw new LogicException('scritto'); }
    public function search(string $type, string $term, int $limit, int $offset): array { throw new LogicException('letto'); }
    public function replace(string $table, string $rowId, string $column, array $mediaIds): void { throw new LogicException('scritto'); }
    public function forget(string $table, string $rowId): void { throw new LogicException('scritto'); }
    public function used(int $mediaId): bool { throw new LogicException('letto'); }
    public function owners(int $mediaId): array { throw new LogicException('letto'); }
}

$upload = static fn (string $name = 'Foto Mare.JPG', int $error = UPLOAD_ERR_OK): array => [
    'name' => $name,
    'type' => 'image/jpeg',
    'tmp_name' => $error === UPLOAD_ERR_OK ? '/tmp/php-wi-media' : '',
    'error' => $error,
    'size' => 1200,
];

/** Libreria e registro nuovi, con qualche media già presente. */
$library = static function (): MemoryMediaStore {
    $store = new MemoryMediaStore([
        ['id' => 10, 'type' => 'image', 'slug' => 'scarpa-rossa', 'name' => 'Scarpa Rossa', 'alt' => 'Scarpa rossa', 'file' => 'scarpa-rossa.jpg'],
        ['id' => 11, 'type' => 'image', 'slug' => 'logo', 'name' => 'Logo', 'alt' => 'Logo scritto a mano', 'file' => 'logo.png'],
        ['id' => 12, 'type' => 'document', 'slug' => 'manuale', 'name' => 'Manuale', 'file' => 'manuale.pdf'],
        ['id' => 13, 'type' => 'image', 'slug' => 'vecchia', 'name' => 'Vecchia', 'file' => 'vecchia.jpg', 'deleted' => true],
    ]);
    MediaLibrary::useStore($store);
    MediaUsages::useStore(new MemoryMediaUsageStore());

    return $store;
};

$inputs = static fn (): MediaInputs => MediaInputs::for(MediaInputsProductResource::class);
$product = ['id' => 5, 'name' => 'Scarpa rossa', 'slug' => 'scarpa-rossa', 'image_id' => '10', 'manual_id' => ''];

// --- Dichiarazione ---------------------------------------------------------

check('i type-helper del DSL scelgono il tipo da MediaTypes', function () {
    return FormField::key('a')->mediaImage()->mediaConfig()['type'] === MediaTypes::IMAGE
        && FormField::key('a')->mediaIcon()->mediaConfig()['type'] === MediaTypes::ICON
        && FormField::key('a')->mediaDocument()->mediaConfig()['type'] === MediaTypes::DOCUMENT
        && FormField::key('a')->mediaVideo()->mediaConfig()['type'] === MediaTypes::VIDEO
        && FormField::key('a')->media('video')->mediaConfig()['type'] === MediaTypes::VIDEO;
});

check('un tipo sconosciuto non si dichiara', function () {
    try {
        FormField::key('a')->media('audio');
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

check('i modelli di slug e testo alternativo arrivano alla configurazione', function () {
    return FormField::key('image_id')->mediaImage()->mediaSlug(' {slug} ')->mediaAlt('{name}')->mediaConfig()
        === ['type' => 'image', 'slug' => '{slug}', 'alt' => '{name}'];
});

check('una Resource senza campi media non legge e non scrive niente', function () {
    $never = new MediaInputsUntouchable();
    MediaLibrary::useStore($never);
    MediaUsages::useStore($never);
    $inputs = MediaInputs::for(MediaInputsPlainResource::class);
    $values = ['name' => 'Scarpa', 'image_id' => '10'];

    $same = $inputs->resolve($values, ['id' => 5]) === $values;
    $inputs->sync(5, $values);
    $inputs->forget(5);

    return $inputs->isEmpty() && $same && $inputs->errors() === [];
});

check('un campo media che non è nella richiesta non tocca il record né la libreria', function () use ($inputs, $product) {
    $never = new MediaInputsUntouchable();
    MediaLibrary::useStore($never);
    MediaUsages::useStore($never);
    $media = $inputs();
    $values = $media->resolve(['name' => 'Scarpa blu'], $product);
    $media->sync(5, $values);

    return $values === ['name' => 'Scarpa blu'] && $media->errors() === [];
});

// --- Scelta dalla libreria ------------------------------------------------

check('un id della libreria del tipo giusto passa', function () use ($inputs, $library) {
    $library();
    $media = $inputs();
    $values = $media->resolve(['name' => 'Scarpa', 'image_id' => '11', 'manual_id' => '12']);

    return $values['image_id'] === '11' && $values['manual_id'] === '12' && $media->errors() === [];
});

check('i media dei campi si leggono con una lettura sola', function () use ($inputs, $library, $product) {
    $store = $library();
    $inputs()->resolve(['image_id' => '11', 'manual_id' => '12'], $product);

    return $store->reads === 1;
});

check('un id che non esiste è rifiutato e il valore resta quello di prima', function () use ($inputs, $library, $product) {
    $library();
    $media = $inputs();
    $values = $media->resolve(['image_id' => '999'], $product);

    return $media->errors() === ['image_id' => MediaInputs::MISSING] && $values['image_id'] === '10';
});

check('un media eliminato è rifiutato', function () use ($inputs, $library) {
    $library();
    $media = $inputs();
    $media->resolve(['image_id' => '13']);

    return $media->errors() === ['image_id' => MediaInputs::MISSING];
});

check('un media di un altro tipo è rifiutato', function () use ($inputs, $library) {
    $library();
    $media = $inputs();
    $values = $media->resolve(['image_id' => '12', 'manual_id' => '10']);

    return $media->errors() === ['image_id' => MediaInputs::WRONG_TYPE, 'manual_id' => MediaInputs::WRONG_TYPE]
        && $values['image_id'] === '' && $values['manual_id'] === '';
});

check('un valore che non è un id è rifiutato', function () use ($inputs, $library) {
    $library();

    foreach (['abc', '10 OR 1=1', '-3', '1.5', '0'] as $value) {
        $media = $inputs();
        $media->resolve(['image_id' => $value]);

        if ($media->errors() !== ['image_id' => MediaInputs::INVALID]) {
            return false;
        }
    }

    return true;
});

check('campo svuotato: la colonna si svuota', function () use ($inputs, $library, $product) {
    $library();
    $media = $inputs();
    $values = $media->resolve(['image_id' => ''], $product);

    return $values['image_id'] === '' && $media->errors() === [];
});

// --- File caricato: media nuovo --------------------------------------------

check('un file caricato crea un media del tipo del campo e la colonna prende il suo id', function () use ($inputs, $library, $upload) {
    $store = $library();
    $media = $inputs();
    $values = $media->resolve(['name' => 'Borsa', 'slug' => 'borsa', 'image_id' => '', 'image_id__wi_media_upload' => $upload()]);
    $row = $store->rows[(int) $values['image_id']];

    return $media->errors() === []
        && $row['type'] === 'image'
        && $row['name'] === 'Foto Mare'
        && $row['slug'] === 'borsa'
        && $row['alt'] === 'Borsa'
        && $row['file'] === 'borsa.jpg'
        && !array_key_exists('image_id__wi_media_upload', $values);
});

check('senza modello lo slug viene dal nome del file', function () use ($library, $upload) {
    $store = $library();
    $media = new MediaInputs('gst_product', ['image_id' => ['type' => 'image', 'slug' => '', 'alt' => '']]);
    $values = $media->resolve(['image_id__wi_media_upload' => $upload('Foto_Mare 2024.jpg')]);
    $row = $store->rows[(int) $values['image_id']];

    return $row['slug'] === 'foto-mare-2024' && $row['alt'] === '' && $row['name'] === 'Foto Mare 2024';
});

check('slug già preso: -2, poi -3', function () use ($inputs, $library, $upload) {
    $store = $library();
    $request = ['name' => 'Scarpa rossa', 'slug' => 'scarpa-rossa', 'image_id__wi_media_upload' => $upload()];
    $second = $inputs()->resolve($request)['image_id'];
    $third = $inputs()->resolve($request)['image_id'];

    return $store->rows[(int) $second]['slug'] === 'scarpa-rossa-2'
        && $store->rows[(int) $third]['slug'] === 'scarpa-rossa-3'
        && $store->rows[10]['slug'] === 'scarpa-rossa';
});

check('uniqueSlug: testo vuoto, maiuscole e caratteri strani', function () {
    $taken = static fn (string $slug): bool => in_array($slug, ['media', 'media-2'], true);

    return MediaInputs::uniqueSlug('', $taken) === 'media-3'
        && MediaInputs::uniqueSlug('Scarpa Rossa!', static fn (): bool => false) === 'scarpa-rossa';
});

check('il modello si riempie dai valori della richiesta e, se mancano, da quelli salvati', function () {
    return MediaInputs::fill('{name} {color}', ['name' => ' Scarpa ', 'color' => 'rossa']) === 'Scarpa rossa'
        && MediaInputs::fill('{name} {unknown}', ['name' => 'Scarpa']) === 'Scarpa'
        && MediaInputs::fill('{name}', ['name' => 'Citt&agrave;']) === 'Città'
        && MediaInputs::fill('{name}', ['name' => ['a']]) === '';
});

check('il file con il nome della colonna (API) vale come quello del form', function () use ($inputs, $library, $upload) {
    $store = $library();
    $list = ['name' => ['manuale.pdf'], 'type' => ['application/pdf'], 'tmp_name' => ['/tmp/php-wi'], 'error' => [0], 'size' => [10]];
    $values = $inputs()->resolve(['manual_id' => $list]);

    return $store->rows[(int) $values['manual_id']]['type'] === 'document'
        && $store->rows[(int) $values['manual_id']]['slug'] === 'manuale-2';
});

check('un file di un tipo non ammesso è rifiutato prima di arrivare alla libreria', function () use ($inputs, $library, $upload, $product) {
    $store = $library();
    $count = count($store->rows);
    $media = $inputs();
    $values = $media->resolve(['image_id' => '10', 'image_id__wi_media_upload' => $upload('virus.php')], $product);

    return str_starts_with($media->errors()['image_id'], 'Il file deve essere di tipo png')
        && $values['image_id'] === '10'
        && count($store->rows) === $count;
});

check('un file che la libreria rifiuta ferma il salvataggio con il suo motivo', function () use ($inputs, $library, $upload) {
    $store = $library();
    $store->failWith = 'Dimensione massima del file superata!';
    $media = $inputs();
    $values = $media->resolve(['image_id__wi_media_upload' => $upload()]);

    return $media->errors() === ['image_id' => 'Dimensione massima del file superata!'] && $values['image_id'] === '';
});

check('un file troppo pesante per PHP non passa per "nessun file"', function () use ($inputs, $library, $upload, $product) {
    $library();
    $media = $inputs();
    $values = $media->resolve(['image_id' => '10', 'image_id__wi_media_upload' => $upload('grande.jpg', UPLOAD_ERR_INI_SIZE)], $product);

    return $media->errors() === ['image_id' => MediaInputs::UPLOAD_LOST] && $values['image_id'] === '10';
});

check('il campo file vuoto del form non è un file', function () use ($inputs, $library, $upload, $product) {
    $library();
    $media = $inputs();
    $values = $media->resolve(['image_id' => '10', 'image_id__wi_media_upload' => $upload('', UPLOAD_ERR_NO_FILE)], $product);

    return $values === ['image_id' => '10'] && $media->errors() === [];
});

// --- Sostituire o creare ---------------------------------------------------

check('media usato solo da questo record: il file si sostituisce, id slug e alt restano', function () use ($inputs, $library, $upload, $product) {
    $store = $library();
    MediaUsages::sync('gst_product', 5, 'image_id', [10]);
    $count = count($store->rows);
    $media = $inputs();
    $values = $media->resolve(['name' => 'Scarpa rossa', 'image_id' => '10', 'image_id__wi_media_upload' => $upload('nuova.PNG')], $product);

    return $values['image_id'] === '10'
        && count($store->rows) === $count
        && $store->rows[10]['slug'] === 'scarpa-rossa'
        && $store->rows[10]['alt'] === 'Scarpa rossa'
        && $store->rows[10]['file'] === 'scarpa-rossa.png'
        && $media->errors() === [];
});

check('media condiviso con un altro record: nasce un media nuovo e quello condiviso resta', function () use ($inputs, $library, $upload, $product) {
    $store = $library();
    MediaUsages::sync('gst_product', 5, 'image_id', [10]);
    MediaUsages::sync('gst_product', 6, 'image_id', [10]);
    $values = $inputs()->resolve(['image_id' => '10', 'image_id__wi_media_upload' => $upload('nuova.png')], $product);

    return $values['image_id'] !== '10'
        && $store->rows[10]['file'] === 'scarpa-rossa.jpg'
        && $store->rows[(int) $values['image_id']]['slug'] === 'scarpa-rossa-2';
});

check('media usato da un\'altra colonna dello stesso record: media nuovo', function () use ($inputs, $library, $upload, $product) {
    $store = $library();
    MediaUsages::sync('gst_product', 5, 'image_id', [10]);
    MediaUsages::sync('gst_product', 5, 'cover_id', [10]);
    $values = $inputs()->resolve(['image_id__wi_media_upload' => $upload('nuova.png')], $product);

    return $values['image_id'] !== '10' && $store->rows[10]['file'] === 'scarpa-rossa.jpg';
});

check('registro che non risponde: media nuovo, mai un file sostituito alla cieca', function () use ($inputs, $library, $upload, $product) {
    $store = $library();
    MediaUsages::useStore(new MediaInputsUntouchable());
    $values = $inputs()->resolve(['image_id__wi_media_upload' => $upload('nuova.png')], $product);

    return $values['image_id'] !== '10' && $store->rows[10]['file'] === 'scarpa-rossa.jpg';
});

check('sostituzione rifiutata: errore sul campo e media intatto', function () use ($inputs, $library, $upload, $product) {
    $store = $library();
    $store->failWith = 'Il contenuto del file non corrisponde alla sua estensione!';
    $media = $inputs();
    $values = $media->resolve(['image_id__wi_media_upload' => $upload('nuova.png')], $product);

    return $media->errors() === ['image_id' => 'Il contenuto del file non corrisponde alla sua estensione!']
        && $values['image_id'] === '10'
        && $store->rows[10]['file'] === 'scarpa-rossa.jpg';
});

// --- Testo alternativo ------------------------------------------------------

check('record rinominato: il testo alternativo lo segue se è ancora quello del modello', function () use ($inputs, $library, $product) {
    $store = $library();
    $media = $inputs();
    $values = $media->resolve(['name' => 'Scarpa blu', 'image_id' => '10'], $product);
    $before = $store->rows[10]['alt'];
    $media->sync(5, $values);

    return $before === 'Scarpa rossa' && $store->rows[10]['alt'] === 'Scarpa blu' && $store->rows[10]['slug'] === 'scarpa-rossa';
});

check('testo alternativo scritto a mano: resta', function () use ($inputs, $library, $product) {
    $store = $library();
    $store->rows[10]['alt'] = 'Scarpa da corsa vista di lato';
    $media = $inputs();
    $media->sync(5, $media->resolve(['name' => 'Scarpa blu', 'image_id' => '10'], $product));

    return $store->rows[10]['alt'] === 'Scarpa da corsa vista di lato';
});

check('il confronto non si ferma alle entità salvate nel database', function () use ($inputs, $library) {
    $store = $library();
    $store->rows[10]['alt'] = 'Citt&agrave; alta';
    $media = $inputs();
    $media->sync(5, $media->resolve(['name' => 'Città bassa', 'image_id' => '10'], ['id' => 5, 'name' => 'Citt&agrave; alta', 'image_id' => '10']));

    return $store->rows[10]['alt'] === 'Città bassa';
});

check('salvataggio non riuscito (niente sync): il testo alternativo non cambia', function () use ($inputs, $library, $product) {
    $store = $library();
    $inputs()->resolve(['name' => 'Scarpa blu', 'image_id' => '10'], $product);

    return $store->rows[10]['alt'] === 'Scarpa rossa';
});

check('file sostituito e record rinominato insieme: alt scritto a mano resta, lo slug non segue mai', function () use ($inputs, $library, $upload) {
    $store = $library();
    MediaUsages::sync('gst_product', 5, 'image_id', [11]);
    $media = $inputs();
    $media->sync(5, $media->resolve(
        ['name' => 'Marchio', 'slug' => 'marchio', 'image_id__wi_media_upload' => $upload('nuovo.png')],
        ['id' => 5, 'name' => 'Logo', 'slug' => 'logo', 'image_id' => '11']
    ));

    return $store->rows[11]['alt'] === 'Logo scritto a mano' && $store->rows[11]['slug'] === 'logo';
});

// --- Registro degli utilizzi -------------------------------------------------

check('dopo il salvataggio il registro segue i campi media', function () use ($inputs, $library) {
    $library();
    $media = $inputs();
    $media->sync(5, $media->resolve(['image_id' => '11', 'manual_id' => '12']));

    return MediaUsages::owners(11) === [['table' => 'gst_product', 'id' => '5', 'column' => 'image_id']]
        && MediaUsages::owners(12) === [['table' => 'gst_product', 'id' => '5', 'column' => 'manual_id']];
});

check('campo svuotato: il media torna libero; campo non inviato: resta com\'è', function () use ($inputs, $library, $product) {
    $library();
    MediaUsages::sync('gst_product', 5, 'image_id', [10]);
    MediaUsages::sync('gst_product', 5, 'manual_id', [12]);
    $media = $inputs();
    $media->sync(5, $media->resolve(['image_id' => ''], $product));

    return !MediaUsages::inUse(10) && MediaUsages::inUse(12);
});

check('record eliminato: non usa più nessun media', function () use ($inputs, $library) {
    $library();
    MediaUsages::sync('gst_product', 5, 'image_id', [10]);
    MediaUsages::sync('gst_product', 5, 'manual_id', [12]);
    MediaUsages::sync('gst_product', 6, 'image_id', [10]);
    $inputs()->forget(5);

    return !MediaUsages::inUse(12) && MediaUsages::owners(10) === [['table' => 'gst_product', 'id' => '6', 'column' => 'image_id']];
});

check('un registro che non risponde non annulla un salvataggio riuscito', function () use ($inputs, $library) {
    $library();
    MediaUsages::useStore(new MediaInputsUntouchable());
    $media = $inputs();
    $media->sync(5, ['image_id' => '11']);
    $media->forget(5);

    return true;
});

// --- File caricato -----------------------------------------------------------

check('MediaUpload porta il file del form e quello delle API alla stessa forma', function () use ($upload) {
    $single = MediaUpload::from($upload('C:\\foto\\Mare.jpg'));
    $list = MediaUpload::from(['name' => ['', 'b.png'], 'type' => ['', 'image/png'], 'tmp_name' => ['', '/tmp/b'], 'error' => [4, 0], 'size' => [0, 5]]);

    return $single['tmp_name'] === ['/tmp/php-wi-media']
        && MediaUpload::fileName($single) === 'Mare.jpg'
        && $list['name'] === ['b.png'] && $list['tmp_name'] === ['/tmp/b']
        && MediaUpload::from('12') === null
        && MediaUpload::from($upload('', UPLOAD_ERR_NO_FILE)) === null
        && !MediaUpload::lost($upload('', UPLOAD_ERR_NO_FILE))
        && MediaUpload::lost($upload('x.jpg', UPLOAD_ERR_FORM_SIZE));
});

check('sola lettura: con il campo modificabile passa anche il suo file', function () {
    $media = new MediaInputs('gst_product', ['image_id' => ['type' => 'image', 'slug' => '', 'alt' => '']]);
    $editable = ['image_id', 'title'];
    $posted = ['image_id' => '', 'image_id__wi_media_upload' => ['name' => 'a.jpg'], 'title' => 'x', 'price' => '1', 'price__wi_media_upload' => 'no'];

    $kept = \Wonder\Backend\Support\ReadonlyFields::filter($posted, [...$editable, ...$media->uploadKeys($editable)]);

    return $media->uploadKeys($editable) === ['image_id__wi_media_upload']
        && array_keys($kept) === ['image_id', 'image_id__wi_media_upload', 'title']
        && $media->uploadKeys(['title']) === [];
});

MediaLibrary::useStore(null);
MediaUsages::useStore(null);

summary();
