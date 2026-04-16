<?php

namespace Wonder\App;

use mysqli;
use RuntimeException;
use Wonder\App\ResourceSchema\ApiSchema as ResourceApiSchema;
use Wonder\App\ResourceSchema\FormSchema as ResourceFormSchema;
use Wonder\App\ResourceSchema\NavigationSchema as ResourceNavigationSchema;
use Wonder\App\ResourceSchema\PageSchema as ResourcePageSchema;
use Wonder\App\ResourceSchema\PermissionSchema as ResourcePermissionSchema;
use Wonder\App\ResourceSchema\TableSchema as ResourceTableSchema;
use Wonder\Sql\Query;

abstract class Resource
{
    public static string $model = '';

    public static array|string $condition = ['deleted' => 'false'];
    public static string|int|null $limit = null;
    public static string $orderColumn = 'creation';
    public static string $orderDirection = 'DESC';

    public static function modelClass(): string
    {
        $modelClass = trim(static::$model);

        if ($modelClass === '' || !class_exists($modelClass)) {
            throw new RuntimeException('Model non valido per la resource '.static::class.'.');
        }

        if (!is_subclass_of($modelClass, Model::class)) {
            throw new RuntimeException("{$modelClass} deve estendere ".Model::class.'.');
        }

        return $modelClass;
    }

    public static function modelTable(): string
    {
        return static::modelClass()::$table;
    }

    public static function slug(): string
    {
        return trim((string) (static::modelClass()::$folder ?? ''));
    }

    public static function icon(): string
    {
        return trim((string) (static::modelClass()::$icon ?? ''));
    }

    public static function labelSchema(): array
    {
        return [];
    }

    public static function textSchema(): array
    {
        return [];
    }

    public static function formSchema(): array
    {
        return static::form()->toArray();
    }

    public static function tableSchema(): array
    {
        return static::table()->toArray();
    }

    public static function pageSchema(): array
    {
        return static::page()->toArray();
    }

    public static function apiSchema(): array
    {
        return static::api()->toArray();
    }

    public static function permissionSchema(): array
    {
        return static::permission()->toArray();
    }

    public static function navigationSchema(): array
    {
        return static::navigation()->toArray();
    }

    public static function querySchema(): array
    {
        return [
            'condition' => static::$condition,
            'limit' => static::$limit,
            'order' => [
                'column' => static::$orderColumn,
                'direction' => static::$orderDirection,
            ],
        ];
    }

    public static function query(): Query
    {
        $modelClass = static::modelClass();

        return $modelClass::query();
    }

    public static function connection(): mysqli
    {
        $modelClass = static::modelClass();

        return $modelClass::connection();
    }

    public static function formFields(): array
    {
        return static::flattenFormItems(static::formSchema());
    }

    public static function getInput(string $key): mixed
    {
        foreach (static::formFields() as $item) {
            if (is_object($item) && property_exists($item, 'name') && $item->name === $key) {
                return $item;
            }
        }

        return null;
    }

    public static function getLabel(?string $key = null): string|array
    {
        $labels = static::labelSchema();

        return $key === null ? $labels : ($labels[$key] ?? '');
    }

    public static function getText(?string $key = null): string|array
    {
        $texts = static::textSchema();

        return $key === null ? $texts : ($texts[$key] ?? '');
    }

    public static function getTable(?string $key = null): mixed
    {
        $schema = static::tableSchema();

        return $key === null ? $schema : ($schema[$key] ?? null);
    }

    public static function getPage(?string $key = null): mixed
    {
        $schema = static::pageSchema();

        return $key === null ? $schema : ($schema[$key] ?? null);
    }

    public static function getApi(?string $key = null): mixed
    {
        $schema = static::apiSchema();

        return $key === null ? $schema : ($schema[$key] ?? null);
    }

    public static function getPermission(?string $key = null): mixed
    {
        $schema = static::permissionSchema();

        return $key === null ? $schema : ($schema[$key] ?? null);
    }

    public static function getNavigation(?string $key = null): mixed
    {
        $schema = static::navigationSchema();

        return $key === null ? $schema : ($schema[$key] ?? null);
    }

    public static function getQuery(?string $key = null): mixed
    {
        $schema = static::querySchema();

        return $key === null ? $schema : ($schema[$key] ?? null);
    }

    public static function table(): ResourceTableSchema
    {
        return ResourceTableSchema::for(static::class);
    }

    public static function form(): ResourceFormSchema
    {
        return ResourceFormSchema::for(static::class);
    }

    public static function page(): ResourcePageSchema
    {
        return ResourcePageSchema::for(static::class);
    }

    public static function api(): ResourceApiSchema
    {
        return ResourceApiSchema::for(static::class);
    }

    public static function navigation(): ResourceNavigationSchema
    {
        return ResourceNavigationSchema::for(static::class);
    }

    public static function permission(): ResourcePermissionSchema
    {
        return ResourcePermissionSchema::for(static::class);
    }

    public static function label(): string
    {
        $label = trim((string) static::getText('label'));

        return $label !== '' ? $label : static::fallbackTitle(static::slug());
    }

    public static function pluralLabel(): string
    {
        $pluralLabel = trim((string) static::getText('plural_label'));

        if ($pluralLabel !== '') {
            return $pluralLabel;
        }

        return static::label();
    }

    public static function titleLabel(): string
    {
        return static::fallbackTitle(static::label());
    }

    public static function titlePluralLabel(): string
    {
        return static::fallbackTitle(static::pluralLabel());
    }

    public static function defaultPageTitles(): array
    {
        return [
            'list' => 'Lista '.static::pluralLabel(),
            'create' => 'Aggiungi '.static::label(),
            'edit' => 'Modifica '.static::label(),
            'view' => static::titleLabel(),
        ];
    }

    private static function flattenFormItems(array $items): array
    {
        $flattened = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $flattened = array_merge($flattened, static::flattenFormItems($item));
                continue;
            }

            $flattened[] = $item;
        }

        return $flattened;
    }

    private static function fallbackTitle(string $value): string
    {
        $value = trim(str_replace(['-', '_'], ' ', $value));

        if ($value === '') {
            return '';
        }

        return function_exists('mb_convert_case')
            ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8')
            : ucfirst($value);
    }
}
