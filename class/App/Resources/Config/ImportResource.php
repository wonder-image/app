<?php

namespace Wonder\App\Resources\Config;

use Throwable;
use Wonder\App\Import\ImportError;
use Wonder\App\Import\Importer;
use Wonder\App\Import\Layout;
use Wonder\App\Import\Layouts;
use Wonder\App\Import\Store;
use Wonder\App\Logger;
use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\Backend\Support\FlashAlert;
use Wonder\Http\Csrf;

/**
 * Pagina "Carica da file", comune a tutti i tracciati (`?layout=<chiave>`):
 * file → abbinamento delle colonne → anteprima → scrittura a blocchi →
 * annullamento. Non sta nel menu: la apre il bottone della lista che la usa,
 * e il permesso lo decide `Layout::permits()`.
 */
final class ImportResource extends NavigationOnlyResource
{
    public static function path(): string { return 'app/config/import'; }
    public static function icon(): string { return 'bi-upload'; }
    public static function titleLabel(): string { return 'Carica da file'; }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)->enabled(false);
    }

    public static function pageView(): string
    {
        return dirname(__DIR__, 4).'/app/view/pages/backend/config/import.php';
    }

    /** @param array<string, scalar> $query */
    public static function url(array $query = []): string
    {
        $url = __r('backend.config.import');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    public static function stepUrl(int $jobId): string
    {
        return __r('backend.config.import.step', ['id' => $jobId]);
    }

    public static function sampleUrl(string $layoutKey, string $format = 'xlsx'): string
    {
        return __r('backend.config.import.sample').'?'.http_build_query(['layout' => $layoutKey, 'format' => $format]);
    }

    /**
     * Il tracciato chiesto, se l'utente lo può usare; altrimenti 403.
     */
    public static function layout(string $key, ?object $user = null): Layout
    {
        $user ??= self::user();
        $layout = Layouts::get($key);

        if ($user === null || !$layout->permits($user)) {
            throw ImportError::make('forbidden');
        }

        return $layout;
    }

    /**
     * Il lavoro chiesto, solo se è del tracciato della pagina.
     *
     * @return array<string, mixed>|null
     */
    public static function job(string $layoutKey, int $jobId): ?array
    {
        $job = $jobId > 0 ? Store::job($jobId) : null;

        return $job !== null && $job['layout'] === $layoutKey ? $job : null;
    }

    public static function handleSubmit(): void
    {
        $key = (string) ($_POST['layout'] ?? '');
        $jobId = (int) ($_POST['job'] ?? 0);
        $back = self::url(['layout' => $key]);

        try {
            if (!Csrf::verify()) {
                FlashAlert::custom('Sessione scaduta', 'Ricarica la pagina e riprova.', 'danger');
            } else {
                self::layout($key);
                $back = match ((string) ($_POST['action'] ?? '')) {
                    'upload' => self::upload($key),
                    'map' => self::map($key, $jobId),
                    'start' => self::start($key, $jobId),
                    'undo' => self::undo($key, $jobId),
                    default => $back,
                };
            }
        } catch (ImportError $exception) {
            FlashAlert::custom('Caricamento non riuscito', $exception->getMessage(), 'danger');
            $back = $jobId > 0 && self::job($key, $jobId) !== null ? self::url(['layout' => $key, 'job' => $jobId]) : $back;
        } catch (Throwable $exception) {
            Logger::log($exception, 'backend', 'import', 'ERROR', 'error', ['layout' => $key, 'job' => $jobId], false);
            FlashAlert::custom('Caricamento non riuscito', 'Qualcosa non ha funzionato: riprova.', 'danger');
        }

        header('Location: '.$back, true, 303);
        exit;
    }

    private static function upload(string $key): string
    {
        $file = $_FILES['file'] ?? null;
        $tmp = is_array($file['tmp_name'] ?? null) ? ($file['tmp_name'][0] ?? '') : ($file['tmp_name'] ?? '');
        $name = is_array($file['name'] ?? null) ? ($file['name'][0] ?? '') : ($file['name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            FlashAlert::custom('Nessun file', 'Scegli il file da caricare (.xlsx o .csv).', 'warning');

            return self::url(['layout' => $key]);
        }

        $profile = (int) ($_POST['import_layout_id'] ?? 0);
        $jobId = Importer::upload($tmp, (string) $name, $key, (int) (self::user()?->id ?? 0), $profile > 0 ? $profile : null);

        return self::url(['layout' => $key, 'job' => $jobId]);
    }

    private static function map(string $key, int $jobId): string
    {
        self::ownJob($key, $jobId);
        $columns = [];

        foreach ($_POST as $name => $column) {
            if (preg_match('/^col_(\d+)$/', (string) $name, $match)) {
                $columns[(int) $match[1]] = is_string($column) && $column !== '' ? $column : null;
            }
        }

        $errors = Importer::map($jobId, $columns);

        if ($errors !== []) {
            FlashAlert::custom('Controlla le colonne', implode("\n", $errors), 'warning');
        }

        return self::url(['layout' => $key, 'job' => $jobId]);
    }

    private static function start(string $key, int $jobId): string
    {
        self::ownJob($key, $jobId);
        Importer::start($jobId);

        return self::url(['layout' => $key, 'job' => $jobId]);
    }

    private static function undo(string $key, int $jobId): string
    {
        self::ownJob($key, $jobId);
        Importer::undo($jobId, (int) (self::user()?->id ?? 0));
        FlashAlert::saved('Caricamento annullato.');

        return self::url(['layout' => $key, 'job' => $jobId]);
    }

    private static function ownJob(string $key, int $jobId): void
    {
        if (self::job($key, $jobId) === null) {
            throw ImportError::make('missing');
        }
    }

    private static function user(): ?object
    {
        global $USER;

        return is_object($USER ?? null) ? $USER : null;
    }
}
