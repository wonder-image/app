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

final class IconResource extends Resource
{
    public static string $model = \Wonder\App\Models\Media\Media::class;

    public static array|string $condition = [
        'deleted' => 'false',
        'type' => 'icon',
    ];

    public static function path(): string
    {
        return 'app/media/icons';
    }

    public static function legacyFolder(): string
    {
        return 'icons';
    }

    public static function icon(): string
    {
        return 'bi bi-grid-1x2';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'icona',
            'plural_label' => 'icone',
            'last' => 'ultime',
            'all' => 'tutte',
            'article' => 'le',
            'full' => 'usata',
            'empty' => 'non usata',
            'this' => 'questa',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'file' => 'Icona',
            'name' => 'File',
            'alt' => 'Alt',
            'slug' => 'Slug',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('file')
                ->inputFileDragDrop('image')
                ->storeAs('{slug}')
                ->prepare([
                    'webp' => RESPONSIVE_IMAGE_WEBP,
                    'resize' => RESPONSIVE_IMAGE_SIZES,
                ])
                ->required(),
            FormInput::key('name')->text()->required(),
            FormInput::key('alt')->text(),
        ];
    }

    public static function formLayoutSchema(): ?Form
    {
        return (new Form)->components([
            (new Card)->components([
                static::getInput('file')->columnSpan(4),
                static::getInput('name')->columnSpan(8),
                static::getInput('alt')->columnSpan(8),
            ])->columns(12)->columnSpan(9),
        ])->columns(12);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('file')->image()->link('edit'),
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
            ->fields('index', ['id', 'name', 'slug', 'alt', 'type', 'file'])
            ->fields('show', ['id', 'name', 'slug', 'alt', 'type', 'file'])
            ->fields('store', ['name', 'alt', 'file'])
            ->fields('update', ['name', 'alt', 'file']);
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
            ->title('Icone')
            ->order(30)
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

        $values['type'] = 'icon';

        return $values;
    }
}
