<?php
/** php tests/Support/UploadFormatTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/string/general.php';
require __DIR__.'/../../app/function/file/resize.php';
require __DIR__.'/../../app/function/file/upload.php';

foreach (['RESPONSIVE_IMAGE_WEBP' => true, 'RESPONSIVE_IMAGE_SIZES' => []] as $constant => $value) {
    defined($constant) || define($constant, $value);
}

use Wonder\App\Resources\Media\DocumentResource;
use Wonder\App\Resources\Media\IconResource;
use Wonder\App\Resources\Media\ImageResource;
use Wonder\App\Resources\Media\LogoResource;
use Wonder\App\Support\UploadValidator;

$dir = sys_get_temp_dir().'/wi-upload-format-'.bin2hex(random_bytes(4));
mkdir($dir, 0775, true);

$image = imagecreatetruecolor(40, 20);
imagefill($image, 0, 0, imagecolorallocate($image, 20, 120, 200));
$jpg = $dir.'/vera.jpg';
imagejpeg($image, $jpg);
$png = $dir.'/vera.png';
imagepng($image, $png);
$gif = $dir.'/vera.gif';
imagegif($image, $gif);
unset($image);

$php = $dir.'/shell.php';
file_put_contents($php, "<?php system(\$_GET['c']);");
$fakePng = $dir.'/finta.png';
file_put_contents($fakePng, "<?php echo 'x';");
$mp4 = $dir.'/clip.mp4';
file_put_contents($mp4, "\0\0\0\x18ftypmp42\0\0\0\0mp42isom".str_repeat("\0", 64));
$csv = $dir.'/listino.csv';
file_put_contents($csv, "a,b\n1,2\n");

# Una voce di $_FILES come la costruisce PHP per un input multiplo.
$files = static fn (string $name, string $tmp): array => [
    'name' => [$name], 'type' => ['application/octet-stream'], 'tmp_name' => [$tmp],
    'error' => [UPLOAD_ERR_OK], 'size' => [filesize($tmp)],
];

$upload = static function (array $files, array $format) use ($dir): ?int {
    global $ALERT;
    $ALERT = null;
    uploadFiles($files, $format + ['max_size' => 5], $dir.'/dest', []);
    return $ALERT;
};

check('.php senza whitelist rifiutato (921), anche via uploadFiles', function () use ($php, $files, $upload) {
    return UploadValidator::checkFormat('php', $php, []) === 921
        && UploadValidator::checkFormat('phtml', $php, '') === 921
        && UploadValidator::checkFormat('html', $php, []) === 921
        && UploadValidator::checkFormat('svg', $php, []) === 921
        && UploadValidator::checkFormat('', $php, []) === 921
        && $upload($files('shell.php', $php), []) === 921
        && !is_file(dirname($php).'/dest/shell.php');
});

check('.png con PHP dentro rifiutato (927), con e senza whitelist', function () use ($fakePng, $files, $upload) {
    return UploadValidator::checkFormat('png', $fakePng, ['png', 'jpg']) === 927
        && UploadValidator::checkFormat('png', $fakePng, []) === 927
        && $upload($files('foto.png', $fakePng), ['extensions' => ['png']]) === 927;
});

check('estensione ignota con codice dentro rifiutata (927)', function () use ($php) {
    return UploadValidator::checkFormat('dat', $php, []) === 927
        && UploadValidator::checkFormat('dat', $php, ['dat']) === 927;
});

check('.jpg vero accettato', function () use ($jpg, $png) {
    return UploadValidator::checkFormat('jpg', $jpg, ['png', 'jpg', 'jpeg', 'webp']) === null
        && UploadValidator::checkFormat('jpeg', $jpg, []) === null
        && UploadValidator::checkFormat('png', $png, 'png, jpg') === null;
});

check('.gif vero rifiutato dalla whitelist immagini (921)', function () use ($gif, $files, $upload) {
    return UploadValidator::checkFormat('gif', $gif, ['png', 'jpg', 'jpeg', 'webp']) === 921
        && $upload($files('anim.gif', $gif), ['extensions' => ['png', 'jpg', 'jpeg', 'webp']]) === 921;
});

check('whitelist esplicita rispettata: gif dichiarato e vero passa', function () use ($gif, $jpg) {
    return UploadValidator::checkFormat('gif', $gif, ['gif']) === null
        && UploadValidator::checkFormat('gif', $jpg, ['gif']) === 927;
});

check('mp4 e csv riconosciuti', function () use ($mp4, $csv) {
    return UploadValidator::checkFormat('mp4', $mp4, ['mp4']) === null
        && UploadValidator::checkFormat('csv', $csv, []) === null;
});

check('deny-list estesa: xhtml, xht, svgz, shtml e php3-8 rifiutati senza whitelist (921)', function () use ($dir) {
    $padded = $dir.'/padding.txt';
    file_put_contents($padded, str_repeat("riga di testo\n", 50)."<?php system('id');");
    foreach (['xhtml', 'xht', 'svgz', 'shtml', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8', 'mjs', 'htaccess'] as $extension) {
        if (UploadValidator::checkFormat($extension, $padded, []) !== UploadValidator::EXTENSION) {
            echo "    passa: $extension\n";
            return false;
        }
    }
    return true;
});

check('svgz rifiutato anche se dichiarato (921)', function () use ($dir) {
    $svgz = $dir.'/logo.svgz';
    file_put_contents($svgz, gzencode('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'));
    return UploadValidator::checkFormat('svgz', $svgz, ['svgz', 'svg']) === UploadValidator::EXTENSION;
});

check('csv che inizia con # accettato', function () use ($dir) {
    $csv = $dir.'/commento.csv';
    file_put_contents($csv, "#include,x\n1,2\n");
    return UploadValidator::checkFormat('csv', $csv, ['csv']) === null
        && UploadValidator::checkFormat('csv', $csv, []) === null;
});

check('estensione con spazio, tab, \x0B o punto finale rifiutata (921), anche via uploadFiles', function () use ($dir, $files, $upload) {
    $svg = $dir.'/script.svg';
    file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    foreach (['logo.svg ', "logo.svg\t", "logo.svg\x0B", 'logo.svg.', 'logo.SVG '] as $name) {
        $extension = (string) pathinfo(strtolower($name), PATHINFO_EXTENSION);
        if (UploadValidator::checkFormat($extension, $svg, ['svg']) !== UploadValidator::EXTENSION
            || $upload($files($name, $svg), ['extensions' => ['svg']]) !== UploadValidator::EXTENSION) {
            echo '    passa: '.json_encode($name)."\n";
            return false;
        }
    }
    return !is_dir($dir.'/dest') || glob($dir.'/dest/*') === [];
});

$format = static fn (string $resource, string $key): array => $resource::prepareSchema()[$key]['input']['format'] ?? [];

check('ImageResource e IconResource: png/jpg/jpeg/webp, niente gif e svg', function () use ($format) {
    $expected = ['png', 'jpg', 'jpeg', 'webp'];
    return $format(ImageResource::class, 'file')['extensions'] === $expected
        && $format(IconResource::class, 'file')['extensions'] === $expected
        && $format(ImageResource::class, 'file')['max_size'] === 5;
});

check('DocumentResource resta pdf; Logo tiene le estensioni del modello', function () use ($format) {
    return $format(DocumentResource::class, 'file')['extensions'] === ['pdf']
        && $format(LogoResource::class, 'main')['extensions'] === ['png']
        && $format(LogoResource::class, 'favicon')['extensions'] === ['ico']
        && $format(LogoResource::class, 'app_icon')['extensions'] === ['png', 'jpg', 'jpeg'];
});

check('Media.file è un campo file', function () {
    return \Wonder\App\Models\Media\Media::dataFields()['file'] instanceof \Wonder\Data\Fields\File;
});


$types = new ReflectionMethod(\Wonder\App\Resource::class, 'fileExtensionsFor');
$withType = new ReflectionMethod(\Wonder\App\Resource::class, 'withTypeExtensions');
$input = static fn (string $type) => \Wonder\App\ResourceSchema\FormField::key('file')->fileDragDrop($type);

check('tipi noti: image, video, gallery', function () use ($types) {
    return $types->invoke(null, 'image') === ['png', 'jpg', 'jpeg', 'webp']
        && $types->invoke(null, 'video') === ['mp4']
        && $types->invoke(null, 'gallery') === ['png', 'jpg', 'jpeg', 'webp', 'mp4']
        && $types->invoke(null, 'media') === [];
});

check('estensioni del modello vincono sul tipo dell\'input', function () use ($withType, $input) {
    $brand = $withType->invoke(null, ['extensions' => ['png', 'jpg', 'jpeg', 'webp', 'svg']], $input('image'));
    $bare = $withType->invoke(null, ['file' => true], $input('image'));
    $unknown = $withType->invoke(null, ['file' => true], $input('media'));
    return $brand['extensions'] === ['png', 'jpg', 'jpeg', 'webp', 'svg']
        && $bare['extensions'] === ['png', 'jpg', 'jpeg', 'webp']
        && !isset($unknown['extensions']);
});

array_map('unlink', glob($dir.'/*.*') ?: []);

summary();
