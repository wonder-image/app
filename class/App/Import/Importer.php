<?php

namespace Wonder\App\Import;

use Throwable;
use Wonder\App\Support\NamedLock;
use Wonder\Sql\Transaction;

/**
 * La vita di un caricamento: file caricato → colonne abbinate e righe
 * controllate → avvio → righe scritte a blocchi (`step()`) → fatto, con un
 * solo annullamento possibile.
 */
final class Importer
{
    public static function layout(string $key): Layout
    {
        return Layouts::get($key);
    }

    /**
     * Copia il file in `storage/imports/` e propone l'abbinamento delle colonne.
     */
    public static function upload(string $tmpPath, string $fileName, string $layoutKey, int $userId, ?int $importLayoutId = null): int
    {
        $layout = self::layout($layoutKey);
        $table = Reader::read($tmpPath, $fileName);

        if ($table['rows'] === []) {
            throw ImportError::make('empty');
        }

        $importLayoutId ??= Profile::defaultId($layoutKey);
        $directory = self::directory();
        $path = $directory.'/'.bin2hex(random_bytes(12)).'.'.Reader::extension($fileName);

        if (!@copy($tmpPath, $path)) {
            throw ImportError::make('unreadable');
        }

        $columns = Mapping::guess($table['headers'], $layout->columns(), Profile::preferred($layout, $importLayoutId ?: null));

        return Store::createJob([
            'layout' => $layoutKey,
            'import_layout_id' => $importLayoutId > 0 ? $importLayoutId : null,
            'file_name' => mb_substr(basename($fileName), 0, 250),
            'path' => $path,
            'mapping' => ['headers' => $table['headers'], 'columns' => $columns],
            'status' => 'uploaded',
            'rows_total' => count($table['rows']),
            'user_id' => $userId,
        ]);
    }

    /**
     * Salva l'abbinamento scelto, rilegge il file e controlla ogni riga.
     *
     * @param array<int, string|null> $columns indice dell'intestazione => chiave
     * @return list<string> errori sull'abbinamento (niente righe controllate)
     */
    public static function map(int $jobId, array $columns): array
    {
        $job = self::job($jobId, ['uploaded', 'validated']);
        $layout = self::layout((string) $job['layout']);
        $mapping = self::mapping($job);
        $headers = $mapping['headers'];
        $known = array_map(static fn (Column $column): string => $column->key, $layout->columns());

        $clean = [];

        foreach (array_keys($headers) as $index) {
            $key = $columns[$index] ?? null;
            $clean[$index] = is_string($key) && in_array($key, $known, true) ? $key : null;
        }

        $errors = [];

        foreach (Mapping::duplicates($clean) as $key) {
            $errors[] = ImportError::make('duplicate_column', ['column' => self::header($layout, $key)])->getMessage();
        }

        if (array_filter($clean) === []) {
            $errors[] = ImportError::make('no_columns')->getMessage();
        }

        $errors = array_merge($errors, $layout->mappingErrors($clean, $headers));
        Store::updateJob($jobId, ['mapping' => ['headers' => $headers, 'columns' => $clean]]);

        if ($errors !== []) {
            return $errors;
        }

        $table = Reader::read((string) $job['path'], (string) $job['file_name']);
        $rows = array_map(static fn (array $row): array => [
            'line' => $row['line'],
            'data' => Mapping::apply($row['cells'], $clean),
        ], $table['rows']);

        $results = $layout->validate($rows, new Context((int) $job['user_id'], $jobId));
        $stored = [];
        $invalid = 0;

        foreach ($rows as $i => $row) {
            $result = $results[$i] ?? ['action' => '', 'errors' => [], 'warnings' => []];
            $hasErrors = $result['errors'] !== [];
            $invalid += $hasErrors ? 1 : 0;
            $stored[] = [
                'line' => $row['line'],
                'data' => $result['data'] ?? $row['data'],
                'status' => $hasErrors ? 'error' : ($result['action'] === 'unchanged' ? 'skipped' : 'ok'),
                'action' => (string) $result['action'],
                'errors' => array_values($result['errors']),
                'warnings' => array_values($result['warnings']),
            ];
        }

        Store::deleteRows($jobId);
        Store::insertRows($jobId, $stored);
        Store::updateJob($jobId, [
            'status' => 'validated',
            'rows_total' => count($stored),
            'rows_valid' => count($stored) - $invalid,
            'rows_error' => $invalid,
        ]);

        return [];
    }

