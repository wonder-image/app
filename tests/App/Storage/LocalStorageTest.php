<?php
/** php tests/App/Storage/LocalStorageTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Storage\LocalStorage;
use Wonder\App\Storage\StorageException;
use Wonder\App\Storage\StoragePath;

$root = sys_get_temp_dir().'/wonder-storage-'.bin2hex(random_bytes(6));
mkdir($root, 0775, true);
register_shutdown_function(static function () use ($root): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($root);
});

$s = new LocalStorage($root, 'https://example.test/assets/upload/');

$throws = static function (callable $fn): bool {
    try {
        $fn();
    } catch (StorageException) {
        return true;
    }

    return false;
};

check('scrive, legge e crea le cartelle', function () use ($s, $root) {
    $s->write('images/a/b.txt', 'ciao');

    return $s->exists('images/a/b.txt') && $s->read('images/a/b.txt') === 'ciao'
        && is_file($root.'/images/a/b.txt') && $s->size('images/a/b.txt') === 4;
});

check('nessun file temporaneo resta dopo la scrittura', fn () =>
    array_values(array_filter(scandir($root.'/images/a'), fn ($n) => str_ends_with($n, '.tmp'))) === []
);

check('sovrascrive un file esistente', function () use ($s) {
    $s->write('images/a/b.txt', 'nuovo');

    return $s->read('images/a/b.txt') === 'nuovo';
});

check('url e percorso fisico', fn () =>
    $s->url('images/a/b.txt') === 'https://example.test/assets/upload/images/a/b.txt'
    && $s->localPath('images/a/b.txt') === $root.'/images/a/b.txt'
);

check('exists e delete su file mancante non lanciano', fn () =>
    $s->exists('nope.txt') === false && $s->delete('nope.txt') === true
);

check('read/size su file mancante lanciano', fn () =>
    $throws(fn () => $s->read('nope.txt')) && $throws(fn () => $s->size('nope.txt'))
);

check('copy, move e delete', function () use ($s) {
    $s->copy('images/a/b.txt', 'images/c.txt');
    $s->move('images/c.txt', 'documents/d.txt');

    return $s->read('documents/d.txt') === 'nuovo' && !$s->exists('images/c.txt')
        && $s->delete('documents/d.txt') === true && !$s->exists('documents/d.txt');
});

check('writeFile copia (e con move sposta) un file locale', function () use ($s, $root) {
    $src = $root.'/_src.bin';
    file_put_contents($src, 'dati');
    $s->writeFile('videos/x.bin', $src);
    $kept = is_file($src);
    $s->writeFile('videos/y.bin', $src, true);

    return $kept && !is_file($src) && $s->read('videos/x.bin') === 'dati' && $s->read('videos/y.bin') === 'dati';
});

check('list non ricorsivo: solo i file diretti', fn () =>
    $s->list('images') === [] && $s->list('videos') === ['videos/x.bin', 'videos/y.bin']
);

check('list ricorsivo restituisce solo file, ordinati', fn () =>
    $s->list('', true) === ['images/a/b.txt', 'videos/x.bin', 'videos/y.bin']
);

check('list di una cartella mancante è vuoto', fn () => $s->list('nessuna') === []);

check('percorsi pericolosi rifiutati', fn () =>
    $throws(fn () => $s->read('../etc/passwd'))
    && $throws(fn () => $s->write('a/../../x', 'x'))
    && $throws(fn () => $s->write("a\0b", 'x'))
    && $throws(fn () => $s->write('a\\b', 'x'))
    && $throws(fn () => $s->write('', 'x'))
);

check('StoragePath normalizza gli slash doppi', fn () =>
    StoragePath::normalize('/a//b/') === 'a/b' && StoragePath::join('a', '/b') === 'a/b'
);

check('radice non pubblica: url lancia', fn () =>
    (new LocalStorage($root))->url('a.txt') === '/a.txt' // baseUrl '' = indirizzi relativi al sito
    && $throws(fn () => (new LocalStorage($root, null))->url('a.txt'))
);

summary();
