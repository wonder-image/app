<?php // tests/App/DeferredImageResizeTest.php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\App\Model;
use Wonder\App\Support\SyncSchema;
use Wonder\Data\UploadSchema as Field;
use Wonder\Sql\TableSchema as Column;

if (!defined('RESPONSIVE_IMAGE_SIZES')) {
    define('RESPONSIVE_IMAGE_SIZES', [480, 960, 1440]);
}

if (!defined('RESPONSIVE_IMAGE_WEBP')) {
    define('RESPONSIVE_IMAGE_WEBP', true);
}

final class GalleriaDiProva extends Model
{
    public static string $table = 'test_gallery';
    public static string $folder = 'test/gallery';

    public static function syncSchema(): ?SyncSchema
    {
        return null;
    }

    public static function tableSchema(): array
    {
        return [Column::key('subito')->json(), Column::key('dopo')->json()];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('subito')->image()->extensions(['jpg'])->maxFile(1),
            Field::key('dopo')->image()->extensions(['jpg'])->maxFile(1)->deferResize(),
        ];
    }
}

$format = static function (string $key): array {
    $fields = GalleriaDiProva::dataFields();

    return GalleriaDiProva::prepareFormatFromField($fields[$key] ?? null);
};

check('un campo immagine prende da sé le misure del sito', function () use ($format) {
    $subito = $format('subito');

    return ($subito['resize'] ?? []) === RESPONSIVE_IMAGE_SIZES
        && ($subito['webp'] ?? null) === true;
});

check('chi rimanda non chiede nessuna misura', function () use ($format) {
    $dopo = $format('dopo');

    // Con `resize` vuoto e `webp` falso `uploadFiles()` salta il
    // ridimensionamento: scrive l'originale e basta.
    return ($dopo['resize'] ?? null) === [] && ($dopo['webp'] ?? null) === false;
});

check('rimandare non toglie il resto delle regole del file', function () use ($format) {
    $dopo = $format('dopo');

    return ($dopo['file'] ?? false) === true
        && in_array('jpg', (array) ($dopo['extensions'] ?? []), true);
});

summary();
