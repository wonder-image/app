<?php

namespace Wonder\App\Resources\Scheduler;

use Throwable;
use Wonder\App\LegacyGlobals;
use Wonder\App\Resources\Support\NavigationOnlyResource;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\Scheduler\{Presentation, Repository, TaskRegistry};
use Wonder\Backend\Support\FlashAlert;
use Wonder\Backend\Table\Table as Datatable;

/**
 * Pagina "Riepilogo" dello scheduler (non-CRUD). Il Resource espone i dati di
 * dominio (statistiche, opzioni di avvio, heartbeat) e l'azione di avvio
 * manuale; la presentazione vive nella view di `pageView()`, che usa i
 * Componenti wonder-image/app e legge i dati da questi metodi. Nessun render
 * qui: la view la stampa l'entry condivisa `app/http/backend/resource/page.php`.
 */
class DashboardResource extends NavigationOnlyResource
{
    private const PERIODS = [7, 30, 90, 180];

    private static ?Repository $repository = null;

    /** @var array<int, array<string, mixed>>|null */
    private static ?array $schedules = null;

    public static function path(): string { return 'app/scheduler'; }
    public static function icon(): string { return 'bi-clock'; }
    public static function titleLabel(): string { return 'Riepilogo'; }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('dev', 'Dev', 'bi-terminal', 1025, ['admin', 'administrator'])
            ->title('Riepilogo')
            ->order(10)
            ->authority(['admin']);
    }

    /** View della pagina: la presentazione, con i Componenti, sta lì. */
    public static function pageView(): string
    {
        return (string) LegacyGlobals::get('ROOT_APP', '').'/view/pages/backend/scheduler/dashboard.php';
    }

    // --- Dati di dominio letti dalla view --------------------------------

    /** Periodo selezionato e validato, in giorni. */
    public static function period(array $query): int
    {
        $days = (int) ($query['days'] ?? 30);

        return in_array($days, self::PERIODS, true) ? $days : 30;
    }

    public static function statistics(int $days): array
    {
        return static::repository()->statistics($days);
    }

    /**
     * Colonne della tabella statistiche. Serve a `ColumnFormatterRegistry` per
     * registrare (in ogni request, anche l'endpoint SSP) i formatter di cella
     * sotto `{slug}.{colonna}`: la formattazione ricca resta in PHP, con
     * `Presentation::number`, non nel JS.
     */
    public static function tableSchema(): array
    {
        return [
            TableColumn::key('task')->text()->formatter(static fn (array $row): string => static::taskCell($row)),
            TableColumn::key('runs')->text()->sortable(),
            TableColumn::key('successes')->text()->sortable(),
            TableColumn::key('duration')->text()->sortable()->formatter(static fn (array $row): string => static::durationCell($row)),
            TableColumn::key('memory')->text()->sortable()->formatter(static fn (array $row): string => static::memoryCell($row)),
            TableColumn::key('cpu')->text()->sortable()->formatter(static fn (array $row): string => static::cpuCell($row)),
        ];
    }

    /**
     * Tabella "Statistiche per attività" come vero DataTable del framework:
     * aggregato `GROUP BY task_key` su `scheduler_runs`, filtrato per periodo.
     * Il group by/aggregato passa dal supporto SSP (`Backend\Table\Table`),
     * niente `new DataTable` a mano.
     */
    public static function statisticsTable(int $days): string
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $slug = static::slug();

        $table = new Datatable('scheduler_runs');
        $table->title(false);
        $table->length(10);
        $table->query("started_at >= '".$cutoff."' AND status NOT IN ('pending', 'running', 'skipped')");
        $table->select(
            "MIN(id) AS id, task_key, COUNT(*) AS runs, SUM(status = 'success') AS successes, "
            ."AVG(duration_ms) AS average_ms, MAX(duration_ms) AS maximum_ms, SUM(duration_ms) AS total_ms, "
            ."AVG(memory_bytes) AS average_memory, MAX(memory_bytes) AS maximum_memory, "
            ."AVG(cpu_ms) AS average_cpu, SUM(cpu_ms) AS total_cpu"
        );
        $table->groupBy('task_key');
        $table->queryOrder('runs', 'DESC');

        $table->addColumn('Attivita', 'task_key', false, '', '', 'big', ['formatter' => $slug.'.task']);
        $table->addColumn('Esecuzioni', 'runs', true);
        $table->addColumn('Riuscite', 'successes', true);
        $table->addColumn('Durata (s)', 'average_ms', true, '', '', '', ['formatter' => $slug.'.duration']);
        $table->addColumn('Memoria (MiB)', 'average_memory', true, '', '', '', ['formatter' => $slug.'.memory']);
        $table->addColumn('CPU (ms)', 'average_cpu', true, '', '', '', ['formatter' => $slug.'.cpu']);

        return $table->generate(false);
    }

    // --- Formatter di cella (ricevono l'intera riga) ---------------------

    private static function taskCell(array $row): string
    {
        $key = (string) ($row['task_key'] ?? '');
        $name = static::taskNames()[$key] ?? $key;

        return e($name).'<div class="small text-body-secondary">'.e($key).'</div>';
    }

    private static function durationCell(array $row): string
    {
        return Presentation::number($row['average_ms'] ?? null, 1000)
            .'<div class="small text-body-secondary">Max '.Presentation::number($row['maximum_ms'] ?? null, 1000)
            .' · Tot. '.Presentation::number($row['total_ms'] ?? null, 1000).'</div>';
    }

    private static function memoryCell(array $row): string
    {
        return Presentation::number($row['average_memory'] ?? null, 1048576)
            .'<div class="small text-body-secondary">Max '.Presentation::number($row['maximum_memory'] ?? null, 1048576).'</div>';
    }

    private static function cpuCell(array $row): string
    {
        return Presentation::number($row['average_cpu'] ?? null)
            .'<div class="small text-body-secondary">Tot. '.Presentation::number($row['total_cpu'] ?? null).'</div>';
    }

    public static function logCounts(int $days): array
    {
        return static::repository()->logCounts($days);
    }

    public static function heartbeat(): ?string
    {
        return static::repository()->state('heartbeat');
    }

    /** Attività eseguibili a mano: abilitate e (se `task`) con task registrato. */
    public static function runOptions(): array
    {
        $options = [];

        foreach (static::schedules() as $schedule) {
            if ($schedule['enabled'] === 'true' && (($schedule['kind'] ?? 'task') !== 'task' || isset(TaskRegistry::all()[$schedule['task_key']]))) {
                $options[$schedule['id']] = $schedule['name'];
            }
        }

        return $options;
    }

    /** Nomi leggibili per codice attività: task registrati più nomi salvati. */
    public static function taskNames(): array
    {
        return array_replace(TaskRegistry::options(), array_column(static::schedules(), 'name', 'task_key'));
    }

    /** Token CSRF della pagina, generato una volta per sessione. */
    public static function csrfToken(): string
    {
        return $_SESSION['scheduler_csrf'] ??= bin2hex(random_bytes(32));
    }

    // --- Azione della pagina (POST) --------------------------------------

    /**
     * Avvio manuale. L'esito si comunica con un alert (obbligatorio nel
     * backend): messo in coda e mostrato come toast dopo il redirect (PRG),
     * mai come testo inline. Vedi docs/app/concetti/notifiche.md.
     */
    public static function handleSubmit(): never
    {
        $token = $_POST['scheduler_csrf'] ?? null;

        if (!is_string($token) || !hash_equals(static::csrfToken(), $token)) {
            FlashAlert::custom('Richiesta non valida', 'Sessione scaduta o richiesta non valida. Ricarica la pagina e riprova.', 'error');
        } else {
            try {
                static::repository()->request((int) ($_POST['schedule_id'] ?? 0));
                FlashAlert::custom('Esecuzione richiesta', 'Partira al prossimo passaggio dello scheduler.', 'success');
            } catch (Throwable $error) {
                FlashAlert::custom('Avvio non riuscito', $error->getMessage(), 'error');
            }
        }

        // PRG: il refresh non ripete l'invio e l'alert in coda diventa il toast
        // della pagina di arrivo. Riporta il periodo se non è il default.
        $redirect = __r('backend.scheduler');
        $days = (int) ($_GET['days'] ?? 0);
        if (in_array($days, [7, 90, 180], true)) { $redirect .= '?days='.$days; }

        header('Location: '.$redirect, true, 303);
        exit();
    }

    // --- Interni ---------------------------------------------------------

    private static function repository(): Repository
    {
        return static::$repository ??= new Repository();
    }

    /** @return array<int, array<string, mixed>> */
    private static function schedules(): array
    {
        return static::$schedules ??= static::repository()->rows(
            "SELECT * FROM scheduler_schedules WHERE deleted = 'false' ORDER BY name"
        );
    }
}
