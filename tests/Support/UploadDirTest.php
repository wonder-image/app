<?php
/** php tests/Support/UploadDirTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';

use Wonder\App\Support\UploadDir;

$base = sys_get_temp_dir().'/wi-upload-dir-'.bin2hex(random_bytes(4));
mkdir($base.'/upload', 0775, true);
mkdir($base.'/fuori', 0775, true);
$upload = realpath($base.'/upload');

$cleanup = static function (string $path) use (&$cleanup): void {
    if (is_link($path) || is_file($path)) {
        unlink($path);
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry !== '.' && $entry !== '..') {
            $cleanup($path.'/'.$entry);
        }
    }

    rmdir($path);
};

check("normalize: '' → '/'", fn () => UploadDir::normalize('') === '/');

check("normalize: 'blog/2026' e 'blog/2026/' → '/blog/2026/'", function () {
    return UploadDir::normalize('blog/2026') === '/blog/2026/'
        && UploadDir::normalize('blog/2026/') === '/blog/2026/'
        && UploadDir::normalize('news') === '/news/';
});

check('normalize: rifiuta traversal, assoluti, NUL e backslash', function () {
    foreach (['../x', 'a/../b', '..', './a', 'a/./b', '/etc', '/blog/', "a\0b", 'a\\b', '..\\x', 'a//b'] as $dir) {
        if (UploadDir::normalize($dir) !== null) {
            return false;
        }
    }

    return true;
});

check('normalize: rifiuta caratteri fuori da [A-Za-z0-9._-]', function () {
    foreach (['a b', 'a%2e%2e', 'blog?x', 'é', "a\nb", "blog\n"] as $dir) {
        if (UploadDir::normalize($dir) !== null) {
            return false;
        }
    }

    return true;
});

check('ensure: crea la cartella dentro la radice e la restituisce reale', function () use ($base, $upload) {
    $dir = UploadDir::ensure($base.'/upload', '/blog/2026/');

    return $dir === $upload.'/blog/2026/' && is_dir($dir)
        && UploadDir::ensure($base.'/upload', '/') === $upload.'/';
});

check('ensure: permessi 0775 sulle cartelle create', function () use ($base) {
    $old = umask(0);
    $dir = UploadDir::ensure($base.'/upload', '/permessi/');
    umask($old);

    return $dir !== null && (fileperms($dir) & 0777) === 0775;
});

check('ensure: symlink che esce dalla radice rifiutato', function () use ($base) {
    symlink($base.'/fuori', $base.'/upload/link');

    return UploadDir::ensure($base.'/upload', '/link/') === null
        && UploadDir::ensure($base.'/upload', '/link/sotto/') === null;
});

check('ensure: radice inesistente → null', fn () => UploadDir::ensure($base.'/manca', '/a/') === null);

check('randomName: lunghezza e alfabeto', function () {
    $names = [];

    for ($i = 0; $i < 50; $i++) {
        $name = UploadDir::randomName();

        if (!preg_match('/^[a-z0-9]{16}$/', $name)) {
            return false;
        }

        $names[$name] = true;
    }

    return count($names) === 50 && strlen(UploadDir::randomName(10)) === 10;
});

$cleanup($base);

summary();
