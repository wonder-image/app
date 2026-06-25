<?php

namespace Wonder\App\Resources\Communications;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class AnnouncementResource extends Resource
{
    public static string $model = \Wonder\App\Models\Communications\Announcement::class;

    public static string $orderColumn = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'announcements';
    }

    public static function icon(): string
    {
        return 'bi bi-megaphone';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'annuncio',
            'plural_label' => 'annunci',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'gli',
            'full' => 'visibile',
            'empty' => 'nascosto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'slug' => 'Slug',
            'name' => 'Titolo',
            'text' => 'Testo',
            'visible' => 'Stato',
            'position' => 'Ordine',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormField::key('name')
                ->text()
                ->required(),
            FormField::key('text')
                ->textarea(),
            FormField::key('visible')
                ->select([
                    'true' => 'Visibile',
                    'false' => 'Nascosto',
                ], 'old')
                ->value('true')
                ->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('name')->columnSpan(2),
                static::getInput('text')->columnSpan(2),
            ])->columns(2)->columnSpan(2),

            (new Card)->components([
                static::getInput('visible'),
            ])->columns(1)->columnSpan(1),
        ])->columns(3);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('visible')
                ->badge()
                ->function('visible', 'id', 'automaticResize')
                ->size('little'),
            TableColumn::key('actions')
                ->button()
                ->actions(['edit', 'visible', 'delete']),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->buttonAdd('Aggiungi '.static::label())
            ->filters();
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->fields('index', ['id', 'slug', 'name', 'text', 'position', 'visible']);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin', 'administrator'])
            ->apiCrud(['admin', 'administrator']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('notices', 'Avvisi', 'bi-megaphone', 500, ['admin', 'administrator'])
            ->title('Annunci')
            ->order(20)
            ->authority(['admin', 'administrator']);
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        if (!empty($values['name']) && empty($values['slug'])) {
            $values['slug'] = $values['name'];
        }

        return $values;
    }
}
