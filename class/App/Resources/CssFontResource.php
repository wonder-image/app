<?php

namespace Wonder\App\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableSchema;

final class CssFontResource extends Resource
{
    public static string $model = \Wonder\App\Models\CssFont::class;

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
        return FormSchema::for(static::class)
            ->fields([
                FormSchema::text('font_family')->required(),
                FormSchema::text('link')->required(),
            ])
            ->sidebarFields([
                FormSchema::text('name')->required(),
                FormSchema::select('visible', [
                    'true' => 'Visibile',
                    'false' => 'Nascosto',
                ])->value('true')->required(),
            ])
            ->toArray();
    }

    public static function tableSchema(): array
    {
        return TableSchema::for(static::class)
            ->column(
                TableSchema::text('name')
                    ->link('edit')
            )
            ->column(
                TableSchema::badge('visible')
                    ->function('visible', 'id', 'automaticResize')
                    ->size('little')
            )
            ->action('edit')
            ->action('delete')
            ->searchable(['font_family', 'name'])
            ->toArray();
    }

    public static function pageSchema(): array
    {
        return PageSchema::for(static::class)->toArray();
    }

    public static function apiSchema(): array
    {
        return ApiSchema::for(static::class)
            ->fields('index', ['id', 'name', 'font_family', 'visible'])
            ->fields('show', ['id', 'name', 'link', 'font_family', 'visible'])
            ->fields('store', ['name', 'link', 'font_family', 'visible'])
            ->fields('update', ['name', 'link', 'font_family', 'visible'])
            ->toArray();
    }

    public static function permissionSchema(): array
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin'])
            ->apiCrud(['admin'])
            ->toArray();
    }

    public static function navigationSchema(): array
    {
        return NavigationSchema::for(static::class)
            ->section('Stile', 'css', 'bi-award')
            ->authority(['admin'])
            ->order(20)
            ->toArray();
    }
}
