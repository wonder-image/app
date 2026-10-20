<?php
/** php tests/Support/UploadValidatorTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/string/general.php';
require __DIR__.'/../../app/function/file/resize.php';

use Wonder\App\Support\UploadValidator;

$dir = sys_get_temp_dir().'/wi-upload-validator-'.bin2hex(random_bytes(4));
mkdir($dir, 0775, true);

$png = $dir.'/vera.png';
$image = imagecreatetruecolor(2400, 1200);
imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 30));
imagepng($image, $png);
$jpg = $dir.'/vera.jpg';
imagejpeg($image, $jpg);
unset($image);

$fake = $dir.'/finta.png';
file_put_contents($fake, "<?php echo 'x';");
$pdf = $dir.'/doc.pdf';
file_put_contents($pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
$txt = $dir.'/note.txt';
file_put_contents($txt, "appunti\n");
$empty = $dir.'/vuoto.png';
touch($empty);

$envelope = static fn (string $name, string $tmp, int $error = UPLOAD_ERR_OK): array => [
    'name' => $name,
    'type' => 'application/octet-stream',
    'tmp_name' => $tmp,
    'error' => $error,
    'size' => is_file($tmp) ? filesize($tmp) : 0,
];

check('immagine PNG e JPG vere accettate', function () use ($envelope, $png, $jpg) {
    return UploadValidator::validate($envelope('foto.png', $png), UploadValidator::IMAGE_TYPES) === null
        && UploadValidator::validate($envelope('foto.JPG', $jpg), UploadValidator::IMAGE_TYPES) === null
        && UploadValidator::validate($envelope('foto.jpeg', $jpg), UploadValidator::IMAGE_TYPES) === null;
});

check('estensione fuori whitelist → 921', function () use ($envelope, $png) {
    return UploadValidator::validate($envelope('foto.gif', $png), UploadValidator::IMAGE_TYPES) === 921
        && UploadValidator::validate($envelope('shell.php', $png), UploadValidator::IMAGE_TYPES) === 921
        && UploadValidator::validate($envelope('foto.svg', $png), UploadValidator::IMAGE_TYPES) === 921
        && UploadValidator::validate($envelope('senza', $png), UploadValidator::IMAGE_TYPES) === 921
        && UploadValidator::validate($envelope('pagina.html', $png), UploadValidator::FILE_TYPES) === 921;
});

check('dimensione oltre il limite → 922', function () use ($envelope, $png) {
    return UploadValidator::validate($envelope('foto.png', $png), UploadValidator::IMAGE_TYPES, 100) === 922
        && UploadValidator::validate($envelope('foto.png', $png, UPLOAD_ERR_INI_SIZE), UploadValidator::IMAGE_TYPES) === 922
        && UploadValidator::validate($envelope('foto.png', $png, UPLOAD_ERR_FORM_SIZE), UploadValidator::IMAGE_TYPES) === 922;
});

check('contenuto non coerente con l\'estensione → 927', function () use ($envelope, $fake, $png, $txt) {
    return UploadValidator::validate($envelope('finta.png', $fake), UploadValidator::IMAGE_TYPES) === 927
        && UploadValidator::validate($envelope('foto.webp', $png), UploadValidator::IMAGE_TYPES) === 927
        && UploadValidator::validate($envelope('doc.pdf', $txt), UploadValidator::FILE_TYPES) === 927;
});

check('allegati: PDF e TXT veri accettati', function () use ($envelope, $pdf, $txt) {
    return UploadValidator::validate($envelope('doc.pdf', $pdf), UploadValidator::FILE_TYPES) === null
        && UploadValidator::validate($envelope('note.txt', $txt), UploadValidator::FILE_TYPES) === null;
});

check('busta non valida, multipla, errore upload o file vuoto → 920', function () use ($envelope, $png, $empty) {
    return UploadValidator::validate(null, UploadValidator::IMAGE_TYPES) === 920
        && UploadValidator::validate('x', UploadValidator::IMAGE_TYPES) === 920
        && UploadValidator::validate(['name' => ['a.png'], 'tmp_name' => [$png], 'error' => [0], 'size' => [1]], UploadValidator::IMAGE_TYPES) === 920
        && UploadValidator::validate(['name' => 'a.png'], UploadValidator::IMAGE_TYPES) === 920
        && UploadValidator::validate($envelope('foto.png', $png, UPLOAD_ERR_PARTIAL), UploadValidator::IMAGE_TYPES) === 920
        && UploadValidator::validate($envelope('foto.png', $empty), UploadValidator::IMAGE_TYPES) === 920
        && UploadValidator::validate($envelope('foto.png', '/non/esiste.png'), UploadValidator::IMAGE_TYPES) === 920;
});

check('codice 927 presente in tutte le lingue', function () {
    foreach (['it', 'en', 'de', 'fr', 'es'] as $locale) {
        $json = json_decode((string) file_get_contents(__DIR__."/../../resources/lang/{$locale}/notifications.json"), true);

        if (!isset($json['927']['text'], $json['927']['title']) || ($json['927']['type'] ?? '') !== 'danger') {
            return false;
        }
    }

    return true;
});

check('resizeImage (GD): riduce dentro il riquadro e non ingrandisce', function () use ($dir, $png) {
    $small = resizeImage($png, 600, 600, $dir, 'small');
    $large = resizeImage($png, 4000, 4000, $dir, 'large');
    [$sw, $sh] = getimagesize($dir.'/small.png') ?: [0, 0];
    [$lw, $lh] = getimagesize($dir.'/large.png') ?: [0, 0];

    return $small && $large && $sw === 600 && $sh === 300 && $lw === 2400 && $lh === 1200;
});

check('resizeImage: file non immagine → false, file mancante → false', function () use ($dir, $fake) {
    return resizeImage($fake, 600, 600, $dir, 'rotta') === false
        && !is_file($dir.'/rotta.png')
        && resizeImage($dir.'/manca.png', 600, 600, $dir, 'x') === false;
});


/**
 * Esegue un handler editorjs in un processo separato: restituisce
 * "status|json|file creati sotto la radice upload".
 */
