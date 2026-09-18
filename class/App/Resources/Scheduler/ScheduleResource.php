<?php

namespace Wonder\App\Resources\Scheduler;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\{ApiSchema, FormField, NavigationSchema, PageSchema, PermissionSchema, TableColumn, TableLayoutSchema};
use Wonder\App\Scheduler\{Repository, TaskRegistry};
use Wonder\Elements\Components\{Button, Card, Text};
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
        return ['name' => 'Nome', 'task_key' => 'Attivita', 'expression' => 'Espressione cron', 'timezone' => 'Fuso orario',
            'parameters' => 'Parametri JSON', 'enabled' => 'Stato', 'requested' => 'Esecuzione richiesta',
            'next_due' => 'Prossima scadenza (UTC)', 'last_started' => 'Ultimo avvio (UTC)'];
    }
    public static function formSchema(): array
    {
        $_SESSION['scheduler_csrf'] ??= bin2hex(random_bytes(32));
        return [FormField::key('scheduler_csrf')->hidden()->value($_SESSION['scheduler_csrf']),
            FormField::key('name')->text()->required(),
            FormField::key('task_key')->select(TaskRegistry::options(), 'old')->required(),
            FormField::key('expression')->text()->value('0 0 * * *')->required(),
            FormField::key('timezone')->select(array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()), 'old')->value('Europe/Rome')->required(),
            FormField::key('parameters')->textarea()->value('{}'),
            FormField::key('enabled')->select(['false' => 'Sospesa', 'true' => 'Attiva'], 'old')->value('false')->required()];
    }
    public static function formLayoutSchema(): ?Form
    {
        return (new Form())->components([(new Card())->components([
            ...array_map(static fn ($field) => static::getInput($field),
                ['scheduler_csrf', 'name', 'task_key', 'expression', 'timezone', 'parameters', 'enabled']),
            Text::make('Esempi: */5 * * * * ogni 5 minuti; 0 0 * * * ogni notte; 0 3 * * 1 ogni lunedi alle 03:00.')->muted(),
            Text::make('I parametri dipendono dall\'attivita. Mantieni credenziali e token nella configurazione del sito.')->muted(),
        ])]);
    }
    public static function tableSchema(): array
    {
        return [TableColumn::key('name')->text()->link('edit'), TableColumn::key('task_key')->text(),
            TableColumn::key('expression')->text(), TableColumn::key('enabled')->activeBadge(),
            TableColumn::key('next_due')->text(), TableColumn::key('last_started')->text(),
            TableColumn::key('actions')->button()->actions(['edit'])];
    }
    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)->title('Attivita pianificate')->results()->buttonAdd('Aggiungi pianificazione')
            ->buttonCustom(Button::make('Riepilogo e avvio')->href('/backend/app/scheduler/'));
    }
    public static function apiSchema(): ApiSchema { return ApiSchema::for(static::class)->enabled(false); }
    public static function pageSchema(): PageSchema { return PageSchema::for(static::class)->disable(['delete']); }
    public static function permissionSchema(): PermissionSchema { return PermissionSchema::for(static::class)->backendCrud(['admin']); }
    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)->section('scheduler', 'Attivita pianificate', 'bi-clock', 1025, ['admin'])
            ->title('Pianificazioni')->authority(['admin']);
    }
    public static function mutateRequestValues(array $values, string $action, string $context = 'backend', ?array $oldValues = null): array
    {
        if (PHP_SAPI !== 'cli' && (!is_string($_POST['scheduler_csrf'] ?? null)
            || !is_string($_SESSION['scheduler_csrf'] ?? null)
            || !hash_equals($_SESSION['scheduler_csrf'], $_POST['scheduler_csrf']))) {
            throw new \RuntimeException('Richiesta non valida. Ricaricare il modulo.');
        }
        $values = array_intersect_key($values, array_flip(['name', 'task_key', 'expression', 'timezone', 'parameters', 'enabled']));
        $merged = array_replace($oldValues ?? [], $values);
        $task = TaskRegistry::get((string) ($merged['task_key'] ?? ''));
        $parameters = json_decode((string) ($merged['parameters'] ?? '{}'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($parameters) || (array_is_list($parameters) && $parameters !== [])) { throw new \InvalidArgumentException('Inserire un oggetto JSON.'); }
        $parameters = $task->validate($parameters);
        if (!in_array($merged['enabled'] ?? '', ['true', 'false'], true)) { throw new \InvalidArgumentException('Stato non valido.'); }
        $values['parameters'] = json_encode((object) $parameters, JSON_THROW_ON_ERROR);
        $values['next_due'] = Repository::next((string) ($merged['expression'] ?? ''), (string) ($merged['timezone'] ?? ''));
        $values['requested'] = 'false';
        return $values;
    }
}
