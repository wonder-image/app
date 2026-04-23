<?php

namespace Wonder\App\Resources\Consent;

use Wonder\App\LegacyGlobals;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\ApiSchema;
use Wonder\App\ResourceSchema\NavigationSchema;
use Wonder\App\ResourceSchema\PageSchema;
use Wonder\App\ResourceSchema\PermissionSchema;
use Wonder\App\ResourceSchema\TableColumn;
use Wonder\App\ResourceSchema\TableLayoutSchema;

final class ConsentEventResource extends Resource
{
    public static string $model = \Wonder\App\Models\Consent\ConsentEvent::class;
    public static array|string $condition = [];
    public static string $orderColumn = 'creation';
    public static string $orderDirection = 'DESC';

    public static function textSchema(): array
    {
        return [
            'label' => 'consenso',
            'plural_label' => 'consensi',
            'last' => 'ultimi',
            'all' => 'tutti',
            'article' => 'i',
            'full' => 'usato',
            'empty' => 'non usato',
            'this' => 'questo',
        ];
    }

    public static function labelSchema(): array
    {
        return [
            'user_id' => 'Utente',
            'consent_type' => 'Consenso',
            'action' => 'Azione',
            'source' => 'Fonte',
            'occurred_at' => 'Accaduto il',
            'locale' => 'Lingua',
            'ip_address' => 'IP',
            'user_agent' => 'Browser',
            'evidence_json' => 'Prova',
        ];
    }

    public static function tableSchema(): array
    {
        return [
            TableColumn::key('user_id')->userName()->link('view'),
            TableColumn::key('consent_type')->text(),
            TableColumn::key('action')->badge()->function('consentEventAction', 'action', 'automaticResize'),
            TableColumn::key('source')->badge()->function('consentEventSource', 'source', 'automaticResize')->size('little'),
            TableColumn::key('occurred_at')->datetime()->size('medium'),
        ];
    }

    public static function tableLayoutSchema(): TableLayoutSchema
    {
        return TableLayoutSchema::for(static::class)
            ->title('Lista '.static::pluralLabel())
            ->results()
            ->hideButtonAdd()
            ->filters()
            ->searchFields(['consent_type', 'source', 'locale', 'ip_address', 'user_agent'])
            ->filterRadio('Azione', 'action', [
                '' => 'Tutte',
                'accept' => 'Accetta',
                'reject' => 'Rifiuta',
                'withdraw' => 'Revoca',
            ])
            ->filterRadio('Fonte', 'source', [
                '' => 'Tutte',
                'web' => 'Web',
                'app' => 'App',
                'api' => 'Api',
                'admin' => 'Admin',
            ]);
    }

    public static function pageSchema(): PageSchema
    {
        return PageSchema::for(static::class)
            ->only(['list', 'view'])
            ->view('show', static::customShowViewPath())
            ->title('view', 'Dettaglio consenso');
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
            ->title('Consensi')
            ->order(30)
            ->authority(['admin', 'administrator']);
    }

    private static function customShowViewPath(): ?string
    {
        $rootApp = (string) LegacyGlobals::get('ROOT_APP', '');

        return $rootApp !== ''
            ? $rootApp.'/view/pages/backend/log/consent/show.php'
            : null;
    }
}
