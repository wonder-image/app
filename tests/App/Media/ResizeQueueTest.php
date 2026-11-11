<?php
/** php tests/App/Media/ResizeQueueTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Media\MemoryResizeStore;
use Wonder\App\Media\ResizeQueue;
use Wonder\App\Media\Variants;
use Wonder\App\Scheduler\Tasks\MediaResizeTask;
use Wonder\Backend\Support\HomeStatus;

if (!function_exists('__log')) {
    function __log(...$arguments): void {}
}

// Un sito finto: ROOT temporanea con la cartella degli upload.
$root = sys_get_temp_dir().'/wi-resize-'.bin2hex(random_bytes(4));
$upload = $root.'/assets/upload/blog';
mkdir($upload, 0775, true);
ResizeQueue::useRoot($root);

$file = static function (string $relative, string $content = 'x') use ($root): string {
    $path = $root.'/'.$relative;
    @mkdir(dirname($path), 0775, true);
    file_put_contents($path, $content);

    return $path;
};
$vivo = static fn (): MemoryResizeStore => new MemoryResizeStore(gmdate('Y-m-d H:i:s'));
$coda = static function (?MemoryResizeStore $store = null) use ($vivo): MemoryResizeStore {
    $store ??= $vivo();
    ResizeQueue::useStore($store);

    return $store;
};
$now = time();
$riesce = static function (...$args): void {};
$fallisce = static function (...$args): void { throw new RuntimeException('GD senza memoria'); };

$foto = $file('assets/upload/blog/foto.jpg');

check('senza heartbeat dello scheduler enqueue() rifiuta: chi chiama ridimensiona subito', static function () use ($coda, $foto): bool {
    $store = $coda(new MemoryResizeStore(null));

    return ResizeQueue::enqueue($foto, [480, 960], true) === false && $store->rows === [];
});

check('con un heartbeat vecchio enqueue() rifiuta', static function () use ($coda, $foto): bool {
    $store = $coda(new MemoryResizeStore(gmdate('Y-m-d H:i:s', time() - HomeStatus::HEARTBEAT_TOLERANCE - 5)));

    return ResizeQueue::enqueue($foto, [480], true) === false && $store->rows === [];
});

check('senza tabella enqueue() rifiuta e status() non sa niente', static function () use ($coda, $vivo, $foto): bool {
    $store = $vivo();
    $store->usable = false;
    $coda($store);

    return ResizeQueue::enqueue($foto, [480], true) === false && ResizeQueue::status($foto) === null;
});

check('se la coda non si scrive enqueue() rifiuta senza eccezioni', static function () use ($coda, $vivo, $foto): bool {
    $store = $vivo();
    $store->broken = true;
    $coda($store);

    return ResizeQueue::enqueue($foto, [480], true) === false;
});

check('un file fuori da ROOT o senza niente da generare non entra in coda', static function () use ($coda, $foto, $root): bool {
    $store = $coda();

    return ResizeQueue::enqueue('/etc/passwd', [480], true) === false
        && ResizeQueue::enqueue($root.'/assets/../../fuori.jpg', [480], true) === false
        && ResizeQueue::enqueue($foto, [], false) === false
        && $store->rows === [];
});

check('con lo scheduler vivo enqueue() accoda il percorso relativo a ROOT con le sue larghezze', static function () use ($coda, $root): bool {
    $store = $coda();
    $queued = ResizeQueue::enqueue($root.'/assets/upload//blog/foto.jpg', [960, '480', 480, 0, -3], true, 70);
    $row = $store->rows[1];

    return $queued && count($store->rows) === 1
        && $row['path'] === 'assets/upload/blog/foto.jpg' && $row['widths'] === [480, 960]
        && $row['webp'] === true && $row['quality'] === 70 && $row['status'] === 'pending'
        && ResizeQueue::status($root.'/assets/upload/blog/foto.jpg') === 'pending'
        && ResizeQueue::error($root.'/assets/upload/blog/foto.jpg') === null
        && ResizeQueue::status($root.'/assets/upload/blog/altra.jpg') === null;
});

check('riaccodare lo stesso percorso non crea una seconda riga e riparte da pending', static function () use ($coda, $foto, $fallisce, $now): bool {
    $store = $coda();
    ResizeQueue::enqueue($foto, [480], true);
    ResizeQueue::process($store, $fallisce, null, $now);
    $dopoErrore = $store->rows[1];
    ResizeQueue::enqueue($foto, [240, 620], false);
    $row = $store->rows[1];

    return count($store->rows) === 1 && $dopoErrore['attempts'] === 1 && $dopoErrore['error'] !== ''
        && $row['widths'] === [240, 620] && $row['webp'] === false
        && $row['status'] === 'pending' && $row['attempts'] === 0 && $row['error'] === '';
});

check('il worker lavora con le larghezze della riga, non con quelle del sito', static function () use ($coda, $foto, $now): bool {
    $store = $coda();
    ResizeQueue::enqueue($foto, [480, 960], true, 65);
    $ricevuto = null;
    $result = ResizeQueue::process($store, static function (...$args) use (&$ricevuto): void { $ricevuto = $args; }, null, $now);

    return !defined('RESPONSIVE_IMAGE_SIZES')
        && $ricevuto === [$foto, [480, 960], true, 65]
        && $result === ['released' => 0, 'ready' => 1, 'retried' => 0, 'failed' => 0, 'missing' => 0]
        && ResizeQueue::status($foto) === 'ready' && ResizeQueue::error($foto) === null;
});

check('una riga presa in carico non viene lavorata da un secondo processo', static function () use ($coda, $foto, $now): bool {
    $store = $coda();
    ResizeQueue::enqueue($foto, [480], true);
    $giri = 0;
    $secondo = null;
    $result = ResizeQueue::process($store, static function (...$args) use (&$giri, &$secondo, $store, $now): void {
        $giri++;
        // Un altro worker parte mentre il primo sta ridimensionando.
        $secondo ??= ResizeQueue::process($store, static function () use (&$giri): void { $giri++; }, null, $now);
    }, null, $now);

    return $giri === 1 && $result['ready'] === 1
        && $secondo === ['released' => 0, 'ready' => 0, 'retried' => 0, 'failed' => 0, 'missing' => 0]
        && $store->claim(1, 'altro', ResizeQueue::time($now + 60)) === null;
});

check('un errore riprova dopo l\'attesa, poi chiude failed con il messaggio', static function () use ($coda, $foto, $fallisce, $now): bool {
    $store = $coda();
    ResizeQueue::enqueue($foto, [480], true);

    $primo = ResizeQueue::process($store, $fallisce, null, $now);
    $subito = ResizeQueue::process($store, $fallisce, null, $now + 10);
    $attesa = $store->rows[1]['next'];
    $secondo = ResizeQueue::process($store, $fallisce, null, $now + 61);
    $terzo = ResizeQueue::process($store, $fallisce, null, $now + 61 + 301);
    $quarto = ResizeQueue::process($store, $fallisce, null, $now + 99999);

    return $primo['retried'] === 1 && $subito['retried'] === 0 && $attesa === ResizeQueue::time($now + 60)
        && $secondo['retried'] === 1 && $terzo['failed'] === 1 && $terzo['retried'] === 0
        && array_sum($quarto) === 0
        && $store->rows[1]['attempts'] === ResizeQueue::MAX_ATTEMPTS
        && ResizeQueue::status($foto) === 'failed' && ResizeQueue::error($foto) === 'GD senza memoria';
});

check('un formato che non si può ridimensionare chiude subito, senza riprovare', static function () use ($coda, $foto, $now): bool {
    $store = $coda();
    ResizeQueue::enqueue($foto, [480], true);
    $result = ResizeQueue::process($store, static function (): void { throw new ErrorException('Formato non supportato', 752); }, null, $now);

    return $result['failed'] === 1 && $store->rows[1]['attempts'] === 1 && ResizeQueue::status($foto) === 'failed';
});

check('il file sorgente sparito chiude la riga senza riprovare e senza chiamare il resize', static function () use ($coda, $file, $now): bool {
    $store = $coda();
    $sparita = $file('assets/upload/blog/sparita.jpg');
    ResizeQueue::enqueue($sparita, [480], true);
    unlink($sparita);
    $chiamato = false;
    $result = ResizeQueue::process($store, static function () use (&$chiamato): void { $chiamato = true; }, null, $now);
    $dopo = ResizeQueue::process($store, static function () use (&$chiamato): void { $chiamato = true; }, null, $now + 99999);

    return !$chiamato && $result['missing'] === 1 && $result['failed'] === 0 && array_sum($dopo) === 0
        && ResizeQueue::status($sparita) === 'failed' && ResizeQueue::error($sparita) === ResizeQueue::MISSING
        // Un file cancellato non è un guasto da segnalare nella home.
        && $store->failed(ResizeQueue::time($now - 60), ResizeQueue::MISSING) === 0;
});

check('deleteWhenReady cancella solo dopo ready, mai prima', static function () use ($coda, $file, $foto, $fallisce, $riesce, $now): bool {
    $store = $coda();
    $vecchia = $file('assets/upload/blog/vecchia-480.jpg');
    $vecchiaWebp = $file('assets/upload/blog/vecchia-480.webp');
    ResizeQueue::enqueue($foto, [480], true, null, [$vecchia, $vecchiaWebp]);

    ResizeQueue::process($store, $fallisce, null, $now);
    $ancoraLi = is_file($vecchia) && is_file($vecchiaWebp);
    ResizeQueue::process($store, $riesce, null, $now + 61);

    return $ancoraLi && !is_file($vecchia) && !is_file($vecchiaWebp) && is_file($foto)
        && $store->rows[1]['status'] === 'ready' && $store->rows[1]['delete'] === [];
});

check('deleteWhenReady scarta i percorsi fuori dalla cartella degli upload', static function () use ($coda, $file, $foto, $riesce, $root, $now): bool {
    $store = $coda();
    $config = $file('custom/config/segreto.php');
    $dentro = $file('assets/upload/blog/da-togliere.jpg');
    ResizeQueue::enqueue($foto, [480], true, null, [
        $config,
        $root.'/assets/upload/../../custom/config/segreto.php',
        '/etc/hosts',
        'assets/upload/blog/relativo.jpg',
        $root.'/assets/upload',
        ['non una stringa'],
        $dentro,
    ]);
    $accodati = $store->rows[1]['delete'];
    // Una riga manomessa nel database non basta per uscire dagli upload.
    $store->rows[1]['delete'][] = 'custom/config/segreto.php';
    $store->rows[1]['delete'][] = 'assets/upload/../../custom/config/segreto.php';
    $store->rows[1]['delete'][] = 'assets/upload/blog/foto.jpg';
    ResizeQueue::process($store, $riesce, null, $now);

    return $accodati === ['assets/upload/blog/da-togliere.jpg']
        && is_file($config) && is_file($foto) && !is_file($dentro);
});

check('riaccodare un percorso ancora in coda non perde i file da cancellare', static function () use ($coda, $file, $foto): bool {
    $store = $coda();
    $a = $file('assets/upload/blog/a-480.jpg');
    $b = $file('assets/upload/blog/b-480.jpg');
    ResizeQueue::enqueue($foto, [480], true, null, [$a]);
    ResizeQueue::enqueue($foto, [480], true, null, [$b, $a]);

    return $store->rows[1]['delete'] === ['assets/upload/blog/a-480.jpg', 'assets/upload/blog/b-480.jpg'];
});

check('un file riaccodato mentre lo si lavora resta pending e non cancella niente', static function () use ($coda, $file, $foto, $now): bool {
    $store = $coda();
    $vecchia = $file('assets/upload/blog/sostituita-480.jpg');
    ResizeQueue::enqueue($foto, [480], true, null, [$vecchia]);
    $result = ResizeQueue::process($store, static function () use ($foto): void {
        ResizeQueue::enqueue($foto, [960], true);
    }, null, $now);

    return $result['ready'] === 0 && is_file($vecchia)
        && $store->rows[1]['status'] === 'pending' && $store->rows[1]['widths'] === [960]
        && $store->rows[1]['delete'] === ['assets/upload/blog/sostituita-480.jpg'];
});

check('una presa in carico scaduta torna libera; dopo troppi tentativi chiude failed', static function () use ($coda, $foto, $riesce, $now): bool {
    $store = $coda();
    ResizeQueue::enqueue($foto, [480], true);
    // Un worker prende la riga e muore.
    $store->claim(1, 'morto', ResizeQueue::time($now + ResizeQueue::CLAIM_TIMEOUT));
    $presto = ResizeQueue::process($store, $riesce, null, $now + 30);
    $scaduta = ResizeQueue::process($store, $riesce, null, $now + ResizeQueue::CLAIM_TIMEOUT + 1);

    $altro = $coda();
    ResizeQueue::enqueue($foto, [480], true);
    for ($giro = 0; $giro < ResizeQueue::MAX_ATTEMPTS; $giro++) {
        $altro->claim(1, 'morto'.$giro, ResizeQueue::time($now));
        $rilascio = $altro->release(ResizeQueue::time($now + 1), ResizeQueue::MAX_ATTEMPTS, ResizeQueue::INTERRUPTED);
    }

    return array_sum($presto) === 0
        && $scaduta['released'] === 1 && $scaduta['ready'] === 1 && $store->rows[1]['attempts'] === 2
        && $rilascio === 1 && $altro->rows[1]['status'] === 'failed' && $altro->rows[1]['error'] === ResizeQueue::INTERRUPTED;
});

check('il giro si ferma al limite di righe e al tempo concesso', static function () use ($coda, $file, $riesce, $now): bool {
    $store = $coda();
    foreach (['uno', 'due', 'tre'] as $name) {
        ResizeQueue::enqueue($file('assets/upload/blog/'.$name.'.jpg'), [480], true);
    }
    $senzaTempo = ResizeQueue::process($store, $riesce, null, $now, ResizeQueue::BATCH, 0);
    $due = ResizeQueue::process($store, $riesce, null, $now, 2);
    $resto = ResizeQueue::process($store, $riesce, null, $now);

    return $senzaTempo['ready'] === 0 && $due['ready'] === 2 && $resto['ready'] === 1;
});

check('retryFailed() rimette in coda i falliti da capo', static function () use ($coda, $foto, $now): bool {
    $store = $coda();
    ResizeQueue::enqueue($foto, [480], true);
    ResizeQueue::process($store, static function (): void { throw new ErrorException('Formato non supportato', 752); }, null, $now);

    return $store->retryFailed(ResizeQueue::time($now)) === 1
        && $store->rows[1]['status'] === 'pending' && $store->rows[1]['attempts'] === 0
        && ResizeQueue::process($store, static function (): void {}, null, $now)['ready'] === 1;
});

check('in coda vanno solo le larghezze responsive: le misure fisse restano immediate', static fn (): bool =>
    ResizeQueue::queueable([240, 480, 960])
    && ResizeQueue::queueable([])
    && !ResizeQueue::queueable(['width' => 600, 'height' => 400])
    && !ResizeQueue::queueable(['width' => 600])
    && !ResizeQueue::queueable([['width' => 600, 'height' => 400]])
    && !ResizeQueue::queueable([['width' => 600], ['width' => 1200]])
    && !ResizeQueue::queueable(true)
    && !ResizeQueue::queueable(null)
);

check('il resize vero genera le misure della riga senza le costanti del sito', static function () use ($coda, $root, $now): bool {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
        echo "    (GD senza webp: salto)\n";

        return true;
    }

    $store = $coda();
    $path = $root.'/assets/upload/blog/vera.jpg';
    imagejpeg(imagecreatetruecolor(300, 200), $path);
    ResizeQueue::enqueue($path, [100, 200], true);
    $result = ResizeQueue::process($store, null, null, $now);
    $base = $root.'/assets/upload/blog/vera';

    return !defined('RESPONSIVE_IMAGE_SIZES') && $result['ready'] === 1
        && (getimagesize($base.'-100.jpg')[0] ?? 0) === 100
        && (getimagesize($base.'-200.jpg')[0] ?? 0) === 200
        && is_file($base.'.webp') && is_file($base.'-100.webp') && is_file($base.'-200.webp')
        && !is_file($base.'-480.jpg');
});

check('il task è sempre attivo, ogni minuto, con una chiave stabile', static function (): bool {
    $task = new MediaResizeTask();

    return $task->key() === 'wonder.media-resize' && $task->label() === 'Ridimensionamento immagini'
        && $task->expression() === '* * * * *' && $task->enabled() && $task->alwaysEnabled()
        && $task->timeout() > ResizeQueue::BUDGET + ResizeQueue::DEADLINE_MARGIN;
});

check('la home tace quando la coda scorre e avvisa se è ferma o ci sono falliti', static function () use ($now): bool {
    $fresca = ResizeQueue::time($now - 120);
    $ferma = ResizeQueue::time($now - ResizeQueue::STUCK_AFTER - 1);
    $attesa = HomeStatus::mediaResize($ferma, 0, $now, '/backend/scheduler/');
    $fallite = HomeStatus::mediaResize(null, 2, $now, '/backend/scheduler/');

    return HomeStatus::mediaResize(null, 0, $now, '/x/') === null
        && HomeStatus::mediaResize($fresca, 0, $now, '/x/') === null
        && $attesa?->getSchema('value') === 'In attesa' && $attesa->getSchema('variant') === 'warning'
        && $fallite?->getSchema('value') === '2' && str_contains((string) $fallite->getSchema('detail'), 'non riusciti');
});

check('l\'anteprima del backend usa l\'originale finché la misura non è su disco', static function () use ($file, $root): bool {
    $file('assets/upload/blog/pronta.jpg');
    $file('assets/upload/blog/pronta-240.jpg');
    $file('assets/upload/blog/in-coda.jpg');
    $url = 'https://sito.test';
    $dir = $url.'/assets/upload/blog/';

    return Variants::url($dir.'pronta-240.jpg', $dir.'pronta.jpg', $root, $url) === $dir.'pronta-240.jpg'
        && Variants::url($dir.'in-coda-240.jpg', $dir.'in-coda.jpg', $root, $url) === $dir.'in-coda.jpg'
        // Niente su disco o URL esterno: non si può sapere, resta com'era.
        && Variants::url($dir.'nessuna-240.jpg', $dir.'nessuna.jpg', $root, $url) === $dir.'nessuna-240.jpg'
        && Variants::url('https://cdn.test/a-240.jpg', 'https://cdn.test/a.jpg', $root, $url) === 'https://cdn.test/a-240.jpg'
        && Variants::suffix($dir, '["pronta.jpg"]', '240', $root, $url) === '240'
        && Variants::suffix($dir, ['pronta.jpg', 'in-coda.jpg'], '240', $root, $url) === ''
        && Variants::suffix($dir, null, '240', $root, $url) === '240'
        && Variants::suffix($dir, ['in-coda.jpg'], '', $root, $url) === '';
});

ResizeQueue::useStore(null);
ResizeQueue::useRoot(null);

// Pulizia della ROOT temporanea.
$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($items as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($root);

summary();
