<?php

namespace Wonder\App\Resources\Css;

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

final class CssFontResource extends Resource
{
    public static string $model = \Wonder\App\Models\Css\CssFont::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'font',
            'plural_label' => 'font',
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
            'link' => 'Link',
            'font_family' => 'Font family',
            'visible' => 'Stato',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('font_family')
                ->text()
                ->required(),
            FormInput::key('link')
                ->url()
                ->required(),
            FormInput::key('name')
                ->text()
                ->required(),
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
                static::getInput('name')
                    ->columnSpan(1),
                static::getInput('font_family')
                    ->columnSpan(1),
                static::getInput('link')
                    ->columnSpan(2),
            ])
                ->columns(2)
                ->columnSpan(2),

            (new Card)->components([
                static::getInput('visible'),
            ])
                ->columns(1)
                ->columnSpan(1),
        ])->columns(3);
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')
                ->text()
                ->link('edit'),
            TableColumn::key('font_family')
                ->text(),
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
            ->fields('index', ['id', 'name', 'font_family', 'visible'])
            ->fields('show', ['id', 'name', 'link', 'font_family', 'visible'])
            ->fields('store', ['name', 'link', 'font_family', 'visible'])
            ->fields('update', ['name', 'link', 'font_family', 'visible']);
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
            ->section('Stile', 'css', 'bi-award')
            ->authority(['admin'])
            ->order(20);
    }

    public static function afterStore(object $result, array $values = []): void
    {
        if (function_exists('cssRoot')) {
            cssRoot();
        }
    }

    public static function afterUpdate(int|string $id, object $result, array $values = []): void
    {
        if (function_exists('cssRoot')) {
            cssRoot();
        }
    }

    public static function afterDelete(int|string $id, object $result): void
    {
        if (function_exists('cssRoot')) {
            cssRoot();
        }
    }
}
