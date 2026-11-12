<?php

namespace Wonder\App\Media;

use ErrorException;
use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Scheduler\Context;
use Wonder\App\Support\Mail\Outbox;
use Wonder\App\Support\NamedLock;
use Wonder\Plugin\Custom\Image\ResponsiveImage;

/**
 * Coda dei ridimensionamenti, legata al percorso del file: `uploadFiles()`
 * scrive una riga `pending` in `image_resizes` con le larghezze da generare
 * e il task `wonder.media-resize` (sempre attivo, ogni minuto) la lavora e
 * la chiude `ready` o `failed`. Finché la riga è in coda il sito mostra
 * l'originale: i renderer usano solo le misure presenti su disco.
 *
 * Fallback: senza tabella, senza heartbeat recente dello scheduler
 * (`Outbox::alive()`) o se la coda non si scrive, `enqueue()` ritorna false
 * e chi chiama ridimensiona subito, come prima della coda.
 *
 * Un errore riprova dopo `RETRY_DELAYS`, fino a `MAX_ATTEMPTS`; un file
 * sorgente sparito chiude subito. Una presa in carico oltre `CLAIM_TIMEOUT`
 * torna libera.
 */
final class ResizeQueue
{
    public const PENDING = 'pending';
    public const READY = 'ready';
    public const FAILED = 'failed';

    public const MAX_ATTEMPTS = 3;

    /** Attesa dopo il tentativo n (secondi); oltre la lista vale l'ultima. */
    public const RETRY_DELAYS = [60, 300];

    public const CLAIM_TIMEOUT = 600;

    /** Oltre questi secondi in coda la home del backend segnala l'attesa. */
    public const STUCK_AFTER = 900;

    /** La home del backend conta i ridimensionamenti falliti in questi secondi. */
    public const FAILED_WINDOW = 604800;

    public const BATCH = 50;

    /** Secondi di lavoro di un giro del task: il resto aspetta il minuto dopo. */
    public const BUDGET = 40;

    /** Secondi lasciati liberi prima della scadenza del giro. */
    public const DEADLINE_MARGIN = 15;

    public const LOCK = 'wonder.media-resize';

    public const UPLOAD = 'assets/upload/';

    public const MISSING = 'File sorgente non trovato.';
    public const INTERRUPTED = 'Elaborazione interrotta più volte.';

    private static ?ResizeStore $store = null;
    private static ?string $root = null;

    /**
     * Accoda il ridimensionamento di `$absolutePath` (sotto `ROOT`). Una riga
     * per file: riaccodare lo stesso percorso la riporta a `pending`.
     * `$deleteWhenReady`: percorsi assoluti sotto `ROOT/assets/upload/` da
     * cancellare quando la riga diventa `ready`; gli altri si scartano.
     *
     * Falso se la coda non è utilizzabile: chi chiama ridimensiona subito.
     */
    public static function enqueue(string $absolutePath, array $widths, bool $webp, ?int $quality = null, array $deleteWhenReady = [], bool $withoutScheduler = false): bool
    {
        try {
            $path = self::relative($absolutePath);
            $widths = self::widths($widths);

            if ($path === null || ($widths === [] && !$webp)) {
                return false;
            }

            $store = self::store();
            $now = time();

            // `$withoutScheduler`: chi consegna un lavoro che può aspettare (una
            // migrazione, dati di prova) accoda anche a scheduler fermo.
            if (!$store->usable() || (!$withoutScheduler && !Outbox::alive($store->heartbeat(), $now))) {
                return false;
            }

            $delete = array_values(array_filter(array_map(self::uploadPath(...), $deleteWhenReady)));
            $previous = $store->find($path);

            if (($previous['status'] ?? null) === self::PENDING) {
                $delete = array_values(array_unique([...(array) ($previous['delete'] ?? []), ...$delete]));
            }

            $store->put($path, ['widths' => $widths, 'webp' => $webp, 'quality' => $quality, 'delete' => $delete], self::time($now));

            return true;
        } catch (Throwable $exception) {
            self::log($exception, '[enqueue]');

            return false;
        }
    }

    /** `pending`, `ready`, `failed`, o null se il percorso non è mai stato accodato. */
    public static function status(string $absolutePath): ?string
    {
        return self::row($absolutePath)['status'] ?? null;
    }

