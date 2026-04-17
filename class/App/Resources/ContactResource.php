<?php

namespace Wonder\App\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;

final class ContactResource extends Resource
{
    public static string $model = \Wonder\App\Models\Contact::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'contact',
            'plural_label' => 'contact',
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
            // 'name' => 'Nome',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('name')->text(),
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('name')->text(),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class);
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
            ->enabled(false);
    }
}
