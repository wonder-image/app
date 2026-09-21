<?php

namespace Wonder\App\Resources\Scheduler;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\{ApiSchema, FormField, NavigationSchema, PageSchema, PermissionSchema, TableColumn, TableLayoutSchema};
use Wonder\App\Scheduler\{ConfiguredTask, Repository, TaskRegistry};
use Wonder\Elements\Components\{Button, Card, Container, Text};
use Wonder\Elements\Form\Form;

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
        $_SESSION['scheduler_csrf'] ??= bin2hex(random_bytes(32));
        return [FormField::key('scheduler_csrf')->hidden()->value($_SESSION['scheduler_csrf']),
            FormField::key('name')->text()->required(),
            FormField::key('kind')->select(['task' => 'Attivita da codice', 'php' => 'Script PHP', 'https' => 'URL HTTPS'], 'old')->value('task')->required(),
            FormField::key('task_key')->select(TaskRegistry::options(), 'old')->visibleWhen('kind', 'task'),
            FormField::key('target')->text()->visibleWhen('kind', ['php', 'https'])->placeholder('custom/tasks/import.php oppure https://example.com/api/task'),
            FormField::key('arguments')->textarea()->visibleWhen('kind', 'php')->placeholder("sync\n--feed=1"),
            FormField::key('http_method')->select(['GET' => 'GET — parametri nella query', 'POST' => 'POST — parametri nel corpo'], 'old')->value('GET')->visibleWhen('kind', 'https'),
            FormField::key('timeout')->number()->decimals(0)->value(300)->attribute('min="1" max="3600"')->hiddenWhen('kind', 'task'),
            FormField::key('frequency')->select(['custom' => 'Personalizzata', '*/5 * * * *' => 'Ogni 5 minuti', '0 * * * *' => 'Ogni ora', '0 0 * * *' => 'Ogni giorno a mezzanotte', '0 3 * * 1' => 'Ogni lunedi alle 03:00'], 'old')->label('Frequenza')->value('custom')->attribute('onchange="if (this.value !== \'custom\') this.form.elements.expression.value = this.value"'),
            FormField::key('expression')->text()->value('0 0 * * *')->required()->attribute('oninput="this.form.elements.frequency.value = \'custom\'"'),
            FormField::key('timezone')->select(array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()), 'old')->value('Europe/Rome')->required(),
            FormField::key('parameters')->textarea()->value('{}')->hiddenWhen('kind', 'php')->placeholder('{"feed": "1"}'),
            FormField::key('enabled')->select(['false' => 'Sospesa', 'true' => 'Attiva'], 'old')->value('false')->required()];
    }
    public static function formLayoutSchema(): ?Form
    {
        $fields = static fn (array $keys): array => array_map(static fn ($key) => static::getInput($key), $keys);
        return (new Form())->columns(12)->components([
            (new Container())->columnSpan(['default' => 12, 'lg' => 9])->components([
                (new Card())->components([Text::make('Attivita')->tag('div')->bold(),
                    ...$fields(['scheduler_csrf', 'name', 'kind', 'task_key', 'target', 'http_method', 'arguments', 'parameters']),
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
                    Text::make('Le attivita predefinite si possono sospendere. Quelle aggiunte dal pannello si possono anche eliminare.')->small()->muted(),
                ]),
            ]),
        ]);
    }
    public static function tableSchema(): array
    {
        return [TableColumn::key('name')->text()->link('edit'), TableColumn::key('kind')->text()->formatter(static fn ($row) => e(['task' => 'Da codice', 'php' => 'Script PHP', 'https' => 'URL HTTPS'][$row['kind'] ?? 'task'] ?? '')),
            TableColumn::key('origin')->text()->formatter(static fn ($row) => e(($row['origin'] ?? '') === 'code' ? 'Predefinita' : 'Backend')),
            TableColumn::key('expression')->text(), TableColumn::key('enabled')->activeBadge(),
            TableColumn::key('next_due')->text(), TableColumn::key('last_started')->text(),
            TableColumn::key('actions')->text()->formatter(static fn (array $row): string => static::actionsCell($row))];
    }

    /**
     * Menu azioni della riga: lo stesso dropdown a tre puntini delle altre
     * liste del backend. "Visualizza" apre il Registro esecuzioni gia filtrato
     * sull'attivita di questa pianificazione; "Elimina" resta riservata alle
     * pianificazioni create dal backend e passa dalla rotta protetta della
     * resource (CSRF + soft-delete), mai dall'endpoint di delete generico.
     */
    private static function actionsCell(array $row): string
    {
        $base = '/backend/app/scheduler/schedules/'.(int) $row['id'];
        $runs = __r('backend.resource.'.RunResource::slug().'.list')
            .'?'.RunResource::modelTable().'__search='.rawurlencode((string) ($row['task_key'] ?? ''));

        $items = '<a class="dropdown-item" href="'.e($base.'/edit/').'" role="button">Modifica</a>'
            .'<a class="dropdown-item" href="'.e($runs).'" role="button">Visualizza</a>';

        if (($row['origin'] ?? 'code') !== 'code') {
            $_SESSION['scheduler_csrf'] ??= bin2hex(random_bytes(32));
            $items .= '<form method="post" action="'.e($base.'/delete/').'" onsubmit="return window.confirm(\'Eliminare questa pianificazione? Lo storico verra conservato.\')">'
                .FormField::key('scheduler_csrf')->hidden()->value($_SESSION['scheduler_csrf'])->render('bootstrap')
                .'<button type="submit" class="dropdown-item text-danger">Elimina</button></form>';
        }

        return '<div class="dropdown">'
            .'<span class="badge text-dark" role="button" data-bs-toggle="dropdown" aria-bs-haspopup="true" aria-bs-expanded="false"><i class="bi bi-three-dots"></i></span>'
            .'<div class="dropdown-menu dropdown-menu-right">'.$items.'</div>'
            .'</div>';
    }
    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)->title('Pianificazioni')->results()->buttonAdd('Aggiungi pianificazione');
    }
    public static function apiSchema(): ApiSchema { return ApiSchema::for(static::class)->enabled(false); }
    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)->view('form', dirname(__DIR__, 4).'/app/view/pages/backend/scheduler/form.php');
    }
    public static function permissionSchema(): PermissionSchema { return PermissionSchema::for(static::class)->backendCrud(['admin']); }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('dev')
            ->group('automations', 'Automazioni', 20)
            ->title('Pianificazioni')
            ->order(10)
            ->authority(['admin']);
    }
    public static function mutateRequestValues(array $values, string $action, string $context = 'backend', ?array $oldValues = null): array
    {
        static::assertCsrf();
        $arguments = (string) ($_POST['arguments'] ?? $values['arguments'] ?? '');
        $values = array_intersect_key($values, array_flip(['name', 'task_key', 'kind', 'target', 'http_method', 'timeout', 'expression', 'timezone', 'parameters', 'enabled']));
        $merged = array_replace($oldValues ?? [], $values);
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
        if (PHP_SAPI !== 'cli' && (!is_string($_POST['scheduler_csrf'] ?? null)
            || !is_string($_SESSION['scheduler_csrf'] ?? null)
            || !hash_equals($_SESSION['scheduler_csrf'], $_POST['scheduler_csrf']))) {
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
