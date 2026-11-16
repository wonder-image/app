<?php
declare(strict_types=1);

/**
 * tests/run.php — esegue la suite di wonder-image/app.
 *
 * Ogni test è uno script PHP a sé: definisce le proprie costanti e termina con
 * exit(), quindi non possono condividere un processo. Qui ne parte uno per file,
 * con un tempo massimo, e l'esito è il codice di uscita (0 = ok).
 *
 *   php tests/run.php                       tutti i test (tests/ ricorsivo *Test.php e gli script in tests/*.php)
 *   php tests/run.php tests/App             solo una cartella
 *   php tests/run.php tests/App/FooTest.php solo un file
 *   php tests/run.php --jobs=4 --timeout=60
 *   php tests/run.php --root=/percorso   altra radice (default: la cartella sopra tests/)
 *   php tests/run.php --list=elenco.txt     un percorso per riga (relativo alla radice o assoluto)
 *
 * Esce con 0 solo se ogni file esce con 0; altrimenti 1 (2 se non trova test o
 * l'uso è sbagliato). Non dipende da .claude/ né da Composer: serve soltanto che
 * vendor/autoload.php esista, perché i test lo caricano da soli.
 */

$root = dirname(__DIR__);
$jobs = 1;
$timeout = 120;
$paths = [];
$list = null;

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--jobs=(\d+)$/', $arg, $m)) {
        $jobs = max(1, (int) $m[1]);
    } elseif (preg_match('/^--timeout=(\d+)$/', $arg, $m)) {
        $timeout = max(1, (int) $m[1]);
    } elseif (str_starts_with($arg, '--root=')) {
        $root = rtrim(substr($arg, 7), '/');
    } elseif (str_starts_with($arg, '--list=')) {
        $list = substr($arg, 7);
    } elseif (str_starts_with($arg, '--')) {
        fwrite(STDERR, "Opzione sconosciuta: {$arg}\n");
        exit(2);
    } else {
        $paths[] = $arg;
    }
}

$tests = $root . '/tests';

if (!is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "vendor/autoload.php manca in {$root}: esegui prima `composer install`.\n");
    exit(2);
}

// Script che stanno in tests/ ma non sono test da lanciare da soli.
$esclusi = [
    'harness.php',                 // libreria dei test: check() e summary()
    'run.php',                     // questo file
    'scheduler-integration.php',   // figlio di scheduler.php: vuole una radice come argomento
];

$resolve = static function (string $p) use ($root): string {
    return str_starts_with($p, '/') ? $p : $root . '/' . ltrim($p, '/');
};

$collect = static function (string $dir) use ($tests, $esclusi): array {
    $found = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fi) {
        if (!$fi->isFile()) {
            continue;
        }
        $name = $fi->getFilename();
        $path = $fi->getPathname();
        $isTest = str_ends_with($name, 'Test.php')
            || (dirname($path) === $tests && str_ends_with($name, '.php') && !in_array($name, $esclusi, true));
        if ($isTest) {
            $found[] = $path;
        }
    }
    return $found;
};

$files = [];
if ($list !== null) {
    if (!is_file($list)) {
        fwrite(STDERR, "Elenco non trovato: {$list}\n");
        exit(2);
    }
    foreach (file($list, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $files[] = $resolve(trim($line));
    }
}
foreach ($paths as $p) {
    $abs = $resolve($p);
    if (is_dir($abs)) {
        array_push($files, ...$collect($abs));
    } elseif (is_file($abs)) {
        $files[] = $abs;
    } else {
        fwrite(STDERR, "Percorso non trovato: {$p}\n");
        exit(2);
    }
}
if ($list === null && $paths === []) {
    $files = $collect($tests);
}

$files = array_values(array_unique($files));
sort($files);

if ($files === []) {
    fwrite(STDERR, "Nessun test trovato.\n");
    exit(2);
}

$rel = static fn (string $f): string => ltrim(str_replace($root, '', $f), '/');

$started = microtime(true);
$queue = $files;
$running = [];
$results = [];

$open = static function (string $file) use ($root): array {
    $proc = proc_open(
        [PHP_BINARY, $file],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
        $pipes,
        $root,
    );
    stream_set_blocking($pipes[1], false);
    return ['file' => $file, 'proc' => $proc, 'pipe' => $pipes[1], 'out' => '', 'start' => microtime(true)];
};

while ($queue !== [] || $running !== []) {
    while ($queue !== [] && count($running) < $jobs) {
        $running[] = $open(array_shift($queue));
    }

    foreach ($running as $i => &$job) {
        $chunk = stream_get_contents($job['pipe']);
        if (is_string($chunk)) {
            $job['out'] .= $chunk;
        }
        $status = proc_get_status($job['proc']);
        $elapsed = microtime(true) - $job['start'];
        $timedOut = $status['running'] && $elapsed > $timeout;

        if ($status['running'] && !$timedOut) {
            continue;
        }
        if ($timedOut) {
            proc_terminate($job['proc'], 9);
        }
        $job['out'] .= (string) stream_get_contents($job['pipe']);
        fclose($job['pipe']);
        $chiuso = proc_close($job['proc']);
        // proc_close() dà -1 se il processo era già stato dato per finito da proc_get_status():
        // in quel caso vale il codice letto prima.
        $code = (!$timedOut && $status['exitcode'] !== -1) ? $status['exitcode'] : $chiuso;
        $done = [
            'file' => $job['file'],
            'code' => $timedOut ? 124 : $code,
            'timeout' => $timedOut,
            'seconds' => $elapsed,
            'out' => $job['out'],
        ];
        $results[] = $done;
        if ($done['code'] === 0) {
            echo "  \u{2713} " . $rel($done['file']) . "\n";
        } else {
            echo "  \u{2717} " . $rel($done['file']) . ($done['timeout'] ? " (timeout {$timeout}s)" : " (exit {$done['code']})") . "\n";
            $righe = array_values(array_filter(explode("\n", rtrim($done['out'])), static fn ($l) => trim($l) !== ''));
            foreach (array_slice($righe, -12) as $riga) {
                echo '      ' . $riga . "\n";
            }
        }
        unset($running[$i]);
    }
    unset($job);

    if ($running !== []) {
        usleep(20000);
    }
}

$failed = array_filter($results, static fn ($r) => $r['code'] !== 0);
$totale = count($results);
$ko = count($failed);
$tempo = microtime(true) - $started;

echo "\n";
if ($ko > 0) {
    echo "File falliti:\n";
    foreach ($failed as $r) {
        echo '  - ' . $rel($r['file']) . ($r['timeout'] ? ' (timeout)' : " (exit {$r['code']})") . "\n";
    }
    echo "\n";
}
printf("%d file, %d ok, %d falliti, %.1f s (PHP %s)\n", $totale, $totale - $ko, $ko, $tempo, PHP_VERSION);

exit($ko === 0 ? 0 : 1);
