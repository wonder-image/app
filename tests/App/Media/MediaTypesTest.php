<?php
/** php tests/App/Media/MediaTypesTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

foreach (['RESPONSIVE_IMAGE_WEBP' => true, 'RESPONSIVE_IMAGE_SIZES' => [240, 480]] as $constant => $value) {
    defined($constant) || define($constant, $value);
}

use Wonder\App\Media\MediaTypes;
use Wonder\App\Resources\Media\DocumentResource;
use Wonder\App\Resources\Media\IconResource;
use Wonder\App\Resources\Media\ImageResource;
use Wonder\App\Resources\Media\VideoResource;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\Support\UploadValidator;

$resources = [
    MediaTypes::IMAGE => ImageResource::class,
    MediaTypes::ICON => IconResource::class,
    MediaTypes::DOCUMENT => DocumentResource::class,
    MediaTypes::VIDEO => VideoResource::class,
];

$format = static fn (string $resource): array => $resource::prepareSchema()['file']['input']['format'] ?? [];

check('i tipi sono quattro: image, icon, document, video', function () {
    return MediaTypes::all() === ['image', 'icon', 'document', 'video']
        && MediaTypes::has('video') && !MediaTypes::has('logo');
});

check('estensioni per tipo', function () {
    return MediaTypes::extensions('image') === ['png', 'jpg', 'jpeg', 'webp']
        && MediaTypes::extensions('icon') === ['svg']
        && MediaTypes::extensions('video') === ['mp4', 'webm', 'mov']
        && in_array('pdf', MediaTypes::extensions('document'), true)
        && in_array('docx', MediaTypes::extensions('document'), true);
});

check('le icone sono solo svg: nessun formato raster', function () {
    return MediaTypes::accepts('icon', 'logo.svg') && MediaTypes::accepts('icon', 'LOGO.SVG')
        && !MediaTypes::accepts('icon', 'logo.png') && !MediaTypes::accepts('icon', 'logo.svgz')
        && !MediaTypes::accepts('icon', 'logo.svg.png') && !MediaTypes::accepts('icon', 'svg');
});

check('nessuna estensione sta in due tipi', function () {
    $all = array_merge(...array_map(MediaTypes::extensions(...), MediaTypes::all()));

    return count($all) === count(array_unique($all));
});

check('nessun tipo ammette estensioni vietate o senza MIME noto', function () {
    $known = UploadValidator::IMAGE_TYPES + UploadValidator::FILE_TYPES + UploadValidator::MEDIA_TYPES;

    foreach (MediaTypes::all() as $type) {
        foreach (MediaTypes::extensions($type) as $extension) {
            $denied = in_array($extension, UploadValidator::DENIED_EXTENSIONS, true) && $extension !== 'svg';

            if ($denied || in_array($extension, UploadValidator::ALWAYS_DENIED_EXTENSIONS, true) || !isset($known[$extension])) {
                echo "    {$type}: {$extension}\n";

                return false;
            }
        }
    }

    return true;
});

check('solo le immagini hanno le misure responsive', function () {
    return MediaTypes::resizes('image')
        && !MediaTypes::resizes('icon') && !MediaTypes::resizes('document') && !MediaTypes::resizes('video');
});

check('cartelle: quelle di prima più videos', function () {
    return array_map(MediaTypes::folder(...), MediaTypes::all()) === ['images', 'icons', 'documents', 'videos'];
});

check('un tipo sconosciuto è un errore, non un tipo senza regole', function () {
    try {
        MediaTypes::extensions('logo');
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

check('ogni Resource accetta le estensioni del suo tipo e salva nella sua cartella', function () use ($resources, $format) {
    foreach ($resources as $type => $resource) {
        if ($format($resource)['extensions'] !== MediaTypes::extensions($type)
            || $format($resource)['max_size'] !== MediaTypes::maxSize($type)
            || $resource::legacyFolder() !== MediaTypes::folder($type)
            || $resource::$condition !== ['deleted' => 'false', 'type' => $type]) {
            echo "    {$type}\n";

            return false;
        }
    }

    return true;
});

check('solo ImageResource ridimensiona e converte in webp', function () use ($format) {
    return $format(ImageResource::class)['resize'] === RESPONSIVE_IMAGE_SIZES
        && $format(ImageResource::class)['webp'] === true
        && empty($format(IconResource::class)['resize']) && empty($format(IconResource::class)['webp'])
        && empty($format(DocumentResource::class)['resize']) && empty($format(DocumentResource::class)['webp'])
        && empty($format(VideoResource::class)['resize']) && empty($format(VideoResource::class)['webp']);
});

check('percorsi di prima non cambiano; immagini e icone espongono alt nelle API', function () {
    $fields = static fn (string $resource, string $action): array => (array) $resource::apiSchema()->get('fields')[$action];

    return ImageResource::path() === 'app/media/images'
        && IconResource::path() === 'app/media/icons'
        && DocumentResource::path() === 'app/media/documents'
        && $fields(ImageResource::class, 'index') === ['id', 'name', 'slug', 'alt', 'type', 'file']
        && $fields(ImageResource::class, 'store') === ['name', 'alt', 'file']
        && $fields(IconResource::class, 'show') === ['id', 'name', 'slug', 'alt', 'type', 'file']
        && $fields(IconResource::class, 'update') === ['name', 'alt', 'file']
        && $fields(DocumentResource::class, 'update') === ['name', 'file'];
});

check('immagini e icone hanno il campo alt nel form, documenti e video no', function () use ($resources) {
    $keys = static fn (string $resource): array => array_map(static fn ($input) => $input->name, $resource::formSchema());

    return $keys(IconResource::class) === ['file', 'name', 'alt']
        && $keys(ImageResource::class) === ['file', 'name', 'alt']
        && $keys(DocumentResource::class) === ['file', 'name']
        && $keys(VideoResource::class) === ['file', 'name'];
});

check('VideoResource: sezione Media, percorso e permessi come gli altri tipi', function () {
    $navigation = VideoResource::navigationSchema()->all();

    return VideoResource::path() === 'app/media/videos'
        && ($navigation['section_key'] ?? null) === 'media'
        && ($navigation['title'] ?? null) === 'Video'
        && VideoResource::allows(['admin'], 'list') && !VideoResource::allows(['editor'], 'list');
});

check('il form di ogni tipo dice al browser cosa accettare', function () {
    $accept = static fn (string $resource): string => (string) $resource::getInput('file')->render('bootstrap');

    return str_contains($accept(IconResource::class), 'accept="image/svg+xml"')
        && str_contains($accept(VideoResource::class), 'accept="video/mp4, video/webm, video/quicktime"')
        && str_contains($accept(DocumentResource::class), '.pdf, .doc, .docx')
        && str_contains($accept(ImageResource::class), 'accept="image/png, image/jpeg"');
});

check('i tipi di input document e media-video hanno la loro whitelist', function () {
    $types = new ReflectionMethod(\Wonder\App\Resource::class, 'fileExtensionsFor');

    return $types->invoke(null, 'document') === MediaTypes::extensions('document')
        && $types->invoke(null, 'media-video') === ['mp4', 'webm', 'mov']
        && $types->invoke(null, 'video') === ['mp4'];
});

check('l\'upload di massa accetta anche svg e documenti', function () {
    $html = FormField::key('file')->fileDragDrop('media', 'classic')->render('bootstrap');

    return str_contains($html, 'image/svg+xml') && str_contains($html, '.docx') && str_contains($html, 'image/webp');
});

summary();
