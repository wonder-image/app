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

final class ImageResource extends Resource
{
    public static string $model = \Wonder\App\Models\Media\Media::class;

    public static array|string $condition = [
        'deleted' => 'false',
        'type' => 'image',
    ];

    public static function path(): string
    {
        return 'app/media/images';
    }

    public static function legacyFolder(): string
    {
        return 'images';
    }

    public static function icon(): string
    {
        return 'bi bi-image';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'immagine',
            'plural_label' => 'immagini',
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
            'file' => 'Immagine',
            'name' => 'File',
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
            ->title('Immagini')
            ->order(20)
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

        $values['type'] = 'image';

        return $values;
    }
}