    /** Avvia la scrittura: solo un lavoro controllato e senza righe sbagliate. */
    public static function start(int $jobId): void
    {
        $job = self::job($jobId, ['validated']);

        if ((int) $job['rows_error'] > 0) {
            throw ImportError::make('has_errors', ['count' => (int) $job['rows_error']]);
        }

        Store::updateJob($jobId, ['status' => 'running', 'started_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Scrive le prossime `$size` righe, una transazione per riga. Lo chiamano
     * la pagina e il task: il lock fa sì che due chiamate non si sovrappongano.
     *
     * @return array{status: string, done: int, total: int, created: int, updated: int, errors: int}
     */
    public static function step(int $jobId, int $size = 100): array
    {
        NamedLock::run('wonder.import.'.$jobId, static function () use ($jobId, $size): void {
            $job = Store::job($jobId);

            if ($job === null || $job['status'] !== 'running') {
                return;
            }

            $layout = self::layout((string) $job['layout']);
            $context = new Context((int) $job['user_id'], $jobId);

            foreach (Store::rows($jobId, ['ok'], max(1, $size)) as $row) {
                $data = json_decode((string) $row['data'], true);
                $data = is_array($data) ? $data : [];

                try {
                    Transaction::run(static function () use ($layout, $data, $context, $jobId, $row): void {
                        $result = $layout->apply($data, $context);

                        foreach ($result['changes'] as $change) {
                            Store::addChange($jobId, (int) $row['id'], $change);
                        }

                        $warnings = json_decode((string) $row['warnings'], true);
                        Store::updateRow((int) $row['id'], 'done', (string) $result['action'], [], array_values(array_merge(is_array($warnings) ? $warnings : [], $context->takeWarnings())));
                    });
                } catch (Throwable $exception) {
                    // La transazione è tornata indietro: quello che la cache ricorda di questa riga non c'è più.
                    $context = new Context((int) $job['user_id'], $jobId);
                    Store::updateRow((int) $row['id'], 'error', (string) $row['action'], [self::message($layout, $exception)]);
                }
            }

            self::refreshCounts($jobId);

            if (Store::rows($jobId, ['ok'], 1) === []) {
                $job = Store::job($jobId) ?? $job;
                Store::updateJob($jobId, ['status' => (int) $job['rows_error'] > 0 ? 'failed' : 'done', 'finished_at' => date('Y-m-d H:i:s')]);
                self::deleteFile($job);
            }
        });

        return self::progress($jobId);
    }

    /**
     * Annulla un caricamento finito, dall'ultima modifica alla prima, in una
     * sola transazione.
     */
    public static function undo(int $jobId, int $userId = 0): void
    {
        $job = self::job($jobId, ['done', 'failed']);
        $layout = self::layout((string) $job['layout']);
        $context = new Context($userId > 0 ? $userId : (int) $job['user_id'], $jobId);

        $result = NamedLock::run('wonder.import.'.$jobId, static function () use ($jobId, $layout, $context): void {
            Transaction::run(static function () use ($jobId, $layout, $context): void {
                foreach (Store::changes($jobId) as $change) {
                    $layout->undo($change, $context);
                }

                Store::updateJob($jobId, ['status' => 'undone']);
            });
        });

        if ($result === NamedLock::NOT_ACQUIRED) {
            throw ImportError::make('busy');
        }
    }

    /**
     * @return array{status: string, done: int, total: int, created: int, updated: int, errors: int}
     */
    public static function progress(int $jobId): array
    {
        $job = Store::job($jobId) ?? [];

        return [
            'status' => (string) ($job['status'] ?? ''),
            'done' => (int) ($job['rows_done'] ?? 0),
            'total' => (int) ($job['rows_valid'] ?? 0),
            'created' => (int) ($job['rows_created'] ?? 0),
            'updated' => (int) ($job['rows_updated'] ?? 0),
            'errors' => (int) ($job['rows_error'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $job
     * @return array{headers: list<string>, columns: array<int, string|null>}
     */
    public static function mapping(array $job): array
    {
        $mapping = json_decode((string) ($job['mapping'] ?? ''), true);
        $headers = is_array($mapping['headers'] ?? null) ? array_values(array_map('strval', $mapping['headers'])) : [];
        $columns = [];

        foreach ((array) ($mapping['columns'] ?? []) as $index => $key) {
            $columns[(int) $index] = is_string($key) && $key !== '' ? $key : null;
        }

        return ['headers' => $headers, 'columns' => $columns];
    }

    private static function refreshCounts(int $jobId): void
    {
        $counts = Store::rowCounts($jobId);
        $done = Store::rows($jobId, ['done']);
        $created = count(array_filter($done, static fn (array $row): bool => $row['action'] === 'create'));

        Store::updateJob($jobId, [
            'rows_done' => count($done),
            'rows_created' => $created,
            'rows_updated' => count($done) - $created,
            'rows_error' => (int) ($counts['error'] ?? 0),
        ]);
    }

    /**
     * @param list<string> $statuses
     * @return array<string, mixed>
     */
    private static function job(int $jobId, array $statuses): array
    {
        $job = Store::job($jobId);

        if ($job === null) {
            throw ImportError::make('missing');
        }

        if (!in_array($job['status'], $statuses, true)) {
            throw ImportError::make('status');
        }

        return $job;
    }

    private static function header(Layout $layout, string $key): string
    {
        foreach ($layout->columns() as $column) {
            if ($column->key === $key) {
                return $column->header;
            }
        }

        return $key;
    }

    private static function message(Layout $layout, Throwable $exception): string
    {
        if ($exception instanceof ImportError) {
            return $exception->getMessage();
        }

        return $layout->message($exception) ?? ImportError::translate('row_failed');
    }

    /** @param array<string, mixed> $job */
    private static function deleteFile(array $job): void
    {
        $path = (string) ($job['path'] ?? '');

        if ($path !== '' && str_starts_with($path, self::directory().'/') && is_file($path)) {
            @unlink($path);
        }
    }

    private static function directory(): string
    {
        $root = defined('ROOT') ? (string) constant('ROOT') : (string) getcwd();
        $directory = rtrim($root, '/').'/storage/imports';

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        return $directory;
    }
}
