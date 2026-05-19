<?php

namespace Wonder\App\Resources\Communications;

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

final class PopupResource extends Resource
{
    public static string $model = \Wonder\App\Models\Communications\Popup::class;

    public static string $orderColumn = 'position';
    public static string $orderDirection = 'ASC';

    public static function path(): string
    {
        return 'popups';
    }

    public static function icon(): string
    {
        return 'bi bi-window-stack';
    }

    public static function textSchema(): array
    {
        return [
            'label' => 'popup',
            'plural_label' => 'popup',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'visibile',
            'empty' => 'nascosto',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'slug' => 'Slug',
            'name' => 'Nome interno',
            'title' => 'Titolo',
            'url' => 'URL azione',
            'url_label' => 'Etichetta CTA',
            'pages' => 'Pagine',
            'view' => 'Modalità',
            'images' => 'Immagine',
            'visible' => 'Stato',
            'position' => 'Ordine',
            'actions' => 'Azioni',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('name')
                ->text()
                ->required(),
            FormInput::key('title')
                ->text(),
            FormInput::key('url')
                ->text(),
            FormInput::key('url_label')
                ->text(),
            FormInput::key('images')
                ->inputFileDragDrop('image')
                ->storeAs('{slug}')
                ->prepare([
                    'resize' => [
                        ['width' => 120, 'height' => 120],
                        ['width' => 480, 'height' => 480],
                        ['width' => 620, 'height' => 620],
                        ['width' => 960, 'height' => 960],
                        ['width' => 1080, 'height' => 1080],
                        ['width' => 1440, 'height' => 1440],
                        ['width' => 1920, 'height' => 1920],
                    ],
                ]),
            FormInput::key('visible')
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
                static::getInput('name')->columnSpan(1),
                static::getInput('title')->columnSpan(1),
                static::getInput('url')->columnSpan(1),
                static::getInput('url_label')->columnSpan(1),
                static::getInput('images')->columnSpan(2),
            ])->columns(2)->columnSpan(2),

            (new Card)->components([
                static::getInput('visible'),
            ])->columns(1)->columnSpan(1),
        ])->columns(3);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('images')->image(),
            TableColumn::key('name')->text()->link('edit'),
            TableColumn::key('title')->text(),
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
            ->fields('index', ['id', 'slug', 'name', 'title', 'url', 'url_label', 'view', 'images', 'pages', 'position', 'visible']);
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
            ->section('Avvisi', 'notices', 'bi-megaphone')
            ->title('Popup')
            ->order(30)
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