$runEditor = static function (string $handler, string $field, array $file, string $header) use ($dir): string {
    $root = $dir.'/upload-'.bin2hex(random_bytes(3));
    mkdir($root, 0775, true);
    $script = tempnam(sys_get_temp_dir(), 'wi-editorjs-');

    if (!is_string($script)) {
        return '';
    }

    $autoload = var_export(__DIR__.'/../../vendor/autoload.php', true);
    $functions = var_export(__DIR__.'/../../app/function/file/resize.php', true);
    $general = var_export(__DIR__.'/../../app/function/string/general.php', true);
    $target = var_export(__DIR__.'/../../app/http/api/backend/'.$handler, true);
    $files = var_export([$field => $file], true);
    $rootExport = var_export($root, true);
    $headerExport = var_export($header, true);

    $source = <<<PHP
<?php
require {$autoload};
require {$general};
require {$functions};
\$_SERVER['REQUEST_METHOD'] = 'POST';
\$_SERVER['HTTP_DIR'] = {$headerExport};
\$_FILES = {$files};
\$PATH = (object) ['rUpload' => {$rootExport}, 'upload' => 'https://sito.it/assets/upload'];
register_shutdown_function(function () use (\$PATH): void {
    \$found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(\$PATH->rUpload, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    echo '|'.http_response_code().'|'.iterator_count(\$found);
});
require {$target};
PHP;

    file_put_contents($script, $source);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1', $output);
    unlink($script);

    return implode("\n", $output);
};

check('editorjs-image: Dir con traversal → 400 success:0, niente su disco', function () use ($runEditor, $envelope, $png) {
    return $runEditor('editorjs-image.php', 'image', $envelope('foto.png', $png), '../fuori')
        === '{"success":0,"alert":920}|400|0';
});

check('editorjs-image: PNG finto → 422 alert 927, niente su disco', function () use ($runEditor, $envelope, $fake) {
    return $runEditor('editorjs-image.php', 'image', $envelope('finta.png', $fake), 'blog')
        === '{"success":0,"alert":927}|422|0';
});

check('editorjs-image: file non caricato via HTTP → 400 prima del move', function () use ($runEditor, $envelope, $png) {
    return $runEditor('editorjs-image.php', 'image', $envelope('foto.png', $png), '')
        === '{"success":0,"alert":920}|400|0';
});

check('editorjs-file: estensione html → 422 alert 921, niente su disco', function () use ($runEditor, $envelope, $txt) {
    return $runEditor('editorjs-file.php', 'file', $envelope('pagina.html', $txt), '')
        === '{"success":0,"alert":921}|422|0';
});

check('editorjs-file: file non caricato via HTTP → 400', function () use ($runEditor, $envelope, $pdf) {
    return $runEditor('editorjs-file.php', 'file', $envelope('doc.pdf', $pdf), 'docs')
        === '{"success":0,"alert":920}|400|0';
});

$removeTree = static function (string $path) use (&$removeTree): void {
    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        is_dir($path.'/'.$entry) ? $removeTree($path.'/'.$entry) : unlink($path.'/'.$entry);
    }

    rmdir($path);
};
$removeTree($dir);

summary();
