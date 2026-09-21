<?php

namespace Wonder\App\Resources\System;

use Wonder\App\LegacyGlobals;
use Wonder\App\Models\System\ErrorReport;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;

/**
 * "Errori": gli errori ripetuti raccolti da `ErrorReporter`.
 *
 * Sono i guasti tecnici, quelli di chi sviluppa: non si creano e non si
 * eliminano, si guardano e si segnano risolti. Un errore chiuso che si
 * ripresenta torna aperto da solo e fa ripartire l'avviso, quindi chiudere non
 * nasconde niente.
 *
 * Quello che deve vedere chi usa il sito non è un errore ma una notifica: sta
 * altrove, con parole sue.
 */
final class ErrorReportResource extends Resource
{
    public static string $model = ErrorReport::class;
    public static string $orderColumn = 'last_seen_at';
    public static string $orderDirection = 'DESC';

    public static function path(): string
    {
        return 'app/config/errori';
    }

    public static function icon(): string
    {
        return 'bi-exclamation-octagon';
    }

    public static function titleLabel(): string
    {
        return 'Errori';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'errore',
            'plural_label' => 'errori',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'gli',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'service' => 'Servizio',
            'action' => 'Azione',
            'message' => 'Messaggio',
            'occurrences' => 'Volte',
            'first_seen_at' => 'Prima volta',
            'last_seen_at' => 'Ultima volta',
            'resolved_at' => 'Risolto il',
            'resolved' => 'Risolto',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('resolved')
                ->select(['false' => 'Aperto', 'true' => 'Risolto'])
                ->value('false')
                ->label('Stato'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Container)->components([
                (new Card)->components([
                    SectionTitle::make('Errore')
                        ->tooltip('Segnare risolto chiude la riga: se il problema torna, riparte da capo con un nuovo avviso.')
                        ->columnSpan(12),
                    static::getInput('resolved')->columnSpan(4),
                ])->columns(12)->columnSpan(12),
            ])->columns(12)->columnSpan(12),
        ]);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('service')->text()->link('edit'),
            TableColumn::key('action')->text(),
            TableColumn::key('occurrences')->text()->size('little'),
            TableColumn::key('last_seen_at')->datetime()->size('little'),
            TableColumn::key('resolved_at')
                ->text()
                ->size('little')
                ->formatter(static fn (array $row): string => trim((string) ($row['resolved_at'] ?? '')) === ''
                    ? '<span class="badge text-bg-danger"><i class="bi bi-exclamation-circle"></i> Aperto</span>'
                    : '<span class="badge text-bg-success"><i class="bi bi-check-circle"></i> Risolto</span>'),
            TableColumn::key('actions')->button()->actions(['edit']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Errori')
            ->results()
            ->hideButtonAdd()
            ->filters()
            ->searchFields(['service', 'action', 'message']);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->only(['list', 'edit', 'update'])
            ->titles(['list' => 'Errori', 'edit' => 'Errore']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'edit', 'update'], ['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('set-up')
            ->title('Errori')
            ->order(80)
            ->authority(['admin']);
    }

    /** L'interruttore della scheda diventa data e autore. */
    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        $resolved = ((string) ($values['resolved'] ?? 'false')) === 'true';
        unset($values['resolved']);

        $user = LegacyGlobals::get('USER');
        $userId = is_object($user) && isset($user->id) ? (int) $user->id : 0;

        $values['resolved_at'] = $resolved ? date('Y-m-d H:i:s') : '';
        $values['resolved_by'] = $resolved ? $userId : 0;

        return $values;
    }

    /** La scheda parte dallo stato di adesso. */
    public static function mutateFormValues(array $values, string $mode, string $context = 'backend'): array
    {
        $values['resolved'] = trim((string) ($values['resolved_at'] ?? '')) === '' ? 'false' : 'true';

        return $values;
    }
}
