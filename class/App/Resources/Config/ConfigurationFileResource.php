<?php

namespace Wonder\App\Resources\Config;

use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\FormInput;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
final class ConfigurationFileResource extends Resource
{
    public static string $model = \Wonder\App\Models\Config\ConfigurationFile::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'file di configurazione',
            'plural_label' => 'file di configurazione',
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
            'htaccess' => '.htaccess',
            'robots' => 'robots.txt',
        ];
    }

    public static function formSchema(): array
    {
        return [
            FormInput::key('htaccess')->textarea()->prepare('sanitize', false),
            FormInput::key('robots')->textarea()->prepare('sanitize', false),
        ];
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->disable(['list', 'create', 'store', 'view', 'edit', 'update', 'delete']);
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backendCrud(['admin']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Set Up', 'set-up', 'bi-gear')
            ->title('File configurazione')
            ->order(80)
            ->authority(['admin']);
    }

}
