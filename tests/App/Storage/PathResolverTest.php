<?php
/** php tests/App/Storage/PathResolverTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Storage\LocalStorage;
use Wonder\App\Storage\PathResolver;
use Wonder\App\Storage\PrefixedStorage;
use Wonder\App\Storage\StorageException;
use Wonder\App\Storage\StorageManager;
use Wonder\App\Storage\StorageScope;

$root = sys_get_temp_dir().'/wonder-resolver-'.bin2hex(random_bytes(6));
mkdir($root, 0775, true);
register_shutdown_function(static function () use ($root): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) {
        $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    }
    rmdir($root);
});

$throws = static function (callable $fn): bool {
    try {
        $fn();
    } catch (StorageException) {
        return true;
    }

    return false;
};

check('scope: validazione di tenant e ambiente', fn () =>
    (new StorageScope('mio-sito', 'staging'))->prefix() === 'mio-sito/staging'
    && $throws(fn () => new StorageScope('Mio Sito', 'staging'))
    && $throws(fn () => new StorageScope('a/b', 'staging'))
    && $throws(fn () => new StorageScope('..', 'staging'))
    && $throws(fn () => new StorageScope('a..b', 'staging'))
    && $throws(fn () => new StorageScope('', 'staging'))
    && $throws(fn () => new StorageScope('sito', 'prod'))
);

check('flat: prefisso vuoto e chiave invariata', function () {
    $r = new PathResolver('flat', new StorageScope('sito', 'production'));

    return $r->prefix() === '' && $r->key('images/a.jpg') === 'images/a.jpg';
});

check('scoped: {tenant}/{ambiente}/percorso', function () {
    $r = new PathResolver('scoped', new StorageScope('sito', 'staging'));

    return $r->prefix() === 'sito/staging' && $r->key('images/a.jpg') === 'sito/staging/images/a.jpg';
});

check('forEnvironment cambia solo l\'ambiente', function () {
    $r = (new PathResolver('scoped', new StorageScope('sito', 'staging')))->forEnvironment('production');

    return $r->key('a.jpg') === 'sito/production/a.jpg' && $r->scope()->tenant === 'sito';
});

check('layout non valido rifiutato', fn () => $throws(fn () => new PathResolver('x', new StorageScope('sito', 'local'))));

check('flat: apply restituisce il driver stesso', function () use ($root) {
    $d = new LocalStorage($root);

    return (new PathResolver('flat', new StorageScope('sito', 'local')))->apply($d) === $d;
});

check('scoped: apply mette il prefisso su disco, trasparente per chi usa', function () use ($root, $throws) {
    $d = new LocalStorage($root, 'https://x.test/up');
    $s = (new PathResolver('scoped', new StorageScope('sito', 'staging')))->apply($d);
    $s->write('images/a.txt', 'ok');

    return $s instanceof PrefixedStorage
        && is_file($root.'/sito/staging/images/a.txt')
        && $s->read('images/a.txt') === 'ok'
        && $s->url('images/a.txt') === 'https://x.test/up/sito/staging/images/a.txt'
        && $s->list('', true) === ['images/a.txt']
        && $s->list('images') === ['images/a.txt']
        && $d->exists('sito/staging/images/a.txt')
        && $throws(fn () => $s->exists('../x'));
});

check('scoped: ambienti diversi non si vedono tra loro', function () use ($root) {
    $d = new LocalStorage($root);
    $prod = (new PathResolver('scoped', new StorageScope('sito', 'production')))->apply($d);
    $stg = (new PathResolver('scoped', new StorageScope('sito', 'staging')))->apply($d);
    $prod->write('p.txt', 'p');

    return $prod->exists('p.txt') && !$stg->exists('p.txt');
});

// Parità con Wonder\App\Path: con la configurazione predefinita percorsi e URL sono quelli di sempre.
define('ROOT', '/var/www/sito');
define('APP_URL', 'https://www.example.test');
define('ASSETS_VERSION', '1');
define('APP_VERSION', '1');

check('predefinito: stessi percorsi e URL di Path', function () {
    $m = StorageManager::fromEnvironment(['APP_ENV' => 'production'], ROOT, APP_URL);
    $p = new Wonder\App\Path();

    return $m->public()->localPath('images/a.jpg') === $p->rUpload.'/images/a.jpg'
        && $m->public()->url('images/a.jpg') === $p->upload.'/images/a.jpg'
        && $m->private()->localPath('backup/x.zip') === ROOT.'/storage/backup/x.zip'
        && $m->publicResolver()->layout() === 'flat';
});

check('predefinito: APP_URL con slash finale e ambiente staging non cambiano nulla', function () {
    $m = StorageManager::fromEnvironment(['APP_ENV' => 'staging'], ROOT.'/', APP_URL.'/');

    return $m->public()->url('images/a.jpg') === APP_URL.'/assets/upload/images/a.jpg'
        && $m->public()->localPath('images/a.jpg') === ROOT.'/assets/upload/images/a.jpg';
});

check('privato: nessun indirizzo pubblico', fn () =>
    $throws(fn () => StorageManager::fromEnvironment(['APP_ENV' => 'production'], ROOT, APP_URL)->private()->url('a'))
);

check('scoped con STORAGE_TENANT', function () {
    $m = StorageManager::fromEnvironment(
        ['APP_ENV' => 'staging', 'STORAGE_LAYOUT' => 'scoped', 'STORAGE_TENANT' => 'sito'], ROOT, APP_URL
    );

    return $m->public()->url('images/a.jpg') === APP_URL.'/assets/upload/sito/staging/images/a.jpg'
        && $m->private()->localPath('backup/x.zip') === ROOT.'/storage/sito/staging/backup/x.zip';
});

check('scoped senza STORAGE_TENANT, ambiente o layout non validi: errore', fn () =>
    $throws(fn () => StorageManager::fromEnvironment(['APP_ENV' => 'staging', 'STORAGE_LAYOUT' => 'scoped'], ROOT, APP_URL))
    && $throws(fn () => StorageManager::fromEnvironment(['APP_ENV' => 'prod'], ROOT, APP_URL))
    && $throws(fn () => StorageManager::fromEnvironment(['APP_ENV' => 'staging', 'STORAGE_LAYOUT' => 'boh'], ROOT, APP_URL))
);

summary();
