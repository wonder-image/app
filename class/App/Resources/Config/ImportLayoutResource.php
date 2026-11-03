<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\Import\Layouts;
use Wonder\App\Models\Import\ImportLayout;
use Wonder\App\Models\Import\ImportLayoutColumn;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\SectionTitle;
use Wonder\Elements\Form\Form;

/**
 * "Tracciati file": quali colonne, in che ordine e con che intestazione
 * escono nell'export e si propongono nel caricamento. Solo per gli admin.
 * Un tracciato predefinito per tipo: salvarne uno lo toglie agli altri.
 */
final class ImportLayoutResource extends Resource
{
    public static string $model = ImportLayout::class;
    public static array|string $condition = [];
    public static string $orderColumn = 'name';
    public static string $orderDirection = 'ASC';

    public static function path(): string { return 'app/config/import-layouts'; }

    public static function textSchema(): array
    {
        return [
            'label' => 'tracciato',
            'plural_label' => 'tracciati',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'pieno',
            'empty' => 'vuoto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'name' => 'Nome',
            'layout' => 'Tipo',
            'is_default' => 'Predefinito',
            'columns' => 'Colonne',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('name')->text()->required(),
            FormField::key('layout')->select(static::layoutOptions())->required(),
            FormField::key('is_default')->select(['true' => 'Sì', 'false' => 'No'])->value('false')->required(),
            FormField::key('columns')
                ->repeater([
                    RepeaterColumn::key('id')->hidden(),
                    RepeaterColumn::key('column_key')->select(static::columnOptions())->label('Dato')->columnSpan(6),
                    RepeaterColumn::key('header')->text()->label('Intestazione nel file')->columnSpan(5),
                ])
                ->relation(
                    RepeaterRelation::make(ImportLayoutColumn::$table, 'import_layout_id')
                        ->model(ImportLayoutColumn::class)
                        ->positionKey('position')
                )
                ->nested()
                ->repeaterSortable()
                ->repeaterAddLabel('Aggiungi colonna')
                ->repeaterDeleteTitle('Elimina colonna')
                ->repeaterDeleteText('Confermi l\'eliminazione di questa colonna?')
                ->repeaterDeleteCancelLabel('Annulla')
                ->repeaterDeleteConfirmLabel('Elimina')
                ->repeaterDeleteConfirmClass('btn btn-danger')
                ->label('Colonne, nell\'ordine del file'),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('columns')->columnSpan(12),
            ])->columns(12)->columnSpan(9),
            (new Card)->components([
                SectionTitle::make('Tracciato')
                    ->tooltip('Il tracciato predefinito lo usano l\'export e, come prima proposta, il caricamento: uno per tipo. Le colonne sono quelle del file, nell\'ordine: trascinale per spostarle.')
                    ->columnSpan(12),
                static::getInput('name')->columnSpan(12),
                static::getInput('layout')->columnSpan(12),
                static::getInput('is_default')->columnSpan(12),
            ])->columns(12)->columnSpan(3),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('layout')->text()->formatter(static fn ($row) => e(static::layoutOptions()[$row['layout'] ?? ''] ?? (string) ($row['layout'] ?? ''))),
            TableColumn::key('is_default')
                ->booleanBadge()
                ->badgeOn('Predefinito', 'bi bi-star-fill', 'primary')
                ->badgeOff('No', 'bi bi-dash', 'secondary')
                ->size('little'),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Tracciati file')
            ->results()
            ->buttonAdd('Aggiungi tracciato')
            ->searchFields(['name']);
    }

    public static function pageSchema(): PageSchema
    {
        return static::page()->disable('view');
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'create', 'store', 'edit', 'update', 'delete'], ['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->inSection('set-up')
            ->title('Tracciati file')
            ->order(90)
            ->authority(['admin']);
    }

    public static function afterStore(object $result, array $values = []): void
    {
        static::keepSingleDefault((int) ($result->insert_id ?? 0));
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        static::keepSingleDefault((int) $id);
    }

    /** @return array<string, string> chiave => nome del tracciato di codice */
    public static function layoutOptions(): array
    {
        $options = [];

        foreach (Layouts::all() as $key => $layout) {
            $options[$key] = $layout->label();
        }

        return $options;
    }

    /**
     * Le colonne di tutti i tracciati: con più tipi l'etichetta dice di quale.
     * Le chiavi che non sono del tipo scelto le ignora `Profile::for()`.
     *
     * @return array<string, string>
     */
    public static function columnOptions(): array
    {
        $layouts = Layouts::all();
        $options = [];

        foreach ($layouts as $layout) {
            foreach ($layout->columns() as $column) {
                $options[$column->key] ??= count($layouts) > 1 ? $layout->label().': '.$column->header : $column->header;
            }
        }

        return $options;
    }

    private static function keepSingleDefault(int $id): void
    {
        $row = $id > 0 ? ImportLayout::findById($id) : null;

        if (!is_array($row) || ($row['is_default'] ?? '') !== 'true') {
            return;
        }

        $others = ImportLayout::find(['layout' => (string) $row['layout'], 'is_default' => 'true', 'deleted' => 'false']);
        $others = is_array($others) && isset($others['id']) ? [$others] : (is_array($others) ? $others : []);

        foreach ($others as $other) {
            if (is_array($other) && (int) ($other['id'] ?? 0) !== $id) {
                sqlModify(ImportLayout::$table, ['is_default' => 'false'], 'id', (int) $other['id']);
            }
        }
    }
}
