<?php

namespace Wonder\App\Resources;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableSchema;

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
        return FormSchema::for(static::class)
            ->toArray();
    }

    public static function tableSchema(): array
    {
        return TableSchema::for(static::class)
            ->toArray();
    }

    public static function pageSchema(): array
    {
        return PageSchema::for(static::class)->toArray();
    }

    public static function apiSchema(): array
    {
        return ApiSchema::for(static::class)
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
            ->enabled(false)
            ->toArray();
    }
}
