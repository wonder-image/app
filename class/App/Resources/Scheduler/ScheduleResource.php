<?php

namespace Wonder\App\Resources\Scheduler;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\{ApiSchema, FormField, NavigationSchema, PageSchema, PermissionSchema, TableColumn, TableLayoutSchema};
use Wonder\App\Scheduler\{ConfiguredTask, Presentation, Repository, TaskRegistry};
use Wonder\Elements\Components\{Button, Card, Container, Dropdown, Text};
use Wonder\Elements\Form\Form;
use Wonder\Http\{Csrf, Route};

class ScheduleResource extends Resource
{
    public static string $model = \Wonder\App\Models\Scheduler\Schedule::class;
    public static function textSchema(): array
    {
        return ['label' => 'pianificazione', 'plural_label' => 'pianificazioni', 'article' => 'le', 'last' => 'ultime', 'all' => 'tutte', 'this' => 'questa'];
    }
    public static function labelSchema(): array
    {
        return ['name' => 'Nome', 'task_key' => 'Attivita da codice', 'kind' => 'Tipo di attivita', 'origin' => 'Origine',
            'target' => 'Script PHP o URL HTTPS', 'http_method' => 'Metodo HTTP', 'timeout' => 'Tempo massimo (secondi)',
            'arguments' => 'Argomenti PHP (uno per riga)', 'expression' => 'Espressione cron', 'timezone' => 'Fuso orario',
            'parameters' => 'Parametri JSON', 'enabled' => 'Stato', 'requested' => 'Esecuzione richiesta',
            'next_due' => 'Prossima scadenza (UTC)', 'last_started' => 'Ultimo avvio (UTC)'];
    }
    public static function formSchema(): array
    {
        return [FormField::key('name')->text()->required(),
            FormField::key('kind')->select(['task' => 'Attivita da codice', 'php' => 'Script PHP', 'https' => 'URL HTTPS'], 'old')->value('task')->required(),
            FormField::key('task_key')->select(TaskRegistry::options(), 'old')->visibleWhen('kind', 'task'),
            FormField::key('target')->text()->visibleWhen('kind', ['php', 'https'])->placeholder('custom/tasks/import.php oppure https://example.com/api/task'),
            FormField::key('arguments')->textarea()->visibleWhen('kind', 'php')->placeholder("sync\n--feed=1"),
            FormField::key('http_method')->select(['GET' => 'GET — parametri nella query', 'POST' => 'POST — parametri nel corpo'], 'old')->value('GET')->visibleWhen('kind', 'https'),
            FormField::key('timeout')->number()->integer()->value(300)->attribute('min="1" max="3600"')->hiddenWhen('kind', 'task'),
            FormField::key('frequency')->select(['custom' => 'Personalizzata', '*/5 * * * *' => 'Ogni 5 minuti', '0 * * * *' => 'Ogni ora', '0 0 * * *' => 'Ogni giorno a mezzanotte', '0 3 * * 1' => 'Ogni lunedi alle 03:00'], 'old')->label('Frequenza')->value('custom')->attribute('onchange="if (this.value !== \'custom\') this.form.elements.expression.value = this.value"'),
            FormField::key('expression')->text()->value('0 0 * * *')->required()->attribute('oninput="this.form.elements.frequency.value = \'custom\'"'),
            FormField::key('timezone')->select(array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()), 'old')->value('Europe/Rome')->required(),
            FormField::key('parameters')->textarea()->value('{}')->hiddenWhen('kind', 'php')->placeholder('{"feed": "1"}'),
            FormField::key('enabled')->select(['false' => 'Sospesa', 'true' => 'Attiva'], 'old')->value('false')->required()
                ->disabled(static::isAlwaysEnabled())];
    }
    public static function formLayoutSchema(): ?Form
    {
        $fields = static fn (array $keys): array => array_map(static fn ($key) => static::getInput($key), $keys);
        return (new Form())->columns(12)->components([
            (new Container())->columnSpan(['default' => 12, 'lg' => 9])->components([
                (new Card())->components([Text::make('Attivita')->tag('div')->bold(),
                    ...$fields(['name', 'kind', 'task_key', 'target', 'http_method', 'arguments', 'parameters']),
                    Text::make('PHP: percorso relativo al sito, senza /usr/local/bin/php. Ogni riga e un argomento separato, senza virgolette. HTTPS: parametri JSON, ad esempio {"feed":"1"}; POST usa il formato form.')->small()->muted(),
                ]),
                (new Card())->components([Text::make('Quando eseguire')->tag('div')->bold(), ...$fields(['frequency', 'expression', 'timezone']),
                    Text::make('I cinque valori indicano: minuto, ora, giorno del mese, mese, giorno della settimana. Gli orari seguono il fuso selezionato.')->small()->muted(),
                ]),
            ]),
            (new Container())->columnSpan(['default' => 12, 'lg' => 3])->components([
                (new Card())->components([Text::make('Impostazioni')->tag('div')->bold(), ...$fields(['enabled', 'timeout']),
                    Text::make('Una pianificazione sospesa conserva configurazione e storico. Il tempo massimo si applica a ogni esecuzione.')->small()->muted(),
                    Button::make('Salva pianificazione')->type('submit')->attr('name', 'upload')->attr('value', 'true')->block()->disabled(static::isReadonly()),
                ]),
                (new Card())->components([Text::make('Come funziona')->tag('div')->bold(),
                    Text::make('Il cron del server controlla le scadenze ogni minuto. Output ed esito restano nel registro per 180 giorni.')->muted(),
                    Text::make(static::isAlwaysEnabled()
                        ? 'Questa attivita e sempre attiva: si possono cambiare orari e parametri, non sospenderla.'
                        : 'Le attivita predefinite si possono sospendere. Quelle aggiunte dal pannello si possono anche eliminare.')->small()->muted(),
                ]),
            ]),
        ]);
    }
    public static function tableSchema(): array
    {
        return [TableColumn::key('name')->text()->link('view'), TableColumn::key('kind')->text()->formatter(static fn ($row) => e(['task' => 'Da codice', 'php' => 'Script PHP', 'https' => 'URL HTTPS'][$row['kind'] ?? 'task'] ?? '')),
            TableColumn::key('origin')->text()->formatter(static fn ($row) => e(($row['origin'] ?? '') === 'code' ? 'Predefinita' : 'Backend')),
            TableColumn::key('expression')->text(),
            TableColumn::key('enabled')->text()->formatter(static fn (array $row): string => Presentation::state($row)),
            TableColumn::key('next_due')->text(), TableColumn::key('last_started')->text(),
            TableColumn::key('actions')->text()->formatter(static fn (array $row): string => static::actionsMenu($row)->render('bootstrap'))];
    }

    /** URL di una pagina o azione di questa pianificazione (`view`, `edit`, `toggle`, `run`, `run-status`). */
    public static function url(array $row, string $action = 'view'): string
    {
        $base = '/backend/app/scheduler/schedules/'.(int) $row['id'].'/';

        return $action === 'view' ? $base : $base.$action.'/';
    }

    /** Registro esecuzioni filtrato sull'attivita della pianificazione. */
    public static function runsUrl(array $row): string
    {
        return __r('backend.resource.'.RunResource::slug().'.list')
            .'?'.RunResource::modelTable().'__search='.rawurlencode((string) ($row['task_key'] ?? ''));
    }

    /**
     * Menu azioni della riga. "Esegui" chiede conferma con `data-wi-confirm`
     * e poi lo segue lo script di `Presentation::runAssets()`; "Attiva" /
     * "Sospendi" manca per le pianificazioni sempre attive; "Elimina" resta
     * riservata alle pianificazioni create dal backend.
     */
    public static function actionsMenu(array $row): Dropdown
    {
        $menu = Dropdown::make()->icon('bi bi-three-dots')->caret(false)->variant('link')->size('sm')->align('end')
            ->toggleClass('text-dark')
            ->item('Visualizza', static::url($row))
            ->item('Modifica', static::url($row, 'edit'));

        if (($row['enabled'] ?? '') === 'true' && static::canEdit()) {
            $menu->action('Esegui ora', ['data-wi-scheduler-run' => static::url($row, 'run')], static::runConfirm($row));
        }

        $toggle = static::canEdit() ? Presentation::toggleAction($row) : null;
        if ($toggle !== null) {
            $menu->item($toggle['label'], static::url($row, 'toggle'), ['method' => 'post', 'hidden' => $toggle['hidden']]);
        }

        $menu->item('Registro esecuzioni', static::runsUrl($row));

        if (($row['origin'] ?? 'code') !== 'code') {
            $menu->divider()->item('Elimina', static::url($row, 'delete'), [
                'method' => 'post',
                'class' => 'text-danger',
                'confirm' => 'Eliminare questa pianificazione? Lo storico verra conservato.',
                'confirm_title' => 'Elimina pianificazione',
                'confirm_ok' => 'Elimina',
                'confirm_variant' => 'danger',
            ]);
        }

        return $menu;
    }

    /** Opzioni di conferma condivise dal menu e dal bottone della pagina di dettaglio. */
    public static function runConfirm(array $row): array
    {
        return [
            'confirm' => 'Eseguire subito "'.($row['name'] ?? '').'"? Partira al prossimo passaggio dello scheduler (entro un minuto).',
            'confirm_title' => 'Esegui pianificazione',
            'confirm_ok' => 'Esegui',
            'confirm_variant' => 'primary',
        ];
    }

    /** Bottoni dell'header del dettaglio: "Esegui ora" (se attiva) e "Modifica". */
    public static function viewActions(array $row): array
    {
        if (!static::canEdit()) {
            return [];
        }

        $actions = [];
        if (($row['enabled'] ?? '') === 'true') {
            $confirm = static::runConfirm($row);
            $actions[] = Button::make('Esegui ora')->icon('bi bi-play-fill')
                ->attr('data-wi-scheduler-run', static::url($row, 'run'))
                ->confirm($confirm['confirm'], title: $confirm['confirm_title'], ok: $confirm['confirm_ok'], variant: $confirm['confirm_variant']);
        }
        $actions[] = Button::make('Modifica')->icon('bi bi-pencil')->href(static::url($row, 'edit'))->variant('warning');

        return $actions;
    }

    public static function canEdit(): bool
    {
        return !static::isReadonly()
            && static::allows((array) ($GLOBALS['USER']->authority ?? []), 'update');
    }

    /** Vero nel form di una pianificazione predefinita che non si può sospendere. */
    private static function isAlwaysEnabled(): bool
    {
        $id = (int) ($GLOBALS['ROUTE_PARAMETERS']['id'] ?? 0);
        $row = $id > 0 ? (new Repository())->find($id) : null;

        return $row !== null && Repository::isAlwaysEnabled($row);
    }

    public static function registerBackendRoutes(string $rootApp, string $slug): void
    {
        $permissions = (array) static::permissionSchema()->get('backend');
        $routes = [
            ['post', 'toggle', 'toggle.php', $permissions['update'] ?? []],
            ['post', 'run', 'run.php', $permissions['update'] ?? []],
            ['get', 'run-status', 'run-status.php', $permissions['view'] ?? $permissions['list'] ?? []],
        ];

        foreach ($routes as [$method, $action, $file, $authorities]) {
            Route::$method('/{id}/'.$action.'/', $rootApp.'/http/backend/scheduler/'.$file, [
                'resource' => $slug,
                'resource_action' => $action,
            ])->name($action)->permit($authorities)->where('id', '[0-9]+');
        }
    }
    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)->title('Pianificazioni')->results()
            ->buttonCustom(Presentation::cronButton())->buttonAdd('Aggiungi pianificazione');
    }
    public static function apiSchema(): ApiSchema { return ApiSchema::for(static::class)->enabled(false); }
    public static function pageSchema(): PageSchema
    {
        $views = dirname(__DIR__, 4).'/app/view/pages/backend/scheduler';

        return PageSchema::for(static::class)->enable('view')
            ->actions('view', static fn (array $item): array => static::viewActions($item))
            ->view('list', $views.'/schedules.php')
            ->view('show', $views.'/schedule.php')
            ->view('form', $views.'/form.php');
    }
    public static function permissionSchema(): PermissionSchema { return PermissionSchema::for(static::class)->access('diagnostica'); }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('dev')
            ->group('automations', 'Automazioni', 20)
            ->title('Pianificazioni')
            ->order(10)
            ->access('diagnostica');
    }
    public static function mutateRequestValues(array $values, string $action, string $context = 'backend', ?array $oldValues = null): array
    {
        static::assertCsrf();
        $arguments = (string) ($_POST['arguments'] ?? $values['arguments'] ?? '');
        $values = array_intersect_key($values, array_flip(['name', 'task_key', 'kind', 'target', 'http_method', 'timeout', 'expression', 'timezone', 'parameters', 'enabled']));
        $merged = array_replace($oldValues ?? [], $values);
        if ($oldValues !== null && Repository::isAlwaysEnabled($oldValues)) {
            $values['enabled'] = $merged['enabled'] = 'true';
        }
        if (trim((string) ($merged['name'] ?? '')) === '') { throw new \InvalidArgumentException('Inserire un nome per la pianificazione.'); }
        $kind = $merged['kind'] ?? 'task';
        if (($oldValues['origin'] ?? '') === 'code' && ($kind !== 'task' || ($merged['task_key'] ?? '') !== $oldValues['task_key'])) {
            throw new \InvalidArgumentException('Una pianificazione predefinita deve mantenere la propria attivita da codice.');
        }
        if (!in_array($kind, ['task', 'php', 'https'], true)) { throw new \InvalidArgumentException('Tipo di attivita non valido.'); }
        $values['kind'] = $kind;
        $values['origin'] = $oldValues['origin'] ?? 'backend';
        if ($kind !== 'task') {
            $values['task_key'] = ($oldValues['kind'] ?? 'task') !== 'task' ? $oldValues['task_key'] : 'custom.'.bin2hex(random_bytes(12));
            $values['target'] = trim((string) ($merged['target'] ?? ''));
            $timeout = filter_var($merged['timeout'] ?? 300, FILTER_VALIDATE_INT);
            if ($timeout === false || $timeout < 1 || $timeout > 3600) { throw new \InvalidArgumentException('Il tempo massimo deve essere tra 1 e 3600 secondi.'); }
            $values['timeout'] = $timeout;
        }
        $task = ConfiguredTask::resolve(array_replace($merged, $values));
        $parameters = $kind === 'php' ? ($arguments === '' ? [] : preg_split('/\r\n|\r|\n/', $arguments))
            : json_decode((string) (($merged['parameters'] ?? '') ?: '{}'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($parameters) || ($kind !== 'php' && array_is_list($parameters) && $parameters !== [])) { throw new \InvalidArgumentException('Inserire un oggetto JSON, ad esempio {"feed":"1"}.'); }
        $parameters = $task->validate($parameters);
        if (!in_array($merged['enabled'] ?? '', ['true', 'false'], true)) { throw new \InvalidArgumentException('Stato non valido.'); }
        $values['parameters'] = json_encode($kind === 'php' ? $parameters : (object) $parameters, JSON_THROW_ON_ERROR);
        if (!in_array($merged['timezone'] ?? '', \DateTimeZone::listIdentifiers(), true)) { throw new \InvalidArgumentException('Selezionare un fuso orario valido.'); }
        $values['next_due'] = Repository::next((string) ($merged['expression'] ?? ''), (string) ($merged['timezone'] ?? ''));
        $values['requested'] = 'false';
        return $values;
    }

    private static function assertCsrf(): void
    {
        if (PHP_SAPI !== 'cli' && !Csrf::verify()) {
            throw new \RuntimeException('Richiesta non valida. Ricaricare il modulo.');
        }
    }

    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        $expression = $values['expression'] ?? '0 0 * * *';
        $values['frequency'] = in_array($expression, ['*/5 * * * *', '0 * * * *', '0 0 * * *', '0 3 * * 1'], true) ? $expression : 'custom';
        if (($values['kind'] ?? '') === 'php' && !array_key_exists('arguments', $values)) {
            $values['arguments'] = implode("\n", json_decode($values['parameters'] ?? '[]', true) ?: []);
        }
        return $values;
    }

    public static function assertDeletable(int|string $id): void
    {
        static::assertCsrf();
        $row = (new Repository())->rows('SELECT origin FROM scheduler_schedules WHERE id = ?', [$id])[0] ?? null;
        if (!$row || $row['origin'] === 'code') { throw new \RuntimeException('Questa pianificazione proviene dal codice: puoi sospenderla, ma non eliminarla dal backend.'); }
    }
}
