<?php

namespace Wonder\App\Resources\Media;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Form;

final class DocumentResource extends Resource
{
    public static string $model = \Wonder\App\Models\Media\Media::class;

    public static array|string $condition = [
        'deleted' => 'false',
        'type' => 'document',
    ];

    public static function path(): string
    {
        return 'app/media/documents';
    }

    public static function icon(): string
    {
        return 'bi bi-file-earmark-text';
    }

    public static function legacyFolder(): string
    {
        return 'documents';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'documento',
            'plural_label' => 'documenti',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'usato',
            'empty' => 'non usato',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'file' => 'Documento',
            'name' => 'Nome file',
            'slug' => 'Slug',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('file')
                ->inputFileDragDrop('pdf')
                ->storeAs('{slug}')
                ->maxSize(5)
                ->maxFile(1)
                ->required(),
            FormInput::key('name')->text()->required(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('file')->columnSpan(4),
                static::getInput('name')->columnSpan(8),
            ])->columns(12)->columnSpan(9),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('slug')->text(),
            TableColumn::key('actions')->button()->actions(['edit', 'delete']),
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
        return PageSchema::for(static::class)
            ->disable(['view']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->fields('index', ['id', 'name', 'slug', 'type', 'file'])
            ->fields('show', ['id', 'name', 'slug', 'type', 'file'])
            ->fields('store', ['name', 'file'])
            ->fields('update', ['name', 'file']);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin'])
            ->apiCrud(['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Media', 'media', 'bi-image')
            ->title('Documenti')
            ->order(40)
            ->authority(['admin']);
    }

    public static function mutateRequestValues(
        array $values,
        string $action,
        string $context = 'backend',
        ?array $oldValues = null
    ): array {
        if (!empty($values['name'])) {
            $values['slug'] = $values['name'];
        }

        $values['type'] = 'document';

        return $values;
    }
}