    /** L'ultimo errore del percorso, o null se non ce n'è. */
    public static function error(string $absolutePath): ?string
    {
        $error = (string) (self::row($absolutePath)['error'] ?? '');

        return $error !== '' ? $error : null;
    }

    /**
     * Un giro della coda. `$resize` riceve percorso assoluto, larghezze,
     * webp e qualità e solleva un'eccezione se non riesce; di default è
     * `resize()`. Si ferma a `$limit` righe, dopo `$seconds` secondi o a
     * ridosso della scadenza di `$context`.
     *
     * @return array{released: int, ready: int, retried: int, failed: int, missing: int}
     */
    public static function process(?ResizeStore $store = null, ?callable $resize = null, ?Context $context = null, ?int $now = null, int $limit = self::BATCH, ?int $seconds = self::BUDGET): array
    {
        $store ??= self::store();
        $resize ??= self::resize(...);
        $clock = $now !== null ? static fn (): int => $now : time(...);
        $until = $seconds !== null ? microtime(true) + $seconds : null;
        $root = rtrim(self::root(), '/');

        $result = [
            'released' => $store->release(self::time($clock()), self::MAX_ATTEMPTS, self::INTERRUPTED),
            'ready' => 0, 'retried' => 0, 'failed' => 0, 'missing' => 0,
        ];

        foreach ($store->due(self::time($clock()), $limit) as $id) {
            if (($until !== null && microtime(true) >= $until)
                || ($context !== null && microtime(true) >= $context->deadline - self::DEADLINE_MARGIN)) {
                break;
            }

            $token = bin2hex(random_bytes(16));
            $job = $store->claim((int) $id, $token, self::time($clock() + self::CLAIM_TIMEOUT));

            if ($job === null) {
                continue;
            }

            $source = $root.'/'.$job['path'];

            if ($root === '' || !is_file($source)) {
                $store->finish((int) $id, $token, self::FAILED, self::MISSING, self::time($clock()));
                $result['missing']++;
                continue;
            }

            $error = null;
            $permanent = false;

            try {
                $resize($source, self::widths((array) $job['widths']), (bool) $job['webp'], $job['quality'] !== null ? (int) $job['quality'] : null);
            } catch (Throwable $exception) {
                $error = $exception->getMessage() !== '' ? $exception->getMessage() : $exception::class;
                // 751 / 752: file sparito o formato non gestito, riprovare non serve.
                $permanent = $exception instanceof ErrorException && in_array($exception->getCode(), [751, 752], true);
            }

            if ($error === null) {
                // Riaccodata mentre si lavorava: le misure sono del file vecchio, il giro dopo le rifà.
                if ($store->finish((int) $id, $token, self::READY, '', self::time($clock()))) {
                    self::delete((array) $job['delete'], (string) $job['path']);
                    $result['ready']++;
                }
            } elseif ($permanent || (int) $job['attempts'] >= self::MAX_ATTEMPTS) {
                $store->finish((int) $id, $token, self::FAILED, $error, self::time($clock()));
                $result['failed']++;
            } else {
                $store->finish((int) $id, $token, self::PENDING, $error, self::time($clock()), self::time(self::retryAt((int) $job['attempts'], $clock())));
                $result['retried']++;
            }
        }

        return $result;
    }

    /**
     * Il giro del task e del comando: un solo processo alla volta
     * (`NamedLock`), nessun lavoro se la tabella manca.
     */
    public static function drain(?Context $context = null, ?int $seconds = self::BUDGET, int $limit = self::BATCH): array
    {
        $store = self::store();

        if (!$store->usable()) {
            return ['skipped' => 'image_resizes_missing'];
        }

        $result = NamedLock::run(self::LOCK, static fn (): array => self::process($store, null, $context, null, $limit, $seconds));

        return $result === NamedLock::NOT_ACQUIRED ? ['busy' => true] : $result;
    }

    /** Genera le misure adesso; solleva un'eccezione se non riesce. */
    public static function resize(string $absolutePath, array $widths, bool $webp, ?int $quality = null): void
    {
        $image = ResponsiveImage::path($absolutePath);
        $image->sizes = $widths;
        $image->webp = $webp;

        if ($quality !== null) {
            $image->quality = max(1, min(100, $quality));
        }

        $image->generate();
    }

