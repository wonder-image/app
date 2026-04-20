<?php

namespace Wonder\App\Resources\Support;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableLayoutSchema;

abstract class SingletonResource extends Resource
{
    public static function singletonRecordId(): int|string|null
    {
        return 1;
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->hideTitle()
            ->hideButtonAdd()
            ->filters(false, false);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->disable(['create', 'store', 'view', 'delete']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)
            ->only(['show', 'update']);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'edit', 'update'], ['admin'])
            ->api(['show', 'update'], ['admin']);
    }
}
