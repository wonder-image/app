<?php

namespace Wonder\App\Resources\User;

use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;

final class AuthLogResource extends Resource
{
    public static string $model = \Wonder\App\Models\User\AuthLog::class;

    public static function textSchema(): array
    {
        return [
            'label' => 'accesso utente',
            'plural_label' => 'accessi utente',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'gli',
            'full' => 'usato',
            'empty' => 'non usato',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'user_id' => 'Utente',
            'ip' => 'Ip',
            'event' => 'Evento',
            'area' => 'Area',
            'success' => 'Success',
            'user_agent' => 'User Agent',
            'meta' => 'Metadati',
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('user_id')->userName()->link('view'),
            TableColumn::key('ip')->text(),
            TableColumn::key('event')->text(),
            TableColumn::key('area')->text()->size('little'),
            TableColumn::key('success')->status()->size('little'),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->hideButtonAdd()
            ->filters()
            ->searchFields(['ip', 'event', 'area', 'user_agent'])
            ->filterRadio('Area', 'area', [
                '' => 'Tutte',
                'backend' => 'Backend',
                'frontend' => 'Frontend',
                'api' => 'Api',
            ])
            ->filterRadio('Esito', 'success', [
                '' => 'Tutti',
                'true' => 'Successo',
                'false' => 'Errore',
            ]);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->only(['list', 'view'])
            ->view('show', static::customShowViewPath())
            ->title('view', 'Dettaglio accesso');
    }

    public static function apiSchema(): ApiSchema
    {
        return ApiSchema::for(static::class)->enabled(false);
    }

    public static function permissionSchema(): PermissionSchema
    {
        return PermissionSchema::for(static::class)
            ->backend(['list', 'view'], ['admin', 'administrator']);
    }

    public static function navigationSchema(): NavigationSchema
    {
        return NavigationSchema::for(static::class)
            ->section('Log', 'log', 'bi-ear')
            ->title('Accessi Utente')
            ->order(10)
            ->authority(['admin', 'administrator']);
    }

    private static function customShowViewPath(): ?string
    {
        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');

        return $rootApp !== ''
            ? $rootApp.'/view/pages/backend/log/auth-users/show.php'
            : null;
    }
}