    /**
     * Vero se il `resize` di un campo può aspettare la coda: nessuna misura
     * (solo webp) o una lista di sole larghezze, quelle responsive. Le
     * misure fisse dichiarate con `width` (`['width' => 600, 'height' => 400]`,
     * `resizeToWidth()`, `resizeToWidths()`) servono subito a chi le legge
     * per nome e restano immediate.
     */
    public static function queueable(mixed $resize): bool
    {
        if (!is_array($resize) || !array_is_list($resize)) {
            return false;
        }

        foreach ($resize as $width) {
            if (!is_int($width) && !(is_string($width) && ctype_digit($width))) {
                return false;
            }
        }

        return true;
    }

    /** Un ridimensionamento aspetta da oltre `STUCK_AFTER` secondi: la coda è ferma. */
    public static function stuck(?string $oldestPending, int $now): bool
    {
        $queued = $oldestPending ? strtotime($oldestPending.' UTC') : false;

        return $queued !== false && $now - $queued > self::STUCK_AFTER;
    }

    public static function retryAt(int $attempts, int $now): int
    {
        $delays = self::RETRY_DELAYS;

        return $now + $delays[min(max($attempts, 1), count($delays)) - 1];
    }

    public static function time(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /** Percorso relativo a `ROOT` di un file assoluto, o null se ne esce. */
    public static function relative(string $absolutePath): ?string
    {
        $root = rtrim(self::root(), '/');
        $path = (string) preg_replace('#/+#', '/', str_replace('\\', '/', $absolutePath));

        if ($root === '' || str_contains($path, "\0") || !str_starts_with($path, $root.'/')) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', substr($path, strlen($root))), static fn (string $segment): bool => $segment !== '' && $segment !== '.'));

        return $segments === [] || in_array('..', $segments, true) ? null : implode('/', $segments);
    }

    public static function store(): ResizeStore
    {
        return self::$store ??= new DatabaseResizeStore();
    }

    /** Sostituisce lo store (test, o un sito con una coda sua); null torna al database. */
    public static function useStore(?ResizeStore $store): void
    {
        self::$store = $store;
    }

    /** Radice del sito al posto di `ROOT` (test); null torna a `ROOT`. */
    public static function useRoot(?string $root): void
    {
        self::$root = $root;
    }

    public static function root(): string
    {
        return self::$root ?? (string) LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');
    }

    /** Percorso relativo di un file da cancellare, solo se sta nella cartella degli upload. */
    private static function uploadPath(mixed $absolutePath): ?string
    {
        $path = is_string($absolutePath) ? self::relative($absolutePath) : null;

        return $path !== null && str_starts_with($path, self::UPLOAD) && $path !== rtrim(self::UPLOAD, '/') ? $path : null;
    }

    /**
     * Cancella i file lasciati da `deleteWhenReady`. I percorsi vengono dalla
     * riga: si ricontrollano, anche con `realpath`, prima di toccare il disco.
     */
    private static function delete(array $paths, string $source): void
    {
        $root = rtrim(self::root(), '/');
        $upload = realpath($root.'/'.self::UPLOAD);

        if ($upload === false) {
            return;
        }

        foreach ($paths as $path) {
            $path = is_string($path) ? self::uploadPath($root.'/'.$path) : null;

            if ($path === null || $path === $source || !is_file($root.'/'.$path)) {
                continue;
            }

            $real = realpath($root.'/'.$path);

            if ($real !== false && str_starts_with($real, $upload.DIRECTORY_SEPARATOR)) {
                @unlink($real);
            }
        }
    }

    private static function widths(array $widths): array
    {
        $widths = array_values(array_unique(array_filter(
            array_map(static fn (mixed $width): int => is_numeric($width) ? (int) $width : 0, $widths),
            static fn (int $width): bool => $width > 0
        )));
        sort($widths);

        return $widths;
    }

    private static function row(string $absolutePath): ?array
    {
        try {
            $path = self::relative($absolutePath);
            $store = self::store();

            return $path !== null && $store->usable() ? $store->find($path) : null;
        } catch (Throwable $exception) {
            self::log($exception, '[status]');

            return null;
        }
    }

    private static function log(Throwable $exception, string $action): void
    {
        if (function_exists('__log')) {
            __log($exception, '[media-resize]', $action);
        }
    }
}
